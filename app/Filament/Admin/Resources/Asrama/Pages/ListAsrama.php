<?php

namespace App\Filament\Admin\Resources\Asrama\Pages;

use App\Filament\Admin\Resources\Asrama\AsramaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAsrama extends ListRecords
{
    protected static string $resource = AsramaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
