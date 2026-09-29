<?php

namespace App\Filament\Admin\Actions;

use App\Services\DataMasterService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

class AktifkanAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'aktifkan';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Aktifkan')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->requiresConfirmation()
            ->modalDescription('Data akan kembali muncul di pilihan form baru.')
            ->modalSubmitActionLabel('Aktifkan')
            ->authorize('update')
            ->hidden(fn (Model $record): bool => $record->is_aktif)
            ->action(function (Model $record): void {
                app(DataMasterService::class)->aktifkan($record);

                Notification::make()->title('Data diaktifkan')->success()->send();
            });
    }
}
