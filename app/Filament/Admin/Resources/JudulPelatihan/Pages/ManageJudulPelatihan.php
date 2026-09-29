<?php

namespace App\Filament\Admin\Resources\JudulPelatihan\Pages;

use App\Filament\Admin\Resources\JudulPelatihan\JudulPelatihanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageJudulPelatihan extends ManageRecords
{
    protected static string $resource = JudulPelatihanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
