<?php

namespace App\Filament\Admin\Resources\Akun\Pages;

use App\Filament\Admin\Resources\Akun\AkunResource;
use App\Services\AkunService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageAkun extends ManageRecords
{
    protected static string $resource = AkunResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->using(fn (array $data, Action $action) => AkunResource::jalankan($action, fn () => app(AkunService::class)->buat($data))),
        ];
    }
}
