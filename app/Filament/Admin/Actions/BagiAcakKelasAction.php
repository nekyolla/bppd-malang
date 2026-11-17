<?php

namespace App\Filament\Admin\Actions;

use App\Enums\StatusPelatihan;
use App\Filament\Admin\Resources\Pelatihan\Pages\ManageKelas;
use App\Filament\Admin\Resources\Pelatihan\Pages\ManagePembagianKelas;
use App\Filament\Admin\Support\GalatService;
use App\Services\KelasService;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Bantuan "bagi acak otomatis" (FR-KLS-03). Dipakai di sub-halaman milik
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
            ->modalDescription('Peserta terverifikasi dibagi rata ke semua kelas. Masih bisa diubah satu per satu.')
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
            ->authorize(fn (ManageKelas|ManagePembagianKelas $livewire): bool => auth()->user()->can('update', $livewire->pelatihan()))
            ->visible(fn (ManageKelas|ManagePembagianKelas $livewire): bool => $livewire->pelatihan()->status !== StatusPelatihan::Selesai)
            ->action(function (ManageKelas|ManagePembagianKelas $livewire, array $data): void {
                $jumlah = GalatService::jalankan($this, 'Peserta belum dapat dibagi', fn () => app(KelasService::class)->bagiAcak($livewire->pelatihan(), $data['cakupan'] === 'semua'));

                $jumlah > 0
                    ? Notification::make()->title("{$jumlah} peserta dibagi ke kelas")->success()->send()
                    : Notification::make()->title('Tidak ada peserta yang perlu dibagi')->warning()->send();
            });
    }
}
