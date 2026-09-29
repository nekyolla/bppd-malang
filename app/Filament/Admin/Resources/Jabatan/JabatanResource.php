<?php

namespace App\Filament\Admin\Resources\Jabatan;

use App\Filament\Admin\Resources\Jabatan\Pages\ManageJabatan;
use App\Filament\Admin\Support\DataMaster;
use App\Models\Jabatan;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class JabatanResource extends Resource
{
    protected static ?string $model = Jabatan::class;

    protected static ?string $slug = 'jabatan';

    protected static ?string $modelLabel = 'jabatan';

    protected static ?string $pluralModelLabel = 'jabatan';

    protected static ?string $navigationLabel = 'Jabatan';

    protected static string|UnitEnum|null $navigationGroup = 'Data Master';

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;

    protected static ?string $recordTitleAttribute = 'nama_jabatan';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nama_jabatan')
                    ->label('Nama jabatan')
                    ->required()
                    ->maxLength(255)
                    ->unique(),
                TextInput::make('urutan')
                    ->label('Urutan tampil')
                    ->helperText('Urutan jabatan di pilihan form registrasi.')
                    ->required()
                    ->integer()
                    ->minValue(0)
                    ->default(fn (): int => (int) Jabatan::max('urutan') + 1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return DataMaster::tabel($table
            ->columns([
                TextColumn::make('urutan')
                    ->label('Urutan')
                    ->sortable(),
                TextColumn::make('nama_jabatan')
                    ->label('Nama jabatan')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('peserta_count')
                    ->label('Jumlah peserta')
                    ->counts('peserta'),
            ])
            ->defaultSort('urutan'), 'Belum ada jabatan');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageJabatan::route('/'),
        ];
    }
}
