<?php

namespace App\Filament\Admin\Resources\JudulPelatihan;

use App\Filament\Admin\Resources\JudulPelatihan\Pages\ManageJudulPelatihan;
use App\Filament\Admin\Support\DataMaster;
use App\Models\JudulPelatihan;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class JudulPelatihanResource extends Resource
{
    protected static ?string $model = JudulPelatihan::class;

    protected static ?string $slug = 'judul-pelatihan';

    protected static ?string $modelLabel = 'judul pelatihan';

    protected static ?string $pluralModelLabel = 'judul pelatihan';

    protected static ?string $navigationLabel = 'Judul Pelatihan';

    protected static string|UnitEnum|null $navigationGroup = 'Data Master';

    protected static ?int $navigationSort = 3;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?string $recordTitleAttribute = 'judul';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('kategori_pelatihan_id')
                    ->label('Kategori')
                    ->relationship('kategoriPelatihan', 'nama_kategori', DataMaster::pilihanAktif('kategori_pelatihan_id'))
                    ->required()
                    ->searchable()
                    ->preload(),
                TextInput::make('judul')
                    ->label('Judul pelatihan')
                    ->helperText('Tanpa tahun dan batch, misal "Pelatihan Pengelolaan Keuangan Desa".')
                    ->required()
                    ->maxLength(255)
                    ->unique(),
                Textarea::make('deskripsi')
                    ->label('Deskripsi')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return DataMaster::tabel($table
            ->columns([
                TextColumn::make('judul')
                    ->label('Judul pelatihan')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('kategoriPelatihan.nama_kategori')
                    ->label('Kategori')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('kategori_pelatihan_id')
                    ->label('Kategori')
                    ->relationship('kategoriPelatihan', 'nama_kategori'),
            ])
            ->defaultSort('judul'), 'Belum ada judul pelatihan');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageJudulPelatihan::route('/'),
        ];
    }
}
