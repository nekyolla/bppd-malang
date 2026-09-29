<?php

namespace App\Filament\Admin\Resources\Pelatihan\Tables;

use App\Enums\StatusPelatihan;
use App\Enums\StatusPendaftaran;
use App\Enums\TipeLokasi;
use App\Filament\Admin\Actions\UbahStatusPelatihanAction;
use App\Models\KategoriPelatihan;
use App\Models\Pelatihan;
use App\Support\FormatTanggal;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PelatihanTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with('judulPelatihan')
                ->withCount([
                    'pendaftaran as jumlah_aktif' => fn (Builder $q) => $q->where('status', '!=', StatusPendaftaran::Batal),
                    'pendaftaran as jumlah_batal' => fn (Builder $q) => $q->where('status', StatusPendaftaran::Batal),
                    'pendaftaran as jumlah_menunggu' => fn (Builder $q) => $q->where('status', StatusPendaftaran::Terdaftar),
                ]))
            ->columns([
                TextColumn::make('nama_tampilan')
                    ->label('Pelatihan')
                    ->description(fn (Pelatihan $record): ?string => $record->keterangan_lokasi)
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query
                        ->whereHas('judulPelatihan', fn (Builder $q) => $q->where('judul', 'like', "%{$search}%")))
                    ->wrap(),
                TextColumn::make('tanggal_mulai')
                    ->label('Tanggal')
                    ->formatStateUsing(fn (Pelatihan $record): string => FormatTanggal::rentang($record->tanggal_mulai, $record->tanggal_selesai))
                    ->description(fn (Pelatihan $record): string => "{$record->jumlah_jp} JP")
                    ->sortable(),
                TextColumn::make('tipe_lokasi')
                    ->label('Lokasi'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('jumlah_aktif')
                    ->label('Peserta')
                    ->formatStateUsing(fn (Pelatihan $record): string => "{$record->jumlah_aktif} aktif · {$record->jumlah_batal} batal")
                    ->description(fn (Pelatihan $record): ?string => $record->jumlah_menunggu > 0 ? "{$record->jumlah_menunggu} menunggu verifikasi" : null),
            ])
            ->defaultSort('tanggal_mulai', 'desc')
            ->filters([
                SelectFilter::make('tahun_anggaran')
                    ->label('Tahun')
                    ->options(fn (): array => Pelatihan::query()->distinct()->orderByDesc('tahun_anggaran')->pluck('tahun_anggaran', 'tahun_anggaran')->all()),
                SelectFilter::make('kategori')
                    ->label('Kategori')
                    ->options(fn (): array => KategoriPelatihan::query()->orderBy('nama_kategori')->pluck('nama_kategori', 'id')->all())
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'],
                        fn (Builder $q, $kategori) => $q->whereHas('judulPelatihan', fn (Builder $q) => $q->where('kategori_pelatihan_id', $kategori)),
                    )),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(StatusPelatihan::class),
                SelectFilter::make('tipe_lokasi')
                    ->label('Lokasi')
                    ->options(TipeLokasi::class),
            ])
            ->recordActions([
                UbahStatusPelatihanAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([])
            ->emptyStateHeading('Belum ada pelatihan')
            ->emptyStateDescription('Buat pelatihan, lalu buka pendaftarannya agar muncul di form registrasi peserta.');
    }
}
