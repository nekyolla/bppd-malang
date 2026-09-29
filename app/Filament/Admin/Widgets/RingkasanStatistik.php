<?php

namespace App\Filament\Admin\Widgets;

use App\Enums\StatusPendaftaran;
use App\Filament\Admin\Resources\Pendaftaran\PendaftaranResource;
use App\Filament\Admin\Widgets\Concerns\MembacaFilterTahun;
use App\Services\StatistikService;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

/**
 * Kartu statistik di bagian atas dashboard (FR-DSB-02).
 */
class RingkasanStatistik extends StatsOverviewWidget
{
    use MembacaFilterTahun;

    protected static bool $isDiscovered = false;

    protected ?string $pollingInterval = null;

    protected function getColumns(): int
    {
        return 5;
    }

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $r = app(StatistikService::class)->ringkasan($this->tahun());
        $angka = fn (int $n): string => Number::format($n, locale: 'id');
        $s = $r['per_status'];

        return [
            Stat::make('Total pendaftar', $angka($r['pendaftar']))
                ->description("{$angka($s['terverifikasi'])} terverifikasi · {$angka($s['selesai'])} selesai · {$angka($s['batal'])} batal")
                ->icon(Heroicon::OutlinedUserGroup),
            Stat::make('Menunggu verifikasi', $angka($r['menunggu']))
                ->description($r['menunggu'] > 0 ? 'Lihat daftar' : 'Semua sudah diperiksa')
                ->color($r['menunggu'] > 0 ? 'warning' : 'success')
                ->icon(Heroicon::OutlinedClock)
                ->url(PendaftaranResource::getUrl('index', ['tableFilters' => ['status' => ['value' => StatusPendaftaran::Terdaftar->value]]])),
            Stat::make('Peserta terlatih', $angka($r['peserta_terlatih']))
                ->description('Berstatus selesai')
                ->icon(Heroicon::OutlinedAcademicCap),
            Stat::make('Kab/kota terlatih', $angka($r['kab_kota_terlatih']))
                ->description("dari {$angka($r['total_kab_kota'])} kab/kota")
                ->icon(Heroicon::OutlinedBuildingOffice2),
            Stat::make('Desa terlatih', $angka($r['desa_terlatih']))
                ->description("dari {$angka($r['total_desa'])} desa")
                ->icon(Heroicon::OutlinedHome),
        ];
    }
}
