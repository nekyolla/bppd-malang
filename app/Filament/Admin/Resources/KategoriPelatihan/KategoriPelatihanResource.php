<?php

namespace App\Filament\Admin\Resources\KategoriPelatihan;

use App\Filament\Admin\Resources\KategoriPelatihan\Pages\ManageKategoriPelatihan;
use App\Filament\Admin\Support\DataMaster;
use App\Models\KategoriPelatihan;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class KategoriPelatihanResource extends Resource
{
    protected static ?string $model = KategoriPelatihan::class;

    protected static ?string $slug = 'kategori-pelatihan';

    protected static ?string $modelLabel = 'kategori pelatihan';

    protected static ?string $pluralModelLabel = 'kategori pelatihan';

    protected static ?string $navigationLabel = 'Kategori Pelatihan';

    protected static string|UnitEnum|null $navigationGroup = 'Data Master';

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $recordTitleAttribute = 'nama_kategori';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nama_kategori')
                    ->label('Nama kategori')
                    ->required()
                    ->maxLength(255)
                    ->unique(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return DataMaster::tabel($table
            ->columns([
                TextColumn::make('nama_kategori')
                    ->label('Nama kategori')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('judul_pelatihan_count')
                    ->label('Jumlah judul')
                    ->counts('judulPelatihan'),
            ])
            ->defaultSort('nama_kategori'), 'Belum ada kategori pelatihan');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageKategoriPelatihan::route('/'),
        ];
    }
}
