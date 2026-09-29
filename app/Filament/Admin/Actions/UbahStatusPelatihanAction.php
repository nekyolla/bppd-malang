<?php

namespace App\Filament\Admin\Actions;

use App\Enums\StatusPelatihan;
use App\Models\Pelatihan;
use App\Services\PelatihanService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

/**
 * Satu tombol untuk memindahkan pelatihan ke status berikutnya:
 * Buka pendaftaran → Mulai pelatihan → Selesaikan pelatihan.
 */
class UbahStatusPelatihanAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'ubahStatus';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(fn (Pelatihan $record): string => $record->status->berikutnya()?->labelAksi() ?? 'Ubah status')
            ->icon(Heroicon::OutlinedArrowRightCircle)
            ->color('primary')
            ->requiresConfirmation()
            ->modalHeading(fn (Pelatihan $record): string => $record->status->berikutnya()?->labelAksi() ?? 'Ubah status')
            ->modalDescription(fn (Pelatihan $record): string => $this->keterangan($record))
            ->modalSubmitActionLabel('Ya, lanjutkan')
            ->authorize('update')
            ->visible(fn (Pelatihan $record): bool => $record->status->berikutnya() !== null)
            ->action(function (Pelatihan $record): void {
                $tujuan = $record->status->berikutnya();

                try {
                    app(PelatihanService::class)->ubahStatus($record, $tujuan);
                } catch (ValidationException $exception) {
                    Notification::make()
                        ->title('Status tidak dapat diubah')
                        ->body(Arr::first(Arr::flatten($exception->errors())))
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()->title("Status pelatihan: {$tujuan->getLabel()}")->success()->send();
            });
    }

    private function keterangan(Pelatihan $pelatihan): string
    {
        $tujuan = $pelatihan->status->berikutnya();
        $teks = "{$pelatihan->nama_tampilan}: {$pelatihan->status->getLabel()} → {$tujuan?->getLabel()}.";

        return match ($tujuan) {
            StatusPelatihan::Dibuka => $teks.' Pelatihan akan muncul di form registrasi peserta.',
            StatusPelatihan::Selesai => $teks.' Semua peserta terverifikasi akan otomatis berstatus Selesai, dan status tidak dapat dikembalikan.',
            default => $teks,
        };
    }
}
