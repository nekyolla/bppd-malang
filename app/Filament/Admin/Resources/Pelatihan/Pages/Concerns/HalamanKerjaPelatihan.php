<?php

namespace App\Filament\Admin\Resources\Pelatihan\Pages\Concerns;

use App\Filament\Admin\Resources\Pendaftaran\PendaftaranResource;

/**
 * Sub-halaman kerja per pelatihan (pendaftar, kelas, pembagian kelas, sertifikat) dibuka
 * dari menu Pendaftaran, jadi breadcrumb-nya berawal dari sana, bukan dari menu Pelatihan (PF-38).
 */
trait HalamanKerjaPelatihan
{
    /**
     * @return array<string>
     */
    public function getBreadcrumbs(): array
    {
        return [PendaftaranResource::getUrl('index') => 'Pendaftaran', ...array_slice(parent::getBreadcrumbs(), 1)];
    }
}
