<?php

namespace App\Services;

use App\Enums\StatusPelatihan;
use App\Enums\StatusPendaftaran;
use App\Models\PelatihanPeserta;
use App\Models\SumberDana;
use App\Models\User;
use App\Support\IsianPeserta;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Verifikasi pendaftaran oleh admin (PRD §8.6, ARCHITECTURE §7.2).
 */
class PendaftaranService
{
    public function __construct(
        private readonly PenggantiService $pengganti,
        private readonly AsramaService $asrama,
    ) {}

    /**
     * Isian yang ditampilkan di form verifikasi: isian terbaru dari pendaftaran ini,
     * dilengkapi data peserta untuk kolom yang tidak ada di isian.
     *
     * @return array<string, mixed>
     */
    public function isianVerifikasi(PelatihanPeserta $pendaftaran): array
    {
        return [
            ...IsianPeserta::dariPeserta($pendaftaran->peserta),
            ...Arr::only($pendaftaran->data_isian ?? [], IsianPeserta::KOLOM),
        ];
    }

    /**
     * Kolom yang isian pendaftaran ini berbeda dengan data peserta saat ini:
     * [kolom => [nilai peserta, nilai isian]]. Selalu kosong untuk NIK baru.
     *
     * @return array<string, array{0: mixed, 1: mixed}>
     */
    public function perbedaanIsian(PelatihanPeserta $pendaftaran): array
    {
        return IsianPeserta::perbedaan(
            IsianPeserta::dariPeserta($pendaftaran->peserta),
            Arr::only($pendaftaran->data_isian ?? [], IsianPeserta::KOLOM),
        );
    }

    /**
     * Memverifikasi pendaftaran: menerapkan data peserta yang sudah diperiksa admin
     * (jika ada), memakai KTP & foto terbaru, lalu mengisi snapshot (CLAUDE.md aturan 6).
     *
     * @param  array<string, mixed>|null  $dataPeserta  Data peserta hasil pemeriksaan admin; null = data peserta tidak diubah.
     * @param  int|null  $menggantikanId  Pendaftaran batal yang digantikan (FR-BTL-04).
     * @param  array{sumber_dana_id: int|string|null, sumber_dana_keterangan?: ?string}|null  $sumberDana  Sumber dana hasil pemeriksaan admin (PF-16); null = tidak diubah.
     *
     * @throws ValidationException
     */
    public function verifikasi(PelatihanPeserta $pendaftaran, User $oleh, ?array $dataPeserta = null, ?string $catatan = null, ?int $menggantikanId = null, ?array $sumberDana = null): void
    {
        DB::transaction(function () use ($pendaftaran, $oleh, $dataPeserta, $catatan, $menggantikanId, $sumberDana): void {
            $terkunci = PelatihanPeserta::query()->lockForUpdate()->findOrFail($pendaftaran->getKey());

            if (! $terkunci->status->bisaMenjadi(StatusPendaftaran::Terverifikasi)) {
                throw ValidationException::withMessages([
                    'status' => 'Hanya pendaftaran yang menunggu verifikasi yang dapat diverifikasi.',
                ]);
            }

            $peserta = $terkunci->peserta()->lockForUpdate()->firstOrFail();

            if ($dataPeserta !== null) {
                $data = IsianPeserta::normalisasi($dataPeserta);

                Validator::make($data, IsianPeserta::aturan(masterHarusAktif: false, nikUnikKecuali: $peserta), IsianPeserta::pesan(), IsianPeserta::LABEL)
                    ->validate();

                $peserta->fill(Arr::only($data, IsianPeserta::KOLOM));
            }

            // KTP dan foto dari pendaftaran ini adalah yang paling baru.
            $peserta->fill(array_filter([
                'file_ktp' => $terkunci->data_isian['file_ktp'] ?? null,
                'foto' => $terkunci->data_isian['foto'] ?? null,
            ]));
            $peserta->save();

            if ($sumberDana !== null) {
                $terkunci->fill($this->validasiSumberDana($sumberDana, $terkunci));
            }

            $terkunci->forceFill([
                'status' => StatusPendaftaran::Terverifikasi,
                'jabatan_id_saat_pelatihan' => $peserta->jabatan_id,
                'desa_id_saat_pelatihan' => $peserta->desa_id,
                'status_ptkp_id_saat_pelatihan' => $peserta->status_ptkp_id,
                'waktu_pelantikan_saat_pelatihan' => $peserta->waktu_pelantikan,
                'diverifikasi_oleh' => $oleh->getKey(),
                'diverifikasi_pada' => now(),
                'catatan_panitia' => filled($catatan) ? trim($catatan) : $terkunci->catatan_panitia,
            ])->save();

            if ($menggantikanId !== null) {
                $this->pengganti->tetapkan($terkunci, PelatihanPeserta::query()->findOrFail($menggantikanId));
            }
        });

        $pendaftaran->refresh();
    }

