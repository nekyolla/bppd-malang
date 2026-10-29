<?php

namespace App\Filament\Admin\Resources\Pelatihan\Pages;

use App\Enums\StatusPendaftaran;
use App\Filament\Admin\Actions\AturKelasAction;
use App\Filament\Admin\Actions\AturKelasMassalAction;
use App\Filament\Admin\Actions\BagiAcakKelasAction;
use App\Filament\Admin\Actions\KembaliAction;
use App\Filament\Admin\Resources\Pelatihan\PelatihanResource;
use App\Models\PelatihanPeserta;
use App\Support\Masking;
use BackedEnum;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Pembagian peserta terverifikasi ke kelas (FR-KLS-02–04). Kelas hanya berubah
 * lewat aksi yang memanggil KelasService.
 */
class ManagePembagianKelas extends ManageRelatedRecords
{
    protected static string $resource = PelatihanResource::class;

    protected static string $relationship = 'pendaftaran';

    protected static ?string $navigationLabel = 'Pembagian Kelas';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static ?string $breadcrumb = 'Pembagian Kelas';

    protected static ?string $modelLabel = 'peserta';

    protected static ?string $pluralModelLabel = 'peserta';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->whereIn('status', [StatusPendaftaran::Terverifikasi, StatusPendaftaran::Selesai])
                ->with(['peserta', 'kelasPelatihan', 'desaSaatPelatihan.kecamatan.kabKota']))
            ->columns([
                TextColumn::make('peserta.nama_lengkap')
                    ->label('Nama')
                    ->description(fn (PelatihanPeserta $record): string => Masking::nik($record->peserta->nik))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas('peserta', fn (Builder $q) => $q
                        ->where('nama_lengkap', 'like', "%{$search}%")
                        ->orWhere('nik', preg_replace('/\D/', '', $search) ?: $search)))
                    ->sortable(),
                TextColumn::make('peserta.jenis_kelamin')
                    ->label('Jenis kelamin'),
                TextColumn::make('desaSaatPelatihan.nama')
                    ->label('Desa')
                    ->description(fn (PelatihanPeserta $record): ?string => $record->desaSaatPelatihan?->kecamatan->kabKota->nama),
                TextColumn::make('kelasPelatihan.nama_kelas')
                    ->label('Kelas')
                    ->badge()
                    ->placeholder('Belum dibagi')
                    ->sortable(),
            ])
            ->defaultSort('peserta.nama_lengkap')
            ->filters([
                SelectFilter::make('kelas_pelatihan_id')
                    ->label('Kelas')
                    ->options(fn (): array => AturKelasAction::pilihanKelas($this->getOwnerRecord()->getKey())),
                Filter::make('belum_dibagi')
                    ->label('Belum dibagi')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->whereNull('kelas_pelatihan_id')),
            ])
            ->headerActions([
                BagiAcakKelasAction::make(),
            ])
            ->recordActions([
                AturKelasAction::make(),
            ])
            ->toolbarActions([
                AturKelasMassalAction::make(),
            ])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading('Belum ada peserta terverifikasi')
            ->emptyStateDescription('Verifikasi pendaftar dulu di sub-menu Pendaftar.');
    }

    public function getTitle(): string|Htmlable
    {
        return "Pembagian Kelas {$this->getOwnerRecord()->nama_tampilan}";
    }

    protected function getHeaderActions(): array
    {
        return [
            KembaliAction::ke(PelatihanResource::getUrl('index'), 'Kembali ke daftar pelatihan'),
        ];
    }
}
