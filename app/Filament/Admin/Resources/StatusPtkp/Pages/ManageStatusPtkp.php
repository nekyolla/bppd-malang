<?php

namespace App\Filament\Admin\Resources\StatusPtkp\Pages;

use App\Filament\Admin\Resources\StatusPtkp\StatusPtkpResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageStatusPtkp extends ManageRecords
{
    protected static string $resource = StatusPtkpResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
