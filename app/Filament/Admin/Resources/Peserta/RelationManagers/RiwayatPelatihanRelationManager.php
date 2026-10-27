<?php

namespace App\Filament\Admin\Resources\Peserta\RelationManagers;

use App\Filament\Admin\Resources\Pendaftaran\PendaftaranResource;
use App\Models\PelatihanPeserta;
use App\Support\FormatTanggal;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Riwayat seluruh pelatihan seorang peserta (FR-PST-01), hanya-baca. Jabatan memakai
 * snapshot saat pelatihan (CLAUDE.md aturan 6).
 */
class RiwayatPelatihanRelationManager extends RelationManager
{
    protected static string $relationship = 'pendaftaran';

    protected static ?string $title = 'Riwayat pelatihan';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['pelatihan.judulPelatihan', 'kelasPelatihan', 'jabatanSaatPelatihan', 'sertifikat']))
            ->columns([
                TextColumn::make('pelatihan.nama_tampilan')
                    ->label('Pelatihan')
                    ->description(fn (PelatihanPeserta $record): string => FormatTanggal::rentang($record->pelatihan->tanggal_mulai, $record->pelatihan->tanggal_selesai))
                    ->wrap(),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('kelasPelatihan.nama_kelas')->label('Kelas')->placeholder('–'),
                TextColumn::make('jabatan_saat_pelatihan_tampilan')->label('Jabatan saat pelatihan')->placeholder('Belum diverifikasi'),
                TextColumn::make('sertifikat')
                    ->label('Sertifikat')
                    ->state(fn (PelatihanPeserta $record): ?string => $record->sertifikat ? 'Lihat sertifikat' : null)
                    ->url(fn (PelatihanPeserta $record): ?string => $record->sertifikat ? route('berkas.sertifikat', $record) : null, shouldOpenInNewTab: true)
                    ->color('primary')
                    ->placeholder('–'),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordUrl(fn (PelatihanPeserta $record): string => PendaftaranResource::getUrl('view', ['record' => $record]))
            ->paginated(false)
            ->emptyStateHeading('Belum pernah mendaftar pelatihan');
    }
}
