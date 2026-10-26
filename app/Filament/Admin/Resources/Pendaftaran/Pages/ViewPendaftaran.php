<?php

namespace App\Filament\Admin\Resources\Pendaftaran\Pages;

use App\Filament\Admin\Actions\AturKelasAction;
use App\Filament\Admin\Actions\BatalkanAction;
use App\Filament\Admin\Actions\HapusPendaftaranAction;
use App\Filament\Admin\Actions\KembaliAction;
use App\Filament\Admin\Actions\KembalikanPendaftaranAction;
use App\Filament\Admin\Actions\VerifikasiAction;
use App\Filament\Admin\Resources\Pelatihan\Pages\ManagePendaftar;
use App\Filament\Admin\Resources\Pendaftaran\PendaftaranResource;
use Filament\Resources\Pages\ViewRecord;

class ViewPendaftaran extends ViewRecord
{
    protected static string $resource = PendaftaranResource::class;

    protected function getHeaderActions(): array
    {
        $pendaftar = fn (): string => ManagePendaftar::getUrl(['record' => $this->getRecord()->pelatihan_id]);

        return [
            KembaliAction::ke($pendaftar, 'Kembali ke pendaftar'),
            VerifikasiAction::make(),
            AturKelasAction::make(),
            KembalikanPendaftaranAction::make(),
            BatalkanAction::make(),
            HapusPendaftaranAction::make()->after(fn () => $this->redirect($pendaftar())),
        ];
    }
}
