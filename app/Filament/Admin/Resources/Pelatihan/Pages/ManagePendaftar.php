<?php

namespace App\Filament\Admin\Resources\Pelatihan\Pages;

use App\Enums\StatusPendaftaran;
use App\Filament\Admin\Resources\Pelatihan\PelatihanResource;
use App\Filament\Admin\Resources\Pendaftaran\Tables\PendaftaranTable;
use App\Models\Pelatihan;
use App\Services\PelatihanService;
use BackedEnum;
use Filament\Navigation\NavigationItem;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Pendaftar satu pelatihan (PF-17): verifikasi, batal, kembalikan, dan hapus.
 */
class ManagePendaftar extends ManageRelatedRecords
{
    protected static string $resource = PelatihanResource::class;

    protected static string $relationship = 'pendaftaran';

    protected static ?string $navigationLabel = 'Pendaftar';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $breadcrumb = 'Pendaftar';

    public function getTitle(): string|Htmlable
    {
        return "Pendaftar {$this->getOwnerRecord()->nama_tampilan}";
    }

    public function table(Table $table): Table
    {
        return PendaftaranTable::configure($table);
    }

    /**
     * Tab "Pendaftar" di sub-menu pelatihan diberi jumlah yang menunggu verifikasi.
     *
     * @param  array<string, mixed>  $urlParameters
     * @return array<NavigationItem>
     */
    public static function getNavigationItems(array $urlParameters = []): array
    {
        $pelatihan = $urlParameters['record'] ?? null;
        $menunggu = $pelatihan instanceof Pelatihan ? app(PelatihanService::class)->jumlahMenunggu($pelatihan) : 0;

        return array_map(
            fn (NavigationItem $item): NavigationItem => $item
                ->badge($menunggu > 0 ? (string) $menunggu : null, color: 'warning')
                ->badgeTooltip('Menunggu verifikasi'),
            parent::getNavigationItems($urlParameters),
        );
    }

    /**
     * Halaman pendaftar yang langsung tersaring "Menunggu verifikasi" (PF-15).
     */
    public static function urlMenunggu(Pelatihan $pelatihan): string
    {
        return static::getUrl(['record' => $pelatihan, 'filters' => ['status' => ['value' => StatusPendaftaran::Terdaftar->value]]]);
    }
}
