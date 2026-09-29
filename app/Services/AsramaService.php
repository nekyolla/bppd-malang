<?php

namespace App\Services;

use App\Models\AsramaPeserta;
use App\Models\KamarAsrama;
use App\Models\PenggunaanKamar;
use Illuminate\Support\Facades\DB;

/**
 * Penempatan peserta di kamar asrama (ARCHITECTURE §7.3). Penempatan dibuat di M6;
 * saat ini baru pelepasan kamar yang dipakai pembatalan.
 */
class AsramaService
{
    /**
     * Mengeluarkan peserta dari kamar. Jika penghuni terakhir keluar, pemakaian
     * kamar dihapus sehingga kamar kembali netral (FR-ASR-09).
     */
    public function keluarkan(AsramaPeserta $penempatan): void
    {
        DB::transaction(function () use ($penempatan): void {
            $penggunaan = PenggunaanKamar::query()->findOrFail($penempatan->penggunaan_kamar_id);

            // Kunci baris kamar yang sama dengan penempatan (CLAUDE.md aturan 4).
            KamarAsrama::query()->lockForUpdate()->findOrFail($penggunaan->kamar_asrama_id);

            $penempatan->delete();

            if ($penggunaan->penghuni()->doesntExist()) {
                $penggunaan->delete();
            }
        });
    }
}
