<?php

namespace App\Filament\Admin\Resources\Wilayah;

use App\Filament\Admin\Resources\Wilayah\Pages\ListWilayah;
use App\Filament\Admin\Support\FilterWilayah;
use App\Models\Desa;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Data wilayah hanya-baca (FR-MSTR-03): diisi WilayahSeeder dari wilayah.csv, bukan lewat
 * panel. Dipakai superadmin untuk memastikan sebuah desa ada beserta kodenya, mis. saat
 * peserta tidak menemukan desanya di form registrasi.
 */
class WilayahResource extends Resource
{
    protected static ?string $model = Desa::class;

    protected static ?string $slug = 'wilayah';

    protected static ?string $modelLabel = 'desa';

    protected static ?string $pluralModelLabel = 'wilayah';

    protected static ?string $navigationLabel = 'Wilayah';

    protected static string|UnitEnum|null $navigationGroup = 'Data Master';

    protected static ?int $navigationSort = 7;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static ?string $recordTitleAttribute = 'nama';

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('kecamatan.kabKota.provinsi'))
            ->columns([
                TextColumn::make('kode')
                    ->label('Kode desa')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('nama')
                    ->label('Desa')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('kecamatan.nama')
                    ->label('Kecamatan')
                    ->searchable(),
                TextColumn::make('kecamatan.kabKota.nama')
                    ->label('Kabupaten/kota'),
                TextColumn::make('kecamatan.kabKota.provinsi.nama')
                    ->label('Provinsi'),
            ])
            ->filters([
                FilterWilayah::make(null),
            ])
            ->defaultSort('kode')
            ->searchPlaceholder('Cari desa atau kode')
            ->recordActions([])
            ->toolbarActions([])
            ->emptyStateHeading('Data wilayah belum dimuat')
            ->emptyStateDescription('Data wilayah diisi lewat seeder saat pemasangan, bukan dari panel.');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWilayah::route('/'),
        ];
    }
}
