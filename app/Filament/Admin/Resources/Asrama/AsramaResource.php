<?php

namespace App\Filament\Admin\Resources\Asrama;

use App\Filament\Admin\Resources\Asrama\Pages\CreateAsrama;
use App\Filament\Admin\Resources\Asrama\Pages\EditAsrama;
use App\Filament\Admin\Resources\Asrama\Pages\ListAsrama;
use App\Filament\Admin\Resources\Asrama\RelationManagers\KamarRelationManager;
use App\Filament\Admin\Support\DataMaster;
use App\Models\Asrama;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Asrama & kamar BBPD (FR-MSTR-01). Gender kamar tidak diinput di sini:
 * ditentukan otomatis dari penghuni pertama (CLAUDE.md aturan 5).
 */
class AsramaResource extends Resource
{
    protected static ?string $model = Asrama::class;

    protected static ?string $slug = 'asrama';

    protected static ?string $modelLabel = 'asrama';

    protected static ?string $pluralModelLabel = 'asrama';

    protected static ?string $navigationLabel = 'Asrama & Kamar';

    protected static string|UnitEnum|null $navigationGroup = 'Data Master';

    protected static ?int $navigationSort = 6;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice;

    protected static ?string $recordTitleAttribute = 'nama_asrama';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nama_asrama')
                    ->label('Nama asrama')
                    ->required()
                    ->maxLength(255)
                    ->unique(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return DataMaster::tabel($table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->withCount(['kamar as kamar_aktif' => fn (Builder $q) => $q->where('is_aktif', true)])
                ->withSum(['kamar as kapasitas_aktif' => fn (Builder $q) => $q->where('is_aktif', true)], 'kapasitas'))
            ->columns([
                TextColumn::make('nama_asrama')->label('Nama asrama')->searchable()->sortable(),
                TextColumn::make('kamar_aktif')->label('Kamar aktif'),
                TextColumn::make('kapasitas_aktif')->label('Tempat tidur')->placeholder('0'),
            ])
            ->defaultSort('nama_asrama'), 'Belum ada asrama');
    }

    public static function getRelations(): array
    {
        return [
            KamarRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAsrama::route('/'),
            'create' => CreateAsrama::route('/create'),
            'edit' => EditAsrama::route('/{record}/edit'),
        ];
    }
}
