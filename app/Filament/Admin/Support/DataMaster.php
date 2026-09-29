<?php

namespace App\Filament\Admin\Support;

use App\Filament\Admin\Actions\AktifkanAction;
use App\Filament\Admin\Actions\NonaktifkanAction;
use Closure;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Bagian tabel & form yang sama untuk semua resource data master.
 */
class DataMaster
{
    /**
     * Kolom status, filter aktif (default), aksi ubah/nonaktifkan/aktifkan,
     * tanpa aksi hapus maupun bulk action (CLAUDE.md aturan 2).
     */
    public static function tabel(Table $table, string $kosong): Table
    {
        return $table
            ->pushColumns([
                TextColumn::make('is_aktif')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Aktif' : 'Nonaktif')
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
            ])
            ->pushFilters([
                TernaryFilter::make('is_aktif')
                    ->label('Status')
                    ->placeholder('Semua')
                    ->trueLabel('Aktif')
                    ->falseLabel('Nonaktif')
                    ->default(true),
            ])
            ->recordActions([
                EditAction::make(),
                NonaktifkanAction::make(),
                AktifkanAction::make(),
            ])
            ->toolbarActions([])
            ->emptyStateHeading($kosong)
            ->emptyStateDescription('Tambahkan data baru, atau ubah filter Status untuk melihat data nonaktif.');
    }

    /**
     * Query untuk `Select::relationship()`: hanya data aktif, ditambah nilai yang
     * sedang dipakai record agar form ubah tidak kosong setelah datanya dinonaktifkan.
     */
    public static function pilihanAktif(string $foreignKey): Closure
    {
        return fn (Builder $query, ?Model $record): Builder => $query->where(fn (Builder $q) => $q
            ->where($q->qualifyColumn('is_aktif'), true)
            ->when($record?->getAttribute($foreignKey), fn (Builder $q, $id) => $q->orWhere($q->qualifyColumn('id'), $id)));
    }
}
