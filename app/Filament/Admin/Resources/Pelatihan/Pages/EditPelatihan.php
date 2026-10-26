<?php

namespace App\Filament\Admin\Resources\Pelatihan\Pages;

use App\Filament\Admin\Actions\KembaliAction;
use App\Filament\Admin\Actions\UbahStatusPelatihanAction;
use App\Filament\Admin\Resources\Pelatihan\PelatihanResource;
use App\Filament\Concerns\MemetakanGalatService;
use App\Services\PelatihanService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

class EditPelatihan extends EditRecord
{
    use MemetakanGalatService;

    protected static string $resource = PelatihanResource::class;

    protected static ?string $navigationLabel = 'Data pelatihan';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected function getHeaderActions(): array
    {
        return [
            KembaliAction::ke(PelatihanResource::getUrl('index'), 'Kembali ke daftar pelatihan'),
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
