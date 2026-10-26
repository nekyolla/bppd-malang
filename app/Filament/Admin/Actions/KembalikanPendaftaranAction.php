<?php

namespace App\Filament\Admin\Actions;

use App\Enums\StatusPelatihan;
use App\Enums\StatusPendaftaran;
use App\Filament\Admin\Support\GalatService;
use App\Models\PelatihanPeserta;
use App\Services\PendaftaranService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

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
                $status = GalatService::jalankan($this, 'Pembatalan tidak dapat dikembalikan', fn () => app(PendaftaranService::class)->kembalikan($record));

                Notification::make()->title("Pendaftaran dikembalikan: {$status->getLabel()}")->success()->send();
            });
    }
}
