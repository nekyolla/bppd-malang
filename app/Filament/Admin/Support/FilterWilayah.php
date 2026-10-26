<?php

namespace App\Filament\Admin\Support;

use App\Models\KabKota;
use App\Models\Provinsi;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

/**
 * Filter provinsi → kab/kota berjenjang untuk tabel yang barisnya terhubung ke desa.
 */
class FilterWilayah
{
    /**
     * @param  string  $relasiDesa  Path relasi dari baris tabel ke desa, mis. `peserta.desa` atau `desa`.
     */
    public static function make(string $relasiDesa): Filter
    {
        return Filter::make('wilayah')
            ->schema([
                Select::make('provinsi_id')
                    ->label('Provinsi')
                    ->options(fn (): array => Provinsi::query()->orderBy('nama')->pluck('nama', 'id')->all())
                    ->searchable()
                    ->live(),
                Select::make('kab_kota_id')
                    ->label('Kabupaten/kota')
                    ->options(fn (Get $get): array => KabKota::query()->where('provinsi_id', $get('provinsi_id'))->orderBy('nama')->pluck('nama', 'id')->all())
                    ->searchable(),
            ])
            ->query(fn (Builder $query, array $data): Builder => match (true) {
                filled($data['kab_kota_id'] ?? null) => $query->whereHas("{$relasiDesa}.kecamatan", fn (Builder $q) => $q->where('kab_kota_id', $data['kab_kota_id'])),
                filled($data['provinsi_id'] ?? null) => $query->whereHas("{$relasiDesa}.kecamatan.kabKota", fn (Builder $q) => $q->where('provinsi_id', $data['provinsi_id'])),
                default => $query,
            })
            ->indicateUsing(fn (array $data): ?string => match (true) {
                filled($data['kab_kota_id'] ?? null) => KabKota::find($data['kab_kota_id'])?->nama,
                filled($data['provinsi_id'] ?? null) => Provinsi::find($data['provinsi_id'])?->nama,
                default => null,
            });
    }
}
