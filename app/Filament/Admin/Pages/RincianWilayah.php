<?php

namespace App\Filament\Admin\Pages;

use App\Models\KabKota;
use App\Models\Provinsi;
use App\Services\StatistikService;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Number;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * Rincian desa terlatih per provinsi → kab/kota → desa (FR-DSB-04). Hanya angka
 * dan nama wilayah, tanpa nama peserta.
 */
class RincianWilayah extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'rincian-wilayah';

    protected static ?string $title = 'Rincian Wilayah Terlatih';

    protected static ?string $navigationLabel = 'Rincian Wilayah';

    protected static string|UnitEnum|null $navigationGroup = 'Pelatihan';

    protected static ?int $navigationSort = 3;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected string $view = 'filament.admin.pages.rincian-wilayah';

    #[Url]
    public ?int $provinsi = null;

    #[Url(as: 'kab_kota')]
    public ?int $kabKota = null;

    #[Url]
    public ?int $tahun = null;

    public function mount(): void
    {
        if ($this->tahun) {
            $this->tableFilters = ['tahun' => ['value' => (string) $this->tahun]];
        }
    }

    public function table(Table $table): Table
    {
        $angka = fn ($n): string => Number::format((int) $n, locale: 'id');
        $service = app(StatistikService::class);

        return $table
            ->records(function (?array $filters) use ($service) {
                $tahun = filled($filters['tahun']['value'] ?? null) ? (int) $filters['tahun']['value'] : null;

                $baris = match ($this->tingkat()) {
                    'desa' => $service->desaTerlatih($this->kabKota, $tahun),
                    'kab_kota' => $service->perKabKota($this->provinsi, $tahun),
                    default => $service->perProvinsi($tahun),
                };

                return $baris->keyBy('id')->all();
            })
            ->columns(match ($this->tingkat()) {
                'desa' => [
                    TextColumn::make('nama')->label('Desa'),
                    TextColumn::make('kecamatan')->label('Kecamatan'),
                    TextColumn::make('peserta_terlatih')->label('Peserta terlatih')->formatStateUsing($angka),
                ],
                default => [
                    TextColumn::make('nama')->label($this->tingkat() === 'kab_kota' ? 'Kabupaten/kota' : 'Provinsi'),
                    ...($this->tingkat() === 'provinsi' ? [
                        TextColumn::make('kab_kota_terlatih')
                            ->label('Kab/kota terlatih')
                            ->formatStateUsing(fn ($state, array $record): string => "{$angka($state)} dari {$angka($record['total_kab_kota'])}"),
                    ] : []),
                    TextColumn::make('desa_terlatih')
                        ->label('Desa terlatih')
                        ->formatStateUsing(fn ($state, array $record): string => "{$angka($state)} dari {$angka($record['total_desa'])}"),
                    TextColumn::make('desa_belum_terlatih')->label('Desa belum terlatih')->formatStateUsing($angka),
                    TextColumn::make('peserta_terlatih')->label('Peserta terlatih')->formatStateUsing($angka),
                ],
            })
            ->filters([
                SelectFilter::make('tahun')
                    ->label('Tahun')
                    ->placeholder('Semua tahun')
                    ->options(fn (): array => collect(app(StatistikService::class)->daftarTahun())
                        ->mapWithKeys(fn (int $tahun): array => [$tahun => (string) $tahun])
                        ->all()),
            ])
            ->recordUrl(fn (array $record): ?string => match ($this->tingkat()) {
                'provinsi' => self::getUrl(['provinsi' => $record['id'], 'tahun' => $this->tahunTerpilih()]),
                'kab_kota' => self::getUrl(['provinsi' => $this->provinsi, 'kab_kota' => $record['id'], 'tahun' => $this->tahunTerpilih()]),
                default => null,
            })
            ->paginated(false)
            ->emptyStateHeading('Belum ada desa terlatih')
            ->emptyStateDescription('Desa dihitung terlatih setelah minimal satu pesertanya berstatus selesai.');
    }

    /**
     * Jejak navigasi: Semua provinsi › Provinsi › Kab/kota.
     *
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        $jejak = [self::getUrl(['tahun' => $this->tahunTerpilih()]) => 'Semua provinsi'];

        if ($this->provinsi && ($provinsi = Provinsi::find($this->provinsi))) {
            $jejak[self::getUrl(['provinsi' => $provinsi->id, 'tahun' => $this->tahunTerpilih()])] = $provinsi->nama;
        }

        if ($this->kabKota && ($kabKota = KabKota::find($this->kabKota))) {
            $jejak[] = $kabKota->nama;
        }

        return $jejak;
    }

    public function getSubheading(): ?string
    {
        return match ($this->tingkat()) {
            'desa' => 'Desa terlatih di '.KabKota::find($this->kabKota)?->nama.'.',
            'kab_kota' => 'Kab/kota di '.Provinsi::find($this->provinsi)?->nama.'. Klik untuk melihat desa terlatih.',
            default => 'Klik provinsi untuk melihat rincian kab/kota.',
        };
    }

    /**
     * @return 'provinsi'|'kab_kota'|'desa'
     */
    private function tingkat(): string
    {
        return match (true) {
            $this->kabKota !== null => 'desa',
            $this->provinsi !== null => 'kab_kota',
            default => 'provinsi',
        };
    }

    private function tahunTerpilih(): ?int
    {
        $tahun = $this->tableFilters['tahun']['value'] ?? null;

        return filled($tahun) ? (int) $tahun : null;
    }
}
