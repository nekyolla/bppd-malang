<?php

namespace App\Filament\Admin\Resources\Pelatihan\Pages;

use App\Enums\StatusPendaftaran;
use App\Filament\Admin\Resources\Pelatihan\Pages\Concerns\HalamanKerjaPelatihan;
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
use Filament\Forms\Components\TextInput;
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
    use HalamanKerjaPelatihan;

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
                    ->description(fn (PelatihanPeserta $record): ?string => $record->sertifikat
                        ? collect([
                            filled($record->sertifikat->nomor_sertifikat) ? "No. {$record->sertifikat->nomor_sertifikat}" : null,
                            FormatTanggal::tanggal($record->sertifikat->updated_at),
                        ])->filter()->implode(' · ')
                        : null),
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
                    ->visible(fn (PelatihanPeserta $record): bool => $record->sertifikat === null)
                    ->authorize(fn (): bool => auth()->user()->can('create', SertifikatPeserta::class)),
                $this->aksiUnggah('ganti', 'Ganti')
                    ->visible(fn (PelatihanPeserta $record): bool => $record->sertifikat !== null)
                    ->authorize(fn (PelatihanPeserta $record): bool => $record->sertifikat !== null && auth()->user()->can('update', $record->sertifikat)),
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
            ->emptyStateDescription('Sertifikat hanya untuk peserta terverifikasi atau selesai.');
    }

    /**
     * Unggah: berkas wajib. Ganti: berkas boleh kosong bila hanya mengubah nomor (PF-37).
     */
    private function aksiUnggah(string $nama, string $label): Action
    {
        $ganti = $nama === 'ganti';

        return Action::make($nama)
            ->label($label)
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->modalHeading(fn (PelatihanPeserta $record): string => "{$label} sertifikat {$record->peserta->nama_lengkap}")
            ->modalSubmitActionLabel($ganti ? 'Simpan' : $label)
            ->fillForm(fn (PelatihanPeserta $record): array => ['nomor_sertifikat' => $record->sertifikat?->nomor_sertifikat])
            ->schema([
                TextInput::make('nomor_sertifikat')
                    ->label('Nomor sertifikat')
                    ->helperText('Opsional. Tampil di export data peserta.')
                    ->maxLength(100),
                FileUpload::make('berkas')
                    ->label($ganti ? 'Berkas sertifikat baru (PDF)' : 'Berkas sertifikat (PDF)')
                    ->helperText($ganti ? 'Kosongkan bila hanya mengubah nomor. PDF, maksimal 2 MB.' : 'PDF, maksimal 2 MB.')
                    ->acceptedFileTypes(['application/pdf'])
                    ->maxSize(RegistrasiService::MAKS_UKURAN_BERKAS_KB)
                    ->storeFiles(false)
                    ->required(! $ganti),
            ])
            ->action(fn (Action $action, PelatihanPeserta $record, array $data) => GalatService::jalankan(
                $action,
                'Sertifikat tidak dapat disimpan',
                function () use ($record, $data): void {
                    $service = app(SertifikatService::class);
                    $nomor = $data['nomor_sertifikat'] ?? null;
                    $sertifikat = filled($data['berkas'] ?? null)
                        ? $service->unggah($record, $data['berkas'], auth()->user(), $nomor)
                        : $record->sertifikat;

                    // Isian nomor di modal yang berlaku, termasuk bila dikosongkan saat ganti berkas.
                    $service->ubahNomor($sertifikat, $nomor);
                },
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
}
