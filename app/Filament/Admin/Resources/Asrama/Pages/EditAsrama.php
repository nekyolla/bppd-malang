<?php

namespace App\Filament\Admin\Resources\Asrama\Pages;

use App\Filament\Admin\Actions\AktifkanAction;
use App\Filament\Admin\Actions\NonaktifkanAction;
use App\Filament\Admin\Resources\Asrama\AsramaResource;
use Filament\Resources\Pages\EditRecord;

class EditAsrama extends EditRecord
{
    protected static string $resource = AsramaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            NonaktifkanAction::make()->after(fn () => $this->refreshFormData(['is_aktif'])),
            AktifkanAction::make()->after(fn () => $this->refreshFormData(['is_aktif'])),
        ];
    }
}
