<?php

namespace App\Services;

use App\Enums\StatusPelatihan;
use App\Enums\StatusPendaftaran;
use App\Models\KelasPelatihan;
use App\Models\Pelatihan;
use App\Models\PelatihanPeserta;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Kelas dalam satu pelatihan dan pembagian pesertanya (PRD §8.8).
 */
class KelasService
{
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
            throw ValidationException::withMessages(['nama_kelas' => 'Nama kelas wajib diisi.']);
        }

        if (mb_strlen($nama) > self::MAKS_NAMA) {
            throw ValidationException::withMessages(['nama_kelas' => 'Nama kelas maksimal '.self::MAKS_NAMA.' karakter.']);
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
                throw ValidationException::withMessages([
                    'kelas' => "Kelas {$kelas->nama_kelas} masih berisi {$jumlah} peserta. Pindahkan pesertanya ke kelas lain terlebih dahulu.",
                ]);
            }

            if ($kelas->lembarPresensi()->exists()) {
                throw ValidationException::withMessages([
                    'kelas' => "Kelas {$kelas->nama_kelas} sudah memiliki presensi, sehingga tidak dapat dihapus.",
                ]);
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
                throw ValidationException::withMessages([
                    'status' => 'Hanya peserta terverifikasi yang dapat ditetapkan kelasnya.',
                ]);
            }

            if ($kelas !== null && $kelas->pelatihan_id !== $terkunci->pelatihan_id) {
                throw ValidationException::withMessages([
                    'kelas_pelatihan_id' => 'Kelas harus milik pelatihan yang sama dengan pendaftaran peserta.',
                ]);
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
     * `$ulangSemua` membagi ulang seluruh peserta terverifikasi dari awal.
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
                throw ValidationException::withMessages(['kelas' => 'Buat kelas terlebih dahulu sebelum membagi peserta.']);
            }

            $terverifikasi = fn () => $terkunci->pendaftaran()->where('status', StatusPendaftaran::Terverifikasi);

            if (! $ulangSemua) {
                $terverifikasi()
                    ->whereIn('kelas_pelatihan_id', array_keys($isi))
                    ->toBase()
                    ->selectRaw('kelas_pelatihan_id, count(*) as jumlah')
                    ->groupBy('kelas_pelatihan_id')
                    ->pluck('jumlah', 'kelas_pelatihan_id')
                    ->each(function (int $jumlah, int $kelasId) use (&$isi): void {
                        $isi[$kelasId] = $jumlah;
                    });
            }

            $peserta = $terverifikasi()
                ->when(! $ulangSemua, fn ($query) => $query->whereNull('kelas_pelatihan_id'))
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
            throw ValidationException::withMessages(['kelas' => 'Kelas tidak dapat diubah karena pelatihan sudah selesai.']);
        }
    }

    /**
     * @throws ValidationException
     */
    private function tolakDuplikat(string $nama): never
    {
        throw ValidationException::withMessages(['nama_kelas' => "Kelas {$nama} sudah ada di pelatihan ini. Gunakan nama lain."]);
    }
}
