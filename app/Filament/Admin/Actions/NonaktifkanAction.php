<?php

namespace App\Filament\Admin\Actions;

use App\Services\DataMasterService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

/**
 * Pengganti aksi hapus untuk data master (DESIGN_SYSTEM §6.6).
 */
class NonaktifkanAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'nonaktifkan';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Nonaktifkan')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription('Data tetap tampil di riwayat, tetapi tidak muncul di pilihan form baru.')
            ->modalSubmitActionLabel('Nonaktifkan')
            ->authorize('update')
            ->visible(fn (Model $record): bool => $record->is_aktif)
            ->action(function (Model $record): void {
                app(DataMasterService::class)->nonaktifkan($record);

                Notification::make()->title('Data dinonaktifkan')->success()->send();
            });
    }
}