    /**
     * Sumber dana boleh yang aktif atau yang sedang dipakai pendaftaran ini; keterangan
     * wajib jika sumber dana membutuhkannya, dan dikosongkan jika tidak (FR-REG-04).
     *
     * @param  array<string, mixed>  $data
     * @return array{sumber_dana_id: int, sumber_dana_keterangan: ?string}
     *
     * @throws ValidationException
     */
    private function validasiSumberDana(array $data, PelatihanPeserta $pendaftaran): array
    {
        $keterangan = is_string($data['sumber_dana_keterangan'] ?? null) ? trim($data['sumber_dana_keterangan']) : null;
        $data = ['sumber_dana_id' => $data['sumber_dana_id'] ?? null, 'sumber_dana_keterangan' => filled($keterangan) ? $keterangan : null];

        Validator::make($data, [
            'sumber_dana_id' => ['required', 'integer', Rule::exists('sumber_dana', 'id')->where(fn ($q) => $q
                ->where('is_aktif', true)
                ->orWhere('id', $pendaftaran->sumber_dana_id))],
        ], [
            'sumber_dana_id.exists' => 'Pilihan sumber dana tidak tersedia.',
        ], ['sumber_dana_id' => 'sumber dana'])->validate();

        $butuhKeterangan = (bool) SumberDana::query()->whereKey($data['sumber_dana_id'])->value('butuh_keterangan');

        if ($butuhKeterangan && $data['sumber_dana_keterangan'] === null) {
            throw ValidationException::withMessages(['sumber_dana_keterangan' => 'Keterangan sumber dana wajib diisi.']);
        }

        return [
            'sumber_dana_id' => (int) $data['sumber_dana_id'],
            'sumber_dana_keterangan' => $butuhKeterangan ? $data['sumber_dana_keterangan'] : null,
        ];
    }

    /**
     * Membatalkan peserta: alasan wajib, jatah kamar dilepas, presensi yang sudah
     * tercatat tidak dihapus (ARCHITECTURE §7.5, CLAUDE.md aturan 7).
     *
     * @throws ValidationException
     */
    public function batalkan(PelatihanPeserta $pendaftaran, User $oleh, string $alasan): void
    {
        if (blank($alasan)) {
            throw ValidationException::withMessages(['alasan_batal' => 'Alasan pembatalan wajib diisi.']);
        }

        DB::transaction(function () use ($pendaftaran, $oleh, $alasan): void {
            $terkunci = PelatihanPeserta::query()->lockForUpdate()->findOrFail($pendaftaran->getKey());

            if (! $terkunci->status->bisaMenjadi(StatusPendaftaran::Batal)) {
                throw ValidationException::withMessages([
                    'status' => 'Hanya peserta yang menunggu verifikasi atau terverifikasi yang dapat dibatalkan.',
                ]);
            }

            $terkunci->forceFill([
                'status' => StatusPendaftaran::Batal,
                'alasan_batal' => trim($alasan),
                'dibatalkan_oleh' => $oleh->getKey(),
                'dibatalkan_pada' => now(),
            ])->save();

            if ($penempatan = $terkunci->penempatanKamar) {
                $this->asrama->keluarkan($penempatan);
            }
        });

        $pendaftaran->refresh();
    }

