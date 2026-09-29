<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Widgets\PendaftarTerbaru;
use App\Filament\Admin\Widgets\PetaProvinsi;
use App\Filament\Admin\Widgets\RingkasanStatistik;
use App\Filament\Admin\Widgets\StatistikProvinsi;
use App\Services\StatistikService;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Schema;

/**
 * Dashboard admin: statistik peserta dan sebaran desa terlatih (PRD §8.16, DESIGN_SYSTEM §5.3).
 */
class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    protected static ?string $title = 'Dashboard';

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('tahun')
                ->label('Tahun')
                ->placeholder('Semua tahun')
                ->options(fn (): array => collect(app(StatistikService::class)->daftarTahun())
                    ->mapWithKeys(fn (int $tahun): array => [$tahun => (string) $tahun])
                    ->all()),
        ]);
    }

    public function getWidgets(): array
    {
        return [
            RingkasanStatistik::class,
            PetaProvinsi::class,
            StatistikProvinsi::class,
            PendaftarTerbaru::class,
        ];
    }

    public function getColumns(): int|array
    {
        return 1;
    }
}
