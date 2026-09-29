<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;

/**
 * Data master tidak dihapus permanen, hanya dinonaktifkan (CLAUDE.md aturan 2).
 * Form baru memakai `Model::aktif()` agar data nonaktif tidak muncul di pilihan.
 */
trait BisaNonaktif
{
    protected function initializeBisaNonaktif(): void
    {
        $this->mergeCasts(['is_aktif' => 'boolean']);
    }

    #[Scope]
    protected function aktif(Builder $query): void
    {
        $query->where($this->qualifyColumn('is_aktif'), true);
    }
}
