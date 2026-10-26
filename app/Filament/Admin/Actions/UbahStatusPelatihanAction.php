<?php

namespace App\Filament\Admin\Actions;

use App\Enums\StatusPelatihan;
use App\Filament\Admin\Resources\Pendaftaran\PendaftaranResource;
use App\Models\Pelatihan;
use App\Services\PelatihanService;
use Filament\Actions\Action;
use Filament\Actions\Action as TombolNotifikasi;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Arr;
use Illuminate\Support\HtmlString;
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
            ->modalDescription(fn (Pelatihan $record): Htmlable => $this->keterangan($record))
            ->modalIcon(fn (Pelatihan $record): Heroicon => $this->adaMenungguSaatMulai($record) ? Heroicon::OutlinedExclamationTriangle : Heroicon::OutlinedArrowRightCircle)
            ->modalIconColor(fn (Pelatihan $record): string => $this->adaMenungguSaatMulai($record) ? 'warning' : 'primary')
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
                        ->actions(app(PelatihanService::class)->jumlahMenunggu($record) > 0 ? [
                            TombolNotifikasi::make('lihatMenunggu')
                                ->label('Lihat pendaftar yang menunggu')
                                ->url(PendaftaranResource::urlMenunggu($record))
                                ->button(),
                        ] : [])
                        ->persistent()
                        ->send();

                    return;
                }

                Notification::make()->title("Status pelatihan: {$tujuan->getLabel()}")->success()->send();
            });
    }

    /**
     * Teks konfirmasi: status lama → baru (DESIGN_SYSTEM §6.6). Saat memulai pelatihan,
     * peserta yang masih menunggu verifikasi diingatkan tanpa memblokir (PF-15).
     */
    private function keterangan(Pelatihan $pelatihan): Htmlable
    {
        $tujuan = $pelatihan->status->berikutnya();
        $teks = e("{$pelatihan->nama_tampilan}: {$pelatihan->status->getLabel()} → {$tujuan?->getLabel()}.");

        $teks .= match ($tujuan) {
            StatusPelatihan::Dibuka => ' Pelatihan akan muncul di form registrasi peserta.',
            StatusPelatihan::Berjalan => ' Form registrasi tetap menerima pendaftaran selama pelatihan berjalan.',
            StatusPelatihan::Selesai => ' Semua peserta terverifikasi akan otomatis berstatus Selesai, dan status tidak dapat dikembalikan.',
            default => '',
        };

        if ($this->adaMenungguSaatMulai($pelatihan)) {
            $jumlah = app(PelatihanService::class)->jumlahMenunggu($pelatihan);
            $teks .= sprintf(
                '<br><br><strong>Masih ada %d peserta yang menunggu verifikasi.</strong> Pelatihan tetap dapat dimulai, tetapi sebaiknya mereka diverifikasi atau dibatalkan terlebih dahulu. <a href="%s" class="underline">Lihat daftarnya</a>',
                $jumlah,
                e(PendaftaranResource::urlMenunggu($pelatihan)),
            );
        }

        return new HtmlString($teks);
    }

    private function adaMenungguSaatMulai(Pelatihan $pelatihan): bool
    {
        return $pelatihan->status->berikutnya() === StatusPelatihan::Berjalan
            && app(PelatihanService::class)->jumlahMenunggu($pelatihan) > 0;
    }
}
