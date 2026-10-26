<?php

namespace App\Services;

use App\Models\Peserta;
use App\Support\IsianPeserta;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Perapian data peserta oleh admin di luar verifikasi (FR-PST-02). Snapshot
 * `*_saat_pelatihan` di pendaftaran tidak ikut berubah (CLAUDE.md aturan 6).
 */
class PesertaService
{
    /**
     * @param  array<string, mixed>  $data  Isian dengan kolom IsianPeserta::KOLOM.
     *
     * @throws ValidationException
     */
    public function ubah(Peserta $peserta, array $data): Peserta
    {
        $data = IsianPeserta::normalisasi($data);

        Validator::make($data, IsianPeserta::aturan(masterHarusAktif: false, nikUnikKecuali: $peserta), IsianPeserta::pesan(), IsianPeserta::LABEL)
            ->validate();

        $peserta->fill(Arr::only($data, IsianPeserta::KOLOM))->save();

        return $peserta;
    }
}
