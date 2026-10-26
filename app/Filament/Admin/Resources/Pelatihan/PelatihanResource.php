<?php

namespace App\Filament\Admin\Resources\Pelatihan;

use App\Filament\Admin\Resources\Pelatihan\Pages\CreatePelatihan;
use App\Filament\Admin\Resources\Pelatihan\Pages\EditPelatihan;
use App\Filament\Admin\Resources\Pelatihan\Pages\ListPelatihan;
use App\Filament\Admin\Resources\Pelatihan\Pages\ManageKelas;
use App\Filament\Admin\Resources\Pelatihan\Pages\ManagePembagianKelas;
use App\Filament\Admin\Resources\Pelatihan\Pages\ManagePendaftar;
use App\Filament\Admin\Resources\Pelatihan\Pages\PesertaKelas;
use App\Filament\Admin\Resources\Pelatihan\Schemas\PelatihanForm;
use App\Filament\Admin\Resources\Pelatihan\Tables\PelatihanTable;
use App\Models\Pelatihan;
use BackedEnum;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class PelatihanResource extends Resource
{
    protected static ?string $model = Pelatihan::class;

    protected static ?string $slug = 'pelatihan';

    protected static ?string $modelLabel = 'pelatihan';

    protected static ?string $pluralModelLabel = 'pelatihan';

    protected static ?string $navigationLabel = 'Pelatihan';

    protected static string|UnitEnum|null $navigationGroup = 'Pelatihan';

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    public static function getRecordTitle(?Model $record): string
    {
        return $record instanceof Pelatihan ? $record->nama_tampilan : 'Pelatihan';
    }

    public static function form(Schema $schema): Schema
    {
        return PelatihanForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PelatihanTable::configure($table);
    }

    /**
     * Sub-menu satu pelatihan (PF-17): Data · Pendaftar · Kelas · Pembagian Kelas.
     * Penempatan kamar cukup lewat menu Asrama di sidebar.
     *
     * @return array<NavigationItem>
     */
    public static function getRecordSubNavigation(Page $page): array
    {
        return $page->generateNavigationItems([
            EditPelatihan::class,
            ManagePendaftar::class,
            ManageKelas::class,
            ManagePembagianKelas::class,
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPelatihan::route('/'),
            'create' => CreatePelatihan::route('/create'),
            'edit' => EditPelatihan::route('/{record}/edit'),
            'pendaftar' => ManagePendaftar::route('/{record}/pendaftar'),
            'kelas' => ManageKelas::route('/{record}/kelas'),
            'kelas.peserta' => PesertaKelas::route('/{record}/kelas/{kelas}'),
            'pembagian-kelas' => ManagePembagianKelas::route('/{record}/pembagian-kelas'),
        ];
    }
}
