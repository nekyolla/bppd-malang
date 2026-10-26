<?php

namespace App\Services;

use App\Enums\StatusPelatihan;
use App\Enums\StatusPendaftaran;
use App\Models\KelasPelatihan;
use App\Models\Pelatihan;
use App\Models\PelatihanPeserta;
use App\Services\Concerns\MenolakAturan;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Kelas dalam satu pelatihan dan pembagian pesertanya (PRD §8.8).
 */
class KelasService
{
    use MenolakAturan;

    public const MAKS_NAMA = 10;

    /**
     * Membuat kelas baru di pelatihan, atau mengganti nama kelas yang sudah ada (FR-KLS-01).
     *
     * @throws ValidationException
     */
    public function simpan(Pelatihan $pelatihan, string $nama, ?KelasPelatihan $kelas = null): KelasPelatihan
    {
        $nama = trim($nama);

        $this->pastikanBelumSelesai($pelatihan);

        if ($nama === '') {
            $this->tolak('nama_kelas', 'Nama kelas wajib diisi.');
        }

        if (mb_strlen($nama) > self::MAKS_NAMA) {
            $this->tolak('nama_kelas', 'Nama kelas maksimal '.self::MAKS_NAMA.' karakter.');
        }

        $sudahAda = $pelatihan->kelas()
            ->where('nama_kelas', $nama)
            ->when($kelas, fn ($query) => $query->whereKeyNot($kelas->getKey()))
            ->exists();

        if ($sudahAda) {
            $this->tolakDuplikat($nama);
        }

        $kelas ??= new KelasPelatihan(['pelatihan_id' => $pelatihan->getKey()]);

        try {
            $kelas->fill(['nama_kelas' => $nama])->save();
        } catch (UniqueConstraintViolationException) {
            $this->tolakDuplikat($nama);
        }

        return $kelas;
    }

    /**
     * Menghapus kelas yang belum dipakai. Peserta batal yang masih tercatat di kelas ini dilepas.
     *
     * @throws ValidationException
     */
    public function hapus(KelasPelatihan $kelas): void
    {
        DB::transaction(function () use ($kelas): void {
            $pelatihan = Pelatihan::query()->lockForUpdate()->findOrFail($kelas->pelatihan_id);

            $this->pastikanBelumSelesai($pelatihan);

            $jumlah = $kelas->pendaftaran()->where('status', '!=', StatusPendaftaran::Batal)->count();

            if ($jumlah > 0) {
                $this->tolak('kelas', "Kelas {$kelas->nama_kelas} masih berisi {$jumlah} peserta. Pindahkan pesertanya ke kelas lain terlebih dahulu.");
            }

            if ($kelas->lembarPresensi()->exists()) {
                $this->tolak('kelas', "Kelas {$kelas->nama_kelas} sudah memiliki presensi, sehingga tidak dapat dihapus.");
            }

            $kelas->pendaftaran()->get()
                ->each(fn (PelatihanPeserta $pendaftaran) => $pendaftaran->forceFill(['kelas_pelatihan_id' => null])->save());

            $kelas->delete();
        });
    }

    /**
     * Menetapkan kelas satu peserta terverifikasi; `null` melepasnya dari kelas (FR-KLS-02).
     * Kelas harus milik pelatihan yang sama dengan pendaftaran (FR-KLS-04).
     *
     * @throws ValidationException
     */
    public function tetapkan(PelatihanPeserta $pendaftaran, ?KelasPelatihan $kelas): void
    {
        DB::transaction(function () use ($pendaftaran, $kelas): void {
            $terkunci = PelatihanPeserta::query()->lockForUpdate()->findOrFail($pendaftaran->getKey());

            if ($terkunci->status !== StatusPendaftaran::Terverifikasi) {
                $this->tolak('status', 'Hanya peserta terverifikasi yang dapat ditetapkan kelasnya.');
            }

            if ($kelas !== null && $kelas->pelatihan_id !== $terkunci->pelatihan_id) {
                $this->tolak('kelas_pelatihan_id', 'Kelas harus milik pelatihan yang sama dengan pendaftaran peserta.');
            }

            if ($terkunci->kelas_pelatihan_id !== $kelas?->getKey() && $this->kelasTerkunci($terkunci)) {
                $this->tolak('kelas_pelatihan_id', 'Kelas tidak dapat diubah karena peserta sudah memiliki presensi.');
            }

            $terkunci->forceFill(['kelas_pelatihan_id' => $kelas?->getKey()])->save();
        });

        $pendaftaran->refresh();
    }

