<?php

namespace App\Filament\Admin\Resources\Pendaftaran\Pages;

use App\Filament\Admin\Actions\AturKelasAction;
use App\Filament\Admin\Actions\BatalkanAction;
use App\Filament\Admin\Actions\VerifikasiAction;
use App\Filament\Admin\Resources\Pendaftaran\PendaftaranResource;
use Filament\Resources\Pages\ViewRecord;

class ViewPendaftaran extends ViewRecord
{
    protected static string $resource = PendaftaranResource::class;

    protected function getHeaderActions(): array
    {
        return [
            VerifikasiAction::make(),
            AturKelasAction::make(),
            BatalkanAction::make(),
        ];
    }
}
