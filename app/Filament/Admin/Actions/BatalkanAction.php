<?php

namespace App\Filament\Admin\Actions;

use App\Enums\StatusPendaftaran;
use App\Models\PelatihanPeserta;
use App\Services\PendaftaranService;
use App\Services\PenggantiService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

/**
 * Membatalkan peserta dengan alasan wajib (FR-BTL-01–02, DESIGN_SYSTEM §6.6).
 */
class BatalkanAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'batalkan';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Batalkan')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->modalHeading(fn (PelatihanPeserta $record): string => "Batalkan {$record->peserta->nama_lengkap}?")
            ->modalDescription(fn (PelatihanPeserta $record): string => 'Jatah kamar peserta akan dilepas. Presensi yang sudah tercatat tetap disimpan. '
                .(app(PenggantiService::class)->masihBisaDiganti($record)
                    ? 'Pengganti dari desa yang sama dapat ditetapkan saat verifikasi pendaftarannya.'
                    : 'Pelatihan sudah dimulai, sehingga peserta ini tidak dapat digantikan.'))
            ->modalSubmitActionLabel('Batalkan peserta')
            ->schema([
                Textarea::make('alasan_batal')
                    ->label('Alasan pembatalan')
                    ->required()
                    ->rows(3),
            ])
            ->authorize('batalkan')
            ->visible(fn (PelatihanPeserta $record): bool => $record->status->bisaMenjadi(StatusPendaftaran::Batal))
            ->action(function (PelatihanPeserta $record, array $data): void {
                try {
                    app(PendaftaranService::class)->batalkan($record, auth()->user(), $data['alasan_batal']);
                } catch (ValidationException $exception) {
                    Notification::make()
                        ->title('Peserta tidak dapat dibatalkan')
                        ->body(Arr::first(Arr::flatten($exception->errors())))
                        ->danger()
                        ->send();

                    $this->halt();
                }

                Notification::make()->title('Peserta dibatalkan')->success()->send();
            });
    }
}
