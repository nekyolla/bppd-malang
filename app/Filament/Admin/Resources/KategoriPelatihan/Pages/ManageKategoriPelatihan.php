<?php

namespace App\Filament\Admin\Resources\KategoriPelatihan\Pages;

use App\Filament\Admin\Resources\KategoriPelatihan\KategoriPelatihanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageKategoriPelatihan extends ManageRecords
{
    protected static string $resource = KategoriPelatihanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
