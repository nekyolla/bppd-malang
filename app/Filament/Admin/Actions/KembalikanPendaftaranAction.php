<?php

namespace App\Filament\Admin\Actions;

use App\Enums\StatusPelatihan;
use App\Enums\StatusPendaftaran;
use App\Models\PelatihanPeserta;
use App\Services\PendaftaranService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

/**
 * Mengembalikan pendaftaran yang salah dibatalkan (PF-14).
 */
class KembalikanPendaftaranAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'kembalikan';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Kembalikan')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading(fn (PelatihanPeserta $record): string => "Kembalikan {$record->peserta->nama_lengkap}?")
            ->modalDescription(fn (PelatihanPeserta $record): string => 'Status kembali menjadi "'
                .($record->diverifikasi_pada ? StatusPendaftaran::Terverifikasi : StatusPendaftaran::Terdaftar)->getLabel()
                .'". Kelas tetap, tetapi kamar yang sudah dilepas perlu ditempatkan ulang.')
            ->modalSubmitActionLabel('Kembalikan')
            ->authorize('kembalikan')
            ->visible(fn (PelatihanPeserta $record): bool => $record->status === StatusPendaftaran::Batal
                && $record->pelatihan->status !== StatusPelatihan::Selesai
                && $record->pengganti === null)
            ->action(function (PelatihanPeserta $record): void {
                try {
                    $status = app(PendaftaranService::class)->kembalikan($record);
                } catch (ValidationException $exception) {
                    Notification::make()
                        ->title('Pembatalan tidak dapat dikembalikan')
                        ->body(Arr::first(Arr::flatten($exception->errors())))
                        ->danger()
                        ->send();

                    $this->halt();
                }

                Notification::make()->title("Pendaftaran dikembalikan: {$status->getLabel()}")->success()->send();
            });
    }
}
