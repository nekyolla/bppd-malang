<?php

namespace App\Filament\Admin\Resources\Pelatihan\Pages;

use App\Filament\Admin\Actions\UbahStatusPelatihanAction;
use App\Filament\Admin\Concerns\MemetakanGalatService;
use App\Filament\Admin\Resources\Pelatihan\PelatihanResource;
use App\Services\PelatihanService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditPelatihan extends EditRecord
{
    use MemetakanGalatService;

    protected static string $resource = PelatihanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            UbahStatusPelatihanAction::make()
                ->after(fn () => $this->refreshFormData(['status'])),
            DeleteAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        // Tipe lokasi yang dikunci (disabled) tidak ikut terkirim; pakai nilai lama.
        $data['tipe_lokasi'] ??= $record->tipe_lokasi;

        return $this->jalankanService(fn () => app(PelatihanService::class)->simpan($data, $record));
    }
}
