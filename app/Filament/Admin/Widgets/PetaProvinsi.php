<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Pages\RincianWilayah;
use App\Filament\Admin\Widgets\Concerns\MembacaFilterTahun;
use App\Services\StatistikService;
use Filament\Widgets\Widget;

/**
 * Peta Indonesia per provinsi yang dapat diklik (FR-DSB-03, FR-DSB-07).
 * Skrip Leaflet dimuat lewat render hook di AdminPanelProvider.
 */
class PetaProvinsi extends Widget
{
    use MembacaFilterTahun;

    protected static bool $isDiscovered = false;

    protected string $view = 'filament.admin.widgets.peta-provinsi';

    protected int|string|array $columnSpan = 'full';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $tahun = $this->tahun();

        return [
            'tahun' => $tahun,
            'data' => app(StatistikService::class)->perProvinsi($tahun)
                ->mapWithKeys(fn (array $p): array => [$p['kode'] => [
                    ...$p,
                    'url' => RincianWilayah::getUrl(['provinsi' => $p['id'], 'tahun' => $tahun]),
                ]])
                ->all(),
        ];
    }
}
