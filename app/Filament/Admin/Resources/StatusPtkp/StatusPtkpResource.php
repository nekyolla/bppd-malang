<?php

namespace App\Filament\Admin\Resources\StatusPtkp;

use App\Filament\Admin\Resources\StatusPtkp\Pages\ManageStatusPtkp;
use App\Filament\Admin\Support\DataMaster;
use App\Models\StatusPtkp;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class StatusPtkpResource extends Resource
{
    protected static ?string $model = StatusPtkp::class;

    protected static ?string $slug = 'status-ptkp';

    protected static ?string $modelLabel = 'status PTKP';

    protected static ?string $pluralModelLabel = 'status PTKP';

    protected static ?string $navigationLabel = 'Status PTKP';

    protected static string|UnitEnum|null $navigationGroup = 'Data Master';

    protected static ?int $navigationSort = 5;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static ?string $recordTitleAttribute = 'kode';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('kode')
                    ->label('Kode')
                    ->placeholder('TK/0')
                    ->required()
                    ->maxLength(5)
                    ->unique(),
                TextInput::make('nama')
                    ->label('Keterangan')
                    ->placeholder('Tidak kawin, tanpa tanggungan')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return DataMaster::tabel($table
            ->columns([
                TextColumn::make('kode')
                    ->label('Kode')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('nama')
                    ->label('Keterangan')
                    ->searchable(),
            ])
            ->defaultSort('id'), 'Belum ada status PTKP');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageStatusPtkp::route('/'),
        ];
    }
}
