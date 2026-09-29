<?php

namespace App\Filament\Admin\Resources\Pendaftaran;

use App\Enums\StatusPendaftaran;
use App\Filament\Admin\Resources\Pendaftaran\Pages\ListPendaftaran;
use App\Filament\Admin\Resources\Pendaftaran\Pages\ViewPendaftaran;
use App\Filament\Admin\Resources\Pendaftaran\Schemas\PendaftaranInfolist;
use App\Filament\Admin\Resources\Pendaftaran\Tables\PendaftaranTable;
use App\Models\PelatihanPeserta;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Peserta yang sudah registrasi lewat form publik (FR-DFT-02). Tidak ada form
 * tambah/ubah: data masuk dari /daftar dan berubah lewat aksi verifikasi.
 */
class PendaftaranResource extends Resource
{
    protected static ?string $model = PelatihanPeserta::class;

    protected static ?string $slug = 'pendaftaran';

    protected static ?string $modelLabel = 'pendaftaran';

    protected static ?string $pluralModelLabel = 'pendaftaran';

    protected static ?string $navigationLabel = 'Pendaftaran';

    protected static string|UnitEnum|null $navigationGroup = 'Pelatihan';

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    public static function getRecordTitle(?Model $record): string
    {
        return $record instanceof PelatihanPeserta ? $record->peserta->nama_lengkap : 'Pendaftaran';
    }

    public static function getNavigationBadge(): ?string
    {
        $menunggu = PelatihanPeserta::query()->where('status', StatusPendaftaran::Terdaftar)->count();

        return $menunggu > 0 ? (string) $menunggu : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): string
    {
        return 'Menunggu verifikasi';
    }

    public static function infolist(Schema $schema): Schema
    {
        return PendaftaranInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PendaftaranTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPendaftaran::route('/'),
            'view' => ViewPendaftaran::route('/{record}'),
        ];
    }
}