    /**
     * Menetapkan kelas beberapa peserta sekaligus. Peserta yang tidak terverifikasi
     * atau berasal dari pelatihan lain dilewati.
     *
     * @param  iterable<PelatihanPeserta>  $daftar
     * @return array{ditetapkan: int, dilewati: int}
     */
    public function tetapkanMassal(iterable $daftar, KelasPelatihan $kelas): array
    {
        $hasil = ['ditetapkan' => 0, 'dilewati' => 0];

        foreach ($daftar as $pendaftaran) {
            try {
                $this->tetapkan($pendaftaran, $kelas);
                $hasil['ditetapkan']++;
            } catch (ValidationException) {
                $hasil['dilewati']++;
            }
        }

        return $hasil;
    }

    /**
     * Membagi peserta terverifikasi secara acak dan rata ke semua kelas pelatihan (FR-KLS-03).
     * Bawaan: hanya peserta yang belum punya kelas, dengan memperhitungkan isi kelas saat ini.
     * `$ulangSemua` membagi ulang seluruh peserta terverifikasi, kecuali yang sudah punya presensi.
     *
     * @return int Jumlah peserta yang dibagi.
     *
     * @throws ValidationException
     */
    public function bagiAcak(Pelatihan $pelatihan, bool $ulangSemua = false): int
    {
        return DB::transaction(function () use ($pelatihan, $ulangSemua): int {
            // Kunci pelatihan agar dua pembagian tidak berjalan bersamaan.
            $terkunci = Pelatihan::query()->lockForUpdate()->findOrFail($pelatihan->getKey());

            $this->pastikanBelumSelesai($terkunci);

            $isi = $terkunci->kelas()->orderBy('nama_kelas')->pluck('id')->mapWithKeys(fn (int $id): array => [$id => 0])->all();

            if ($isi === []) {
                $this->tolak('kelas', 'Buat kelas terlebih dahulu sebelum membagi peserta.');
            }

            $terverifikasi = fn () => $terkunci->pendaftaran()->where('status', StatusPendaftaran::Terverifikasi);
            // Peserta yang kelasnya tidak diubah: yang sudah punya kelas (bawaan), atau
            // yang sudah punya presensi (selalu, termasuk saat membagi ulang semua).
            $kelasnyaTetap = fn ($query) => $ulangSemua
                ? $query->whereHas('presensi')
                : $query->whereNotNull('kelas_pelatihan_id');

            $terverifikasi()
                ->where($kelasnyaTetap)
                ->whereIn('kelas_pelatihan_id', array_keys($isi))
                ->toBase()
                ->selectRaw('kelas_pelatihan_id, count(*) as jumlah')
                ->groupBy('kelas_pelatihan_id')
                ->pluck('jumlah', 'kelas_pelatihan_id')
                ->each(function (int $jumlah, int $kelasId) use (&$isi): void {
                    $isi[$kelasId] = $jumlah;
                });

            $peserta = $terverifikasi()
                ->whereNot($kelasnyaTetap)
                ->lockForUpdate()
                ->get()
                ->shuffle();

            foreach ($peserta as $pendaftaran) {
                // Kelas dengan isi paling sedikit; bila sama, urutan nama kelas.
                $tujuan = array_search(min($isi), $isi, true);
                $isi[$tujuan]++;

                // Disimpan per model (bukan query update massal) agar tercatat di audit log.
                $pendaftaran->forceFill(['kelas_pelatihan_id' => $tujuan])->save();
            }

            return $peserta->count();
        });
    }

    /**
     * Kelas peserta terkunci begitu presensinya tercatat: peserta tidak berpindah kelas
     * selama pelatihan (keputusan pemilik repo, 26 Okt 2026).
     */
    public function kelasTerkunci(PelatihanPeserta $pendaftaran): bool
    {
        return $pendaftaran->presensi()->exists();
    }

    /**
     * Usulan nama untuk kelas baru: huruf A–Z pertama yang belum dipakai.
     */
    public function namaBerikutnya(Pelatihan $pelatihan): ?string
    {
        $terpakai = $pelatihan->kelas()->pluck('nama_kelas')->map(fn (string $nama): string => mb_strtoupper($nama))->all();

        return collect(range('A', 'Z'))->first(fn (string $huruf): bool => ! in_array($huruf, $terpakai, true));
    }

    /**
     * Setelah pelatihan selesai, kelas dan pembagiannya menjadi dasar rekap dan tidak diubah lagi.
     *
     * @throws ValidationException
     */
    private function pastikanBelumSelesai(Pelatihan $pelatihan): void
    {
        if ($pelatihan->status === StatusPelatihan::Selesai) {
            $this->tolak('kelas', 'Kelas tidak dapat diubah karena pelatihan sudah selesai.');
        }
    }

    /**
     * @throws ValidationException
     */
    private function tolakDuplikat(string $nama): never
    {
        $this->tolak('nama_kelas', "Kelas {$nama} sudah ada di pelatihan ini. Gunakan nama lain.");
    }
}
