<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Pages\RincianWilayah;
use App\Filament\Admin\Widgets\Concerns\MembacaFilterTahun;
use App\Services\StatistikService;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Number;

/**
 * Tabel provinsi: data yang sama dengan peta, sekaligus alternatif peta untuk
 * pembaca layar (DESIGN_SYSTEM §5.3, §8).
 */
class StatistikProvinsi extends TableWidget
{
    use MembacaFilterTahun;

    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $angka = fn ($n): string => Number::format((int) $n, locale: 'id');

        return $table
            ->heading('Sebaran per provinsi')
            ->description('Klik provinsi untuk melihat rincian kab/kota dan desa terlatih.')
            ->records(fn (): array => app(StatistikService::class)->perProvinsi($this->tahun())->keyBy('id')->all())
            ->columns([
                TextColumn::make('nama')->label('Provinsi'),
                TextColumn::make('kab_kota_terlatih')
                    ->label('Kab/kota terlatih')
                    ->formatStateUsing(fn ($state, array $record): string => "{$angka($state)} dari {$angka($record['total_kab_kota'])}"),
                TextColumn::make('desa_terlatih')
                    ->label('Desa terlatih')
                    ->formatStateUsing($angka),
                TextColumn::make('desa_belum_terlatih')
                    ->label('Desa belum terlatih')
                    ->formatStateUsing($angka),
                TextColumn::make('peserta_terlatih')
                    ->label('Peserta terlatih')
                    ->formatStateUsing($angka),
            ])
            ->recordUrl(fn (array $record): string => RincianWilayah::getUrl(['provinsi' => $record['id'], 'tahun' => $this->tahun()]))
            ->paginated(false);
    }
}
