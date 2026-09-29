<?php

namespace App\Filament\Admin\Widgets\Concerns;

use Filament\Widgets\Concerns\InteractsWithPageFilters;

/**
 * Membaca filter tahun dari halaman Dashboard (PF-11).
 */
trait MembacaFilterTahun
{
    use InteractsWithPageFilters;

    protected function tahun(): ?int
    {
        $tahun = $this->pageFilters['tahun'] ?? null;

        return filled($tahun) ? (int) $tahun : null;
    }
}
