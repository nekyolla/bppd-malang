<?php

namespace App\Filament\Admin\Resources\Peserta\Pages;

use App\Filament\Admin\Actions\KembaliAction;
use App\Filament\Admin\Resources\Peserta\PesertaResource;
use App\Filament\Concerns\MemetakanGalatService;
use App\Services\PesertaService;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * Merapikan data peserta sesuai KTP (FR-PST-02). Snapshot pelatihan yang sudah diambil
 * tidak berubah.
 */
class EditPeserta extends EditRecord
{
    use MemetakanGalatService;

    protected static string $resource = PesertaResource::class;

    public function getSubheading(): ?string
    {
        return 'Perubahan berlaku untuk pelatihan berikutnya; data saat pelatihan yang sudah diverifikasi tidak berubah.';
    }

    protected function getHeaderActions(): array
    {
        return [
            KembaliAction::ke(fn (): string => PesertaResource::getUrl('view', ['record' => $this->getRecord()]), 'Kembali ke detail'),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return $this->jalankanService(fn () => app(PesertaService::class)->ubah($record, $data));
    }

    protected function getRedirectUrl(): string
    {
        return PesertaResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
