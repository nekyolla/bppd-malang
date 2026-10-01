<?php

namespace App\Filament\Admin\Actions;

use App\Models\KelasPelatihan;
use App\Services\KelasService;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;

/**
 * Menetapkan kelas beberapa peserta sekaligus. Dipakai di relation manager milik
 * Pelatihan, sehingga pilihan kelas terbatas pada pelatihan tersebut (FR-KLS-04).
 */
class AturKelasMassalAction extends BulkAction
{
    public static function getDefaultName(): ?string
    {
        return 'aturKelasMassal';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Atur kelas terpilih')
            ->icon(Heroicon::OutlinedRectangleGroup)
            ->color('gray')
            ->modalHeading('Atur kelas peserta terpilih')
            ->modalSubmitActionLabel('Simpan')
            ->modalWidth('md')
            ->schema([
                Select::make('kelas_pelatihan_id')
                    ->label('Kelas')
                    ->options(fn (RelationManager $livewire): array => AturKelasAction::pilihanKelas($livewire->getOwnerRecord()->getKey()))
                    ->required(),
            ])
            ->authorizeIndividualRecords('aturKelas')
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records, array $data): void {
                $kelas = KelasPelatihan::query()->findOrFail($data['kelas_pelatihan_id']);
                $hasil = app(KelasService::class)->tetapkanMassal($records, $kelas);

                Notification::make()
                    ->title("{$hasil['ditetapkan']} peserta masuk kelas {$kelas->nama_kelas}")
                    ->body($hasil['dilewati'] > 0 ? "{$hasil['dilewati']} dilewati karena tidak berstatus terverifikasi." : null)
                    ->success()
                    ->send();
            });
    }
}
