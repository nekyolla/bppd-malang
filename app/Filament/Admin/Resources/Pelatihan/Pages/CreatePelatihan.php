<?php

namespace App\Filament\Admin\Resources\Pelatihan\Pages;

use App\Filament\Admin\Concerns\MemetakanGalatService;
use App\Filament\Admin\Resources\Pelatihan\PelatihanResource;
use App\Services\PelatihanService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePelatihan extends CreateRecord
{
    use MemetakanGalatService;

    protected static string $resource = PelatihanResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return $this->jalankanService(fn () => app(PelatihanService::class)->simpan($data));
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
