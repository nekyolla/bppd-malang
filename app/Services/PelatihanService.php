<?php

namespace App\Services;

use App\Enums\StatusPelatihan;
use App\Enums\StatusPendaftaran;
use App\Enums\TipeLokasi;
use App\Models\JudulPelatihan;
use App\Models\Pelatihan;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Pembuatan pelatihan dan perpindahan statusnya (PRD §8.5, ARCHITECTURE §7.1).
 */
class PelatihanService
{
    /**
     * Membuat atau mengubah pelatihan.
     *
     * @param  array{judul_pelatihan_id: int|string, batch_ke: int|string, tanggal_mulai: string|\DateTimeInterface, tanggal_selesai: string|\DateTimeInterface, tipe_lokasi: TipeLokasi|string, keterangan_lokasi?: ?string}  $data
     *
     * @throws ValidationException
     */
    public function simpan(array $data, ?Pelatihan $pelatihan = null): Pelatihan
    {
        $tipeLokasi = $data['tipe_lokasi'] instanceof TipeLokasi ? $data['tipe_lokasi'] : TipeLokasi::from($data['tipe_lokasi']);
        $mulai = Carbon::parse($data['tanggal_mulai'])->startOfDay();
        $selesai = Carbon::parse($data['tanggal_selesai'])->startOfDay();
        $keterangan = trim((string) ($data['keterangan_lokasi'] ?? ''));

        $atribut = [
            'judul_pelatihan_id' => (int) $data['judul_pelatihan_id'],
            'batch_ke' => (int) $data['batch_ke'],
            'tanggal_mulai' => $mulai->toDateString(),
            'tanggal_selesai' => $selesai->toDateString(),
            // Tahun anggaran sama dengan tahun pelaksanaan (BR-07).
            'tahun_anggaran' => $mulai->year,
            'tipe_lokasi' => $tipeLokasi,
            'keterangan_lokasi' => $tipeLokasi === TipeLokasi::Luar ? $keterangan : null,
        ];

        if ($selesai->lt($mulai)) {
            throw ValidationException::withMessages(['tanggal_selesai' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.']);
        }

        if ($tipeLokasi === TipeLokasi::Luar && $keterangan === '') {
            throw ValidationException::withMessages(['keterangan_lokasi' => 'Keterangan lokasi wajib diisi untuk pelatihan di luar BBPD.']);
        }

        $sudahAda = Pelatihan::query()
            ->where('judul_pelatihan_id', $atribut['judul_pelatihan_id'])
            ->where('tahun_anggaran', $atribut['tahun_anggaran'])
            ->where('batch_ke', $atribut['batch_ke'])
            ->when($pelatihan, fn ($query) => $query->whereKeyNot($pelatihan->getKey()))
            ->exists();

        if ($sudahAda) {
            $this->tolakDuplikat($atribut);
        }

        if ($pelatihan && $pelatihan->tipe_lokasi !== $tipeLokasi && $this->sudahAdaPenghuniKamar($pelatihan)) {
            throw ValidationException::withMessages(['tipe_lokasi' => 'Tipe lokasi tidak dapat diubah karena sudah ada peserta yang ditempatkan di kamar asrama.']);
        }

        $pelatihan ??= new Pelatihan;

        try {
            $pelatihan->fill($atribut)->save();
        } catch (UniqueConstraintViolationException) {
            $this->tolakDuplikat($atribut);
        }

        return $pelatihan;
    }

    /**
     * Memindahkan status ke tahap berikutnya: draft → dibuka → berjalan → selesai.
     * Saat selesai, semua peserta terverifikasi ikut selesai (FR-DFT-07).
     *
     * @throws ValidationException
     */
    public function ubahStatus(Pelatihan $pelatihan, StatusPelatihan $tujuan): void
    {
        DB::transaction(function () use ($pelatihan, $tujuan): void {
            $terkunci = Pelatihan::query()->lockForUpdate()->findOrFail($pelatihan->getKey());

            if ($terkunci->status->berikutnya() !== $tujuan) {
                throw ValidationException::withMessages([
                    'status' => "Status pelatihan tidak dapat diubah dari \"{$terkunci->status->getLabel()}\" menjadi \"{$tujuan->getLabel()}\".",
                ]);
            }

            if ($tujuan === StatusPelatihan::Selesai) {
                $this->selesaikanPendaftaran($terkunci);
            }

            $terkunci->forceFill(['status' => $tujuan])->save();
        });

        $pelatihan->refresh();
    }

    /**
     * @throws ValidationException
     */
    private function selesaikanPendaftaran(Pelatihan $pelatihan): void
    {
        $menunggu = $pelatihan->pendaftaran()->where('status', StatusPendaftaran::Terdaftar)->count();

        if ($menunggu > 0) {
            throw ValidationException::withMessages([
                'status' => "Masih ada {$menunggu} peserta yang menunggu verifikasi. Verifikasi atau batalkan terlebih dahulu.",
            ]);
        }

        // Disimpan per model (bukan query update massal) agar tercatat di audit log.
        $pelatihan->pendaftaran()
            ->where('status', StatusPendaftaran::Terverifikasi)
            ->lazyById()
            ->each(fn ($pendaftaran) => $pendaftaran->forceFill(['status' => StatusPendaftaran::Selesai])->save());
    }

    private function sudahAdaPenghuniKamar(Pelatihan $pelatihan): bool
    {
        return $pelatihan->penggunaanKamar()->whereHas('penghuni')->exists();
    }

    /**
     * @param  array<string, mixed>  $atribut
     *
     * @throws ValidationException
     */
    private function tolakDuplikat(array $atribut): never
    {
        $judul = JudulPelatihan::find($atribut['judul_pelatihan_id'])?->judul;

        throw ValidationException::withMessages([
            'batch_ke' => "{$judul} {$atribut['tahun_anggaran']} Batch {$atribut['batch_ke']} sudah ada. Gunakan nomor batch lain.",
        ]);
    }
}
