<?php

namespace App\Filament\Admin\Actions;

use App\Enums\StatusPelatihan;
use App\Services\KelasService;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

/**
 * Bantuan "bagi acak otomatis" (FR-KLS-03). Dipakai di relation manager milik
 * Pelatihan; pelatihannya diambil dari owner record.
 */
class BagiAcakKelasAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'bagiAcak';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Bagi acak')
            ->icon(Heroicon::OutlinedArrowsRightLeft)
            ->color('gray')
            ->modalHeading('Bagi peserta ke kelas secara acak')
            ->modalDescription('Peserta terverifikasi dibagi rata ke semua kelas. Hasilnya tetap dapat diubah satu per satu.')
            ->modalSubmitActionLabel('Bagi acak')
            ->modalWidth('lg')
            ->schema([
                Radio::make('cakupan')
                    ->label('Peserta yang dibagi')
                    ->options([
                        'belum' => 'Hanya yang belum punya kelas',
                        'semua' => 'Semua peserta terverifikasi',
                    ])
                    ->descriptions([
                        'belum' => 'Peserta yang sudah punya kelas tidak dipindah.',
                        'semua' => 'Pembagian sebelumnya diganti dengan pembagian baru.',
                    ])
                    ->default('belum')
                    ->required(),
            ])
            ->authorize(fn (RelationManager $livewire): bool => auth()->user()->can('update', $livewire->getOwnerRecord()))
            ->visible(fn (RelationManager $livewire): bool => $livewire->getOwnerRecord()->status !== StatusPelatihan::Selesai)
            ->action(function (RelationManager $livewire, array $data): void {
                try {
                    $jumlah = app(KelasService::class)->bagiAcak($livewire->getOwnerRecord(), $data['cakupan'] === 'semua');
                } catch (ValidationException $exception) {
                    Notification::make()
                        ->title('Peserta belum dapat dibagi')
                        ->body(Arr::first(Arr::flatten($exception->errors())))
                        ->danger()
                        ->send();

                    $this->halt();
                }

                $jumlah > 0
                    ? Notification::make()->title("{$jumlah} peserta dibagi ke kelas")->success()->send()
                    : Notification::make()->title('Tidak ada peserta yang perlu dibagi')->body('Belum ada peserta terverifikasi tanpa kelas di pelatihan ini.')->warning()->send();
            });
    }
}
