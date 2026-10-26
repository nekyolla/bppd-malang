<?php

namespace App\Filament\Admin\Resources\Peserta\Pages;

use App\Filament\Admin\Actions\KembaliAction;
use App\Filament\Admin\Resources\Peserta\PesertaResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPeserta extends ViewRecord
{
    protected static string $resource = PesertaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            KembaliAction::ke(PesertaResource::getUrl('index'), 'Kembali ke daftar peserta'),
            EditAction::make()->label('Rapikan data'),
        ];
    }
}
