<?php

namespace App\Filament\Admin\Actions;

use App\Enums\StatusPendaftaran;
use App\Filament\Admin\Support\GalatService;
use App\Models\PelatihanPeserta;
use App\Services\PendaftaranService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Menghapus pendaftaran tidak sah yang belum pernah diverifikasi, misal data iseng (PF-14).
 */
class HapusPendaftaranAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'hapus';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Hapus')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(fn (PelatihanPeserta $record): string => "Hapus pendaftaran {$record->peserta->nama_lengkap}?")
            ->modalDescription('Pendaftaran dan berkasnya (KTP, pas foto, surat tugas) dihapus permanen. Gunakan untuk data iseng atau ganda, bukan untuk peserta yang batal hadir.')
            ->modalSubmitActionLabel('Hapus permanen')
            ->authorize('hapus')
            ->visible(fn (PelatihanPeserta $record): bool => $record->diverifikasi_pada === null
                && in_array($record->status, [StatusPendaftaran::Terdaftar, StatusPendaftaran::Batal], true))
            ->action(function (PelatihanPeserta $record): void {
                GalatService::jalankan($this, 'Pendaftaran tidak dapat dihapus', fn () => app(PendaftaranService::class)->hapus($record));

                Notification::make()->title('Pendaftaran dihapus')->success()->send();
            });
    }
}
