<?php

namespace App\Filament\Admin\Resources\SumberDana\Pages;

use App\Filament\Admin\Resources\SumberDana\SumberDanaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSumberDana extends ManageRecords
{
    protected static string $resource = SumberDanaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
