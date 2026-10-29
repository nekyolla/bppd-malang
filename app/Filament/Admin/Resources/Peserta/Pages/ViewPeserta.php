<?php

namespace App\Filament\Admin\Resources\Peserta\Pages;

use App\Filament\Admin\Actions\KembaliAction;
use App\Filament\Admin\Resources\Peserta\PesertaResource;
use App\Models\Peserta;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewPeserta extends ViewRecord
{
    protected static string $resource = PesertaResource::class;

    /**
     * Judul cukup nama; jabatan dan desa sebagai subjudul.
     */
    public function getTitle(): string|Htmlable
    {
        /** @var Peserta $peserta */
        $peserta = $this->getRecord();

        return $peserta->nama_lengkap;
    }

    public function getSubheading(): ?string
    {
        /** @var Peserta $peserta */
        $peserta = $this->getRecord();

        return collect([$peserta->jabatan?->nama_jabatan, $peserta->desa ? "Desa {$peserta->desa->nama}" : null])
            ->filter()
            ->implode(' · ') ?: null;
    }

    protected function getHeaderActions(): array
    {
        return [
            KembaliAction::ke(PesertaResource::getUrl('index'), 'Kembali ke daftar peserta'),
            EditAction::make()->label('Rapikan data'),
        ];
    }
}
