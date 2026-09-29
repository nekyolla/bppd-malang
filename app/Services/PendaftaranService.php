<?php

namespace App\Services;

use App\Enums\StatusPendaftaran;
use App\Models\PelatihanPeserta;
use App\Models\User;
use App\Support\IsianPeserta;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
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
     *
     * @throws ValidationException
     */
    public function verifikasi(PelatihanPeserta $pendaftaran, User $oleh, ?array $dataPeserta = null, ?string $catatan = null, ?int $menggantikanId = null): void
    {
        DB::transaction(function () use ($pendaftaran, $oleh, $dataPeserta, $catatan, $menggantikanId): void {
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
