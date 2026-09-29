<?php

namespace App\Filament\Admin\Actions;

use App\Services\PendaftaranService;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;

class VerifikasiMassalAction extends BulkAction
{
    public static function getDefaultName(): ?string
    {
        return 'verifikasiMassal';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Verifikasi terpilih')
            ->icon(Heroicon::OutlinedCheckBadge)
            ->color('info')
            ->requiresConfirmation()
            ->modalDescription('Pendaftaran yang isiannya berbeda dengan data peserta sebelumnya akan dilewati agar diperiksa satu per satu.')
            ->modalSubmitActionLabel('Verifikasi')
            ->authorizeIndividualRecords('verifikasi')
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records): void {
                $hasil = app(PendaftaranService::class)->verifikasiMassal($records->load('peserta'), auth()->user());

                Notification::make()
                    ->title("{$hasil['terverifikasi']} pendaftaran terverifikasi")
                    ->body($hasil['dilewati'] > 0 ? "{$hasil['dilewati']} dilewati: sudah diproses atau isiannya perlu diperiksa satu per satu." : null)
                    ->success()
                    ->send();
            });
    }
}
