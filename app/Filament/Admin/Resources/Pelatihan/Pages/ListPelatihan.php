<?php

namespace App\Filament\Admin\Resources\Pelatihan\Pages;

use App\Filament\Admin\Resources\Pelatihan\PelatihanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPelatihan extends ListRecords
{
    protected static string $resource = PelatihanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