    /**
     * Mengembalikan pendaftaran batal ke status sebelum dibatalkan (PF-14): terverifikasi bila
     * pernah diverifikasi, selain itu menunggu verifikasi. Ini koreksi salah batal, bukan
     * perpindahan status biasa. Kelas tetap; kamar yang sudah dilepas tidak ikut kembali.
     *
     * @throws ValidationException
     */
    public function kembalikan(PelatihanPeserta $pendaftaran): StatusPendaftaran
    {
        $tujuan = DB::transaction(function () use ($pendaftaran): StatusPendaftaran {
            $terkunci = PelatihanPeserta::query()->with(['pelatihan', 'pengganti.peserta'])->lockForUpdate()->findOrFail($pendaftaran->getKey());

            match (true) {
                $terkunci->status !== StatusPendaftaran::Batal => $this->tolak('status', 'Hanya pendaftaran yang batal yang dapat dikembalikan.'),
                $terkunci->pelatihan->status === StatusPelatihan::Selesai => $this->tolak('status', 'Pelatihan sudah selesai, sehingga pembatalan tidak dapat dikembalikan.'),
                $terkunci->pengganti !== null => $this->tolak('status', "Peserta ini sudah digantikan {$terkunci->pengganti->peserta->nama_lengkap}, sehingga pembatalannya tidak dapat dikembalikan."),
                default => null,
            };

            $tujuan = $terkunci->diverifikasi_pada !== null ? StatusPendaftaran::Terverifikasi : StatusPendaftaran::Terdaftar;

            // Alasan & waktu pembatalan tetap tersimpan di audit log.
            $terkunci->forceFill([
                'status' => $tujuan,
                'alasan_batal' => null,
                'dibatalkan_oleh' => null,
                'dibatalkan_pada' => null,
            ])->save();

            return $tujuan;
        });

        $pendaftaran->refresh();

        return $tujuan;
    }

    /**
     * Menghapus pendaftaran tidak sah, misal data iseng dari form publik (PF-14). Hanya untuk
     * pendaftaran yang belum pernah diverifikasi dan tidak punya pengganti. Berkasnya ikut
     * dihapus, begitu juga data peserta bila tidak punya pendaftaran lain. Tercatat di audit log.
     *
     * @throws ValidationException
     */
    public function hapus(PelatihanPeserta $pendaftaran): void
    {
        $folder = DB::transaction(function () use ($pendaftaran): string {
            $terkunci = PelatihanPeserta::query()->with('peserta')->lockForUpdate()->findOrFail($pendaftaran->getKey());

            if ($terkunci->diverifikasi_pada !== null || ! in_array($terkunci->status, [StatusPendaftaran::Terdaftar, StatusPendaftaran::Batal], true)) {
                $this->tolak('status', 'Hanya pendaftaran yang belum pernah diverifikasi yang dapat dihapus. Gunakan Batalkan untuk peserta yang sudah diverifikasi.');
            }

            if ($terkunci->pengganti()->exists()) {
                $this->tolak('status', 'Pendaftaran ini sudah punya pengganti, sehingga tidak dapat dihapus.');
            }

            $peserta = $terkunci->peserta;
            $terkunci->delete();

            if ($peserta->pendaftaran()->doesntExist()) {
                $peserta->delete();
            }

            return "pendaftaran/{$terkunci->getKey()}";
        });

        // Berkas dihapus setelah transaksi berhasil agar tidak hilang bila penghapusan dibatalkan.
        Storage::disk(RegistrasiService::DISK)->deleteDirectory($folder);
    }

    /**
     * @throws ValidationException
     */
    private function tolak(string $kolom, string $pesan): never
    {
        throw ValidationException::withMessages([$kolom => $pesan]);
    }

    /**
     * Verifikasi beberapa pendaftaran sekaligus tanpa mengubah data peserta. Pendaftaran
     * yang isiannya berbeda dengan data peserta dilewati agar diperiksa satu per satu.
     *
     * @param  iterable<PelatihanPeserta>  $daftar
     * @return array{terverifikasi: int, dilewati: int}
     */
    public function verifikasiMassal(iterable $daftar, User $oleh): array
    {
        $hasil = ['terverifikasi' => 0, 'dilewati' => 0];

        foreach ($daftar as $pendaftaran) {
            if ($pendaftaran->status !== StatusPendaftaran::Terdaftar || $this->perbedaanIsian($pendaftaran) !== []) {
                $hasil['dilewati']++;

                continue;
            }

            $this->verifikasi($pendaftaran, $oleh);
            $hasil['terverifikasi']++;
        }

        return $hasil;
    }
}
