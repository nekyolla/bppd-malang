<?php

namespace App\Filament\Admin\Resources\Pelatihan;

use App\Filament\Admin\Resources\Pelatihan\Pages\CreatePelatihan;
use App\Filament\Admin\Resources\Pelatihan\Pages\EditPelatihan;
use App\Filament\Admin\Resources\Pelatihan\Pages\ListPelatihan;
use App\Filament\Admin\Resources\Pelatihan\Pages\ManageKelas;
use App\Filament\Admin\Resources\Pelatihan\Pages\ManagePembagianKelas;
use App\Filament\Admin\Resources\Pelatihan\Pages\ManagePendaftar;
use App\Filament\Admin\Resources\Pelatihan\Pages\ManageSertifikat;
use App\Filament\Admin\Resources\Pelatihan\Pages\PesertaKelas;
use App\Filament\Admin\Resources\Pelatihan\Schemas\PelatihanForm;
use App\Filament\Admin\Resources\Pelatihan\Tables\PelatihanTable;
use App\Models\Pelatihan;
use App\Support\FormatTanggal;
use BackedEnum;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
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

    protected static bool $isGloballySearchable = true;

    public static function getGloballySearchableAttributes(): array
    {
        return ['judulPelatihan.judul', 'tahun_anggaran'];
    }

    /**
     * @param  Pelatihan  $record
     */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Tanggal' => FormatTanggal::rentang($record->tanggal_mulai, $record->tanggal_selesai),
            'Status' => $record->status->getLabel(),
        ];
    }

    /**
     * Hasil pencarian ⌘K membuka halaman kerja (pendaftar), bukan form ubah (PF-38).
     */
    public static function getGlobalSearchResultUrl(Model $record): string
    {
        return ManagePendaftar::getUrl(['record' => $record]);
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with('judulPelatihan');
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
     * Sub-menu kerja satu pelatihan (PF-17): Pendaftar · Kelas · Pembagian Kelas · Sertifikat.
     * Form ubah pelatihan berdiri sendiri tanpa sub-menu (PF-38). Penempatan kamar cukup
     * lewat menu Asrama di sidebar.
     *
     * @return array<NavigationItem>
     */
    public static function getRecordSubNavigation(Page $page): array
    {
        if ($page instanceof EditPelatihan) {
            return [];
        }

        return $page->generateNavigationItems([
            ManagePendaftar::class,
            ManageKelas::class,
            ManagePembagianKelas::class,
            ManageSertifikat::class,
        ]);
    }

    /**
     * Menu Pelatihan hanya aktif di daftar, buat, dan ubah; sub-halaman kerja milik menu
     * Pendaftaran (PF-38).
     *
     * @return array<string>
     */
    public static function getNavigationItemActiveRoutePattern(): array
    {
        return [ListPelatihan::getRouteName(), CreatePelatihan::getRouteName(), EditPelatihan::getRouteName()];
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
            'sertifikat' => ManageSertifikat::route('/{record}/sertifikat'),
        ];
    }
}
