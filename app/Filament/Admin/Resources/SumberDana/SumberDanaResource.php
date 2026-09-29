<?php

namespace App\Filament\Admin\Resources\SumberDana;

use App\Filament\Admin\Resources\SumberDana\Pages\ManageSumberDana;
use App\Filament\Admin\Support\DataMaster;
use App\Models\SumberDana;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class SumberDanaResource extends Resource
{
    protected static ?string $model = SumberDana::class;

    protected static ?string $slug = 'sumber-dana';

    protected static ?string $modelLabel = 'sumber dana';

    protected static ?string $pluralModelLabel = 'sumber dana';

    protected static ?string $navigationLabel = 'Sumber Dana';

    protected static string|UnitEnum|null $navigationGroup = 'Data Master';

    protected static ?int $navigationSort = 4;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $recordTitleAttribute = 'nama';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nama')
                    ->label('Nama sumber dana')
                    ->required()
                    ->maxLength(255)
                    ->unique(),
                Toggle::make('butuh_keterangan')
                    ->label('Wajib isi keterangan')
                    ->helperText('Aktifkan untuk pilihan seperti "Lainnya": peserta wajib menuliskan sumber dananya.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return DataMaster::tabel($table
            ->columns([
                TextColumn::make('nama')
                    ->label('Nama sumber dana')
                    ->searchable()
                    ->sortable(),
                IconColumn::make('butuh_keterangan')
                    ->label('Wajib keterangan')
                    ->boolean(),
            ])
            ->defaultSort('nama'), 'Belum ada sumber dana');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSumberDana::route('/'),
        ];
    }
}
