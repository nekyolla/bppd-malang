<?php

namespace App\Filament\Admin\Actions;

use App\Enums\StatusPendaftaran;
use App\Models\KelasPelatihan;
use App\Models\PelatihanPeserta;
use App\Services\KelasService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

/**
 * Menetapkan atau memindahkan kelas satu peserta terverifikasi (FR-KLS-02).
 * Pilihan hanya kelas di pelatihan yang sama dengan pendaftarannya (FR-KLS-04).
 */
class AturKelasAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'aturKelas';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Atur kelas')
            ->icon(Heroicon::OutlinedRectangleGroup)
            ->color('gray')
            ->modalHeading(fn (PelatihanPeserta $record): string => "Kelas {$record->peserta->nama_lengkap}")
            ->modalSubmitActionLabel('Simpan')
            ->modalWidth('md')
            ->fillForm(fn (PelatihanPeserta $record): array => ['kelas_pelatihan_id' => $record->kelas_pelatihan_id])
            ->schema(fn (PelatihanPeserta $record): array => [
                Select::make('kelas_pelatihan_id')
                    ->label('Kelas')
                    ->options(self::pilihanKelas($record->pelatihan_id))
                    ->placeholder('Belum dibagi')
                    ->helperText(self::pilihanKelas($record->pelatihan_id) === []
                        ? 'Belum ada kelas di pelatihan ini. Buat kelas terlebih dahulu di halaman pelatihan.'
                        : 'Kosongkan untuk melepas peserta dari kelas.'),
            ])
            ->authorize('aturKelas')
            ->visible(fn (PelatihanPeserta $record): bool => $record->status === StatusPendaftaran::Terverifikasi)
            ->action(function (PelatihanPeserta $record, array $data): void {
                $kelas = filled($data['kelas_pelatihan_id'] ?? null) ? KelasPelatihan::query()->findOrFail($data['kelas_pelatihan_id']) : null;

                try {
                    app(KelasService::class)->tetapkan($record, $kelas);
                } catch (ValidationException $exception) {
                    Notification::make()
                        ->title('Kelas tidak dapat ditetapkan')
                        ->body(Arr::first(Arr::flatten($exception->errors())))
                        ->danger()
                        ->send();

                    $this->halt();
                }

                Notification::make()
                    ->title($kelas ? "{$record->peserta->nama_lengkap} masuk kelas {$kelas->nama_kelas}" : "{$record->peserta->nama_lengkap} dilepas dari kelas")
                    ->success()
                    ->send();
            });
    }

    /**
     * @return array<int, string>
     */
    public static function pilihanKelas(int $pelatihanId): array
    {
        return KelasPelatihan::query()
            ->where('pelatihan_id', $pelatihanId)
            ->orderBy('nama_kelas')
            ->pluck('nama_kelas', 'id')
            ->all();
    }
}
