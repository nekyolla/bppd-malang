<?php

namespace App\Filament\Concerns;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Str;

/**
 * Breadcrumb halaman lihat cukup berakhir di judul record ("Peserta › Safina"),
 * tanpa remah "Lihat" bawaan Filament. Resource tanpa judul record memakai label modelnya.
 */
trait RemahTanpaLihat
{
    /**
     * @return array<string>
     */
    public function getBreadcrumbs(): array
    {
        $judul = static::getResource()::getRecordTitle($this->getRecord());

        return [
            ...$this->getResourceBreadcrumbs(),
            $judul instanceof Htmlable ? $judul : Str::ucfirst((string) $judul),
        ];
    }
}
