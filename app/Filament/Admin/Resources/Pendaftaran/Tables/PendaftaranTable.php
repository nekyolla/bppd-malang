<?php

namespace App\Filament\Admin\Resources\Pendaftaran\Tables;

use App\Enums\StatusPendaftaran;
use App\Filament\Admin\Actions\VerifikasiAction;
use App\Filament\Admin\Actions\VerifikasiMassalAction;
use App\Models\Desa;
use App\Models\KabKota;
use App\Models\Pelatihan;
use App\Models\PelatihanPeserta;
use App\Models\Provinsi;
use App\Services\PendaftaranService;
use App\Support\Masking;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PendaftaranTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'peserta.desa.kecamatan.kabKota',
                'desaSaatPelatihan.kecamatan.kabKota',
                'pelatihan.judulPelatihan',
            ]))
            ->columns([
                TextColumn::make('created_at')
                    ->label('Tanggal daftar')
                    ->dateTime('j M Y, H:i')
                    ->sortable(),
                TextColumn::make('peserta.nama_lengkap')
                    ->label('Nama')
                    ->description(fn (PelatihanPeserta $record): string => Masking::nik($record->peserta->nik))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas('peserta', fn (Builder $q) => $q
                        ->where('nama_lengkap', 'like', "%{$search}%")
                        ->orWhere('nik', preg_replace('/\D/', '', $search) ?: $search))),
                TextColumn::make('desa')
                    ->label('Desa')
                    ->state(fn (PelatihanPeserta $record): string => self::desa($record)->nama)
                    ->description(fn (PelatihanPeserta $record): string => self::desa($record)->kecamatan->kabKota->nama),
                TextColumn::make('pelatihan.nama_tampilan')
                    ->label('Pelatihan')
                    ->wrap(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->description(fn (PelatihanPeserta $record): ?string => $record->status === StatusPendaftaran::Terdaftar
                        && app(PendaftaranService::class)->perbedaanIsian($record) !== []
                            ? 'Isian berbeda dari data lama'
                            : null),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('pelatihan_id')
                    ->label('Pelatihan')
                    ->options(fn (): array => Pelatihan::query()->with('judulPelatihan')->orderByDesc('tanggal_mulai')->get()
                        ->mapWithKeys(fn (Pelatihan $p): array => [$p->id => $p->nama_tampilan])->all())
                    ->searchable(),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(StatusPendaftaran::class),
                Filter::make('wilayah')
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
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['kab_kota_id'] ?? null, fn (Builder $q, $kabKota) => $q->whereHas('peserta.desa.kecamatan', fn (Builder $q) => $q->where('kab_kota_id', $kabKota)))
                        ->when(! ($data['kab_kota_id'] ?? null) && ($data['provinsi_id'] ?? null), fn (Builder $q) => $q->whereHas('peserta.desa.kecamatan.kabKota', fn (Builder $q) => $q->where('provinsi_id', $data['provinsi_id']))))
                    ->indicateUsing(fn (array $data): ?string => match (true) {
                        filled($data['kab_kota_id'] ?? null) => KabKota::find($data['kab_kota_id'])?->nama,
                        filled($data['provinsi_id'] ?? null) => Provinsi::find($data['provinsi_id'])?->nama,
                        default => null,
                    }),
            ])
            ->recordActions([
                VerifikasiAction::make(),
                ViewAction::make(),
            ])
            ->toolbarActions([
                VerifikasiMassalAction::make(),
            ])
            ->emptyStateHeading('Belum ada pendaftar')
            ->emptyStateDescription('Pendaftar akan muncul di sini setelah mengisi form registrasi di /daftar.');
    }

    /**
     * Desa saat pelatihan (snapshot) setelah verifikasi; sebelumnya desa di data peserta.
     */
    private static function desa(PelatihanPeserta $record): Desa
    {
        return $record->desaSaatPelatihan ?? $record->peserta->desa;
    }
}
