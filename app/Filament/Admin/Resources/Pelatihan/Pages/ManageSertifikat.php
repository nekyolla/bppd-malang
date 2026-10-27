<?php

namespace App\Filament\Admin\Resources\Pelatihan\Pages;

use App\Enums\StatusPendaftaran;
use App\Filament\Admin\Actions\KembaliAction;
use App\Filament\Admin\Resources\Pelatihan\PelatihanResource;
use App\Filament\Admin\Support\GalatService;
use App\Models\PelatihanPeserta;
use App\Models\SertifikatPeserta;
use App\Services\RegistrasiService;
use App\Services\SertifikatService;
use App\Support\FormatTanggal;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Sertifikat PDF per peserta (PF-27). Sertifikat dibuat manual di luar sistem; admin
 * mengunggah, mengganti, atau menghapusnya lewat SertifikatService.
 */
class ManageSertifikat extends ManageRelatedRecords
{
    protected static string $resource = PelatihanResource::class;

    protected static string $relationship = 'pendaftaran';

    protected static ?string $navigationLabel = 'Sertifikat';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCheck;

    protected static ?string $breadcrumb = 'Sertifikat';

    protected static ?string $modelLabel = 'peserta';

    protected static ?string $pluralModelLabel = 'peserta';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->whereIn('status', [StatusPendaftaran::Terverifikasi, StatusPendaftaran::Selesai])
                ->with(['peserta', 'kelasPelatihan', 'desaSaatPelatihan.kecamatan.kabKota', 'sertifikat']))
            ->columns([
                TextColumn::make('no')->label('No')->rowIndex(),
                TextColumn::make('peserta.nama_lengkap')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('desaSaatPelatihan.nama')
                    ->label('Desa')
                    ->description(fn (PelatihanPeserta $record): ?string => $record->desaSaatPelatihan?->kecamatan->kabKota->nama),
                TextColumn::make('kelasPelatihan.nama_kelas')
                    ->label('Kelas')
                    ->badge()
                    ->placeholder('–'),
                TextColumn::make('sertifikat')
                    ->label('Sertifikat')
                    ->state(fn (PelatihanPeserta $record): string => $record->sertifikat ? 'Sudah diunggah' : 'Belum')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Belum' ? 'gray' : 'success')
                    ->icon(fn (string $state): Heroicon => $state === 'Belum' ? Heroicon::OutlinedMinus : Heroicon::OutlinedDocumentCheck)
                    ->description(fn (PelatihanPeserta $record): ?string => $record->sertifikat ? FormatTanggal::tanggal($record->sertifikat->updated_at) : null),
            ])
            ->defaultSort('peserta.nama_lengkap')
            ->filters([
                TernaryFilter::make('sudah')
                    ->label('Sertifikat')
                    ->trueLabel('Sudah diunggah')
                    ->falseLabel('Belum')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereHas('sertifikat'),
                        false: fn (Builder $query): Builder => $query->whereDoesntHave('sertifikat'),
                    ),
            ])
            ->recordActions([
                $this->aksiUnggah('unggah', 'Unggah')
                    ->visible(fn (PelatihanPeserta $record): bool => $record->sertifikat === null),
                $this->aksiUnggah('ganti', 'Ganti')
                    ->visible(fn (PelatihanPeserta $record): bool => $record->sertifikat !== null),
                Action::make('lihat')
                    ->label('Lihat')
                    ->icon(Heroicon::OutlinedEye)
                    ->url(fn (PelatihanPeserta $record): string => route('berkas.sertifikat', $record))
                    ->openUrlInNewTab()
                    ->visible(fn (PelatihanPeserta $record): bool => $record->sertifikat !== null),
                Action::make('hapus')
                    ->label('Hapus')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Hapus sertifikat?')
                    ->modalDescription(fn (PelatihanPeserta $record): string => "Berkas sertifikat {$record->peserta->nama_lengkap} akan dihapus permanen.")
                    ->visible(fn (PelatihanPeserta $record): bool => $record->sertifikat !== null)
                    ->authorize(fn (PelatihanPeserta $record): bool => auth()->user()->can('delete', $record->sertifikat))
                    ->action(fn (PelatihanPeserta $record) => app(SertifikatService::class)->hapus($record->sertifikat)),
            ])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading('Belum ada peserta terverifikasi')
            ->emptyStateDescription('Sertifikat hanya dapat diunggah untuk peserta terverifikasi atau selesai.');
    }

    private function aksiUnggah(string $nama, string $label): Action
    {
        return Action::make($nama)
            ->label($label)
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->modalHeading(fn (PelatihanPeserta $record): string => "{$label} sertifikat {$record->peserta->nama_lengkap}")
            ->modalSubmitActionLabel($label)
            ->schema([
                FileUpload::make('berkas')
                    ->label('Berkas sertifikat (PDF)')
                    ->helperText('Sertifikat dibuat di luar sistem. Unggah berkas PDF-nya di sini.')
                    ->acceptedFileTypes(['application/pdf'])
                    ->maxSize(RegistrasiService::MAKS_UKURAN_BERKAS_KB)
                    ->storeFiles(false)
                    ->required(),
            ])
            ->authorize(fn (): bool => auth()->user()->can('create', SertifikatPeserta::class))
            ->action(fn (Action $action, PelatihanPeserta $record, array $data) => GalatService::jalankan(
                $action,
                'Sertifikat tidak dapat diunggah',
                fn () => app(SertifikatService::class)->unggah($record, $data['berkas'], auth()->user()),
            ));
    }

    public function getTitle(): string|Htmlable
    {
        return "Sertifikat {$this->getOwnerRecord()->nama_tampilan}";
    }

    public function getSubheading(): string|Htmlable|null
    {
        $peserta = $this->getOwnerRecord()->pendaftaran()
            ->whereIn('status', [StatusPendaftaran::Terverifikasi, StatusPendaftaran::Selesai]);
        $jumlah = $peserta->count();

        return $jumlah === 0 ? null : (clone $peserta)->whereHas('sertifikat')->count()." dari {$jumlah} peserta sudah bersertifikat";
    }

    protected function getHeaderActions(): array
    {
        return [
            KembaliAction::ke(PelatihanResource::getUrl('index'), 'Kembali ke daftar pelatihan'),
        ];
    }
}
