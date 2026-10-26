<?php

namespace App\Filament\Admin\Resources\Pelatihan\Pages;

use App\Enums\TipeLokasi;
use App\Filament\Admin\Actions\UbahStatusPelatihanAction;
use App\Filament\Admin\Pages\PenempatanKamar;
use App\Filament\Admin\Resources\Pelatihan\PelatihanResource;
use App\Filament\Concerns\MemetakanGalatService;
use App\Services\PelatihanService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

class EditPelatihan extends EditRecord
{
    use MemetakanGalatService;

    protected static string $resource = PelatihanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('penempatanKamar')
                ->label('Penempatan kamar')
                ->icon(Heroicon::OutlinedHomeModern)
                ->color('gray')
                ->url(fn (): string => PenempatanKamar::getUrl(['pelatihan' => $this->getRecord()->getKey()]))
                ->visible(fn (): bool => $this->getRecord()->tipe_lokasi === TipeLokasi::Bbpd && PenempatanKamar::canAccess()),
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
