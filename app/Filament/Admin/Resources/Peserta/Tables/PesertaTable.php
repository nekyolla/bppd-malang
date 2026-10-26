<?php

namespace App\Filament\Admin\Resources\Peserta\Tables;

use App\Filament\Admin\Support\FilterWilayah;
use App\Models\Peserta;
use App\Support\Masking;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PesertaTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with(['jabatan', 'desa.kecamatan.kabKota'])
                ->withCount('pendaftaran'))
            ->columns([
                TextColumn::make('nama_lengkap')
                    ->label('Nama')
                    ->description(fn (Peserta $record): string => Masking::nik($record->nik))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query
                        ->where('nama_lengkap', 'like', "%{$search}%")
                        ->orWhere('nik', preg_replace('/\D/', '', $search) ?: $search))
                    ->sortable(),
                TextColumn::make('jenis_kelamin')->label('Jenis kelamin'),
                TextColumn::make('jabatan.nama_jabatan')->label('Jabatan'),
                TextColumn::make('desa.nama')
                    ->label('Desa')
                    ->description(fn (Peserta $record): string => $record->desa->kecamatan->kabKota->nama),
                TextColumn::make('pendaftaran_count')
                    ->label('Pelatihan')
                    ->suffix(' kali')
                    ->sortable(),
            ])
            ->defaultSort('nama_lengkap')
            ->filters([
                FilterWilayah::make('desa'),
                SelectFilter::make('jabatan_id')
                    ->label('Jabatan')
                    ->relationship('jabatan', 'nama_jabatan'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([])
            ->emptyStateHeading('Belum ada peserta')
            ->emptyStateDescription('Peserta tercatat otomatis saat mendaftar lewat form registrasi di /daftar.');
    }
}
