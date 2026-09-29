<?php

namespace App\Services;

use App\Models\Concerns\BisaNonaktif;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Data master tidak dihapus permanen, hanya dinonaktifkan (CLAUDE.md aturan 2).
 */
class DataMasterService
{
    public function nonaktifkan(Model $model): void
    {
        $this->ubahStatusAktif($model, false);
    }

    public function aktifkan(Model $model): void
    {
        $this->ubahStatusAktif($model, true);
    }

    private function ubahStatusAktif(Model $model, bool $aktif): void
    {
        if (! in_array(BisaNonaktif::class, class_uses_recursive($model), true)) {
            throw new InvalidArgumentException($model::class.' bukan data master yang dapat dinonaktifkan.');
        }

        $model->forceFill(['is_aktif' => $aktif])->save();
    }
}
