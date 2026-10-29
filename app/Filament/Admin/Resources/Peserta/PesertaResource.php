<?php

namespace App\Filament\Admin\Resources\Peserta;

use App\Filament\Admin\Resources\Peserta\Pages\EditPeserta;
use App\Filament\Admin\Resources\Peserta\Pages\ListPeserta;
use App\Filament\Admin\Resources\Peserta\Pages\ViewPeserta;
use App\Filament\Admin\Resources\Peserta\RelationManagers\RiwayatPelatihanRelationManager;
use App\Filament\Admin\Resources\Peserta\Schemas\PesertaInfolist;
use App\Filament\Admin\Resources\Peserta\Tables\PesertaTable;
use App\Filament\Admin\Support\IsianPesertaForm;
use App\Models\Peserta;
use App\Support\Masking;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Data peserta, satu baris per NIK, beserta riwayat pelatihannya (FR-PST-01–03).
 * Peserta dibuat lewat form registrasi publik, jadi tidak ada tambah maupun hapus.
 */
class PesertaResource extends Resource
{
    protected static ?string $model = Peserta::class;

    protected static ?string $slug = 'peserta';

    protected static ?string $modelLabel = 'peserta';

    protected static ?string $pluralModelLabel = 'peserta';

    protected static ?string $navigationLabel = 'Peserta';

    protected static string|UnitEnum|null $navigationGroup = 'Pelatihan';

    protected static ?int $navigationSort = 3;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static ?string $recordTitleAttribute = 'nama_lengkap';

    protected static bool $isGloballySearchable = true;

    public static function getGloballySearchableAttributes(): array
    {
        return ['nama_lengkap', 'nik'];
    }

    /**
     * NIK di hasil pencarian tetap disamarkan (DESIGN_SYSTEM §4).
     *
     * @param  Peserta  $record
     */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return array_filter([
            'NIK' => Masking::nik($record->nik),
            'Desa' => $record->desa?->nama,
        ]);
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with('desa');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components(IsianPesertaForm::bagian());
    }

    public static function infolist(Schema $schema): Schema
    {
        return PesertaInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PesertaTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RiwayatPelatihanRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPeserta::route('/'),
            'view' => ViewPeserta::route('/{record}'),
            'edit' => EditPeserta::route('/{record}/edit'),
        ];
    }
}
