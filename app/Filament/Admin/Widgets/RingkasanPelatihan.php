<?php

namespace App\Filament\Admin\Widgets;

use App\Enums\StatusPendaftaran;
use App\Filament\Admin\Resources\Pelatihan\Pages\ManagePendaftar;
use App\Filament\Admin\Widgets\Concerns\MembacaFilterTahun;
use App\Models\Pelatihan;
use App\Support\FormatTanggal;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Jumlah pendaftar menunggu & terverifikasi per pelatihan (FR-DSB-05, PF-24). Hanya angka
 * agregat; rincian peserta dibuka lewat halaman Pendaftar pelatihan.
 */
class RingkasanPelatihan extends TableWidget
{
    use MembacaFilterTahun;

    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Pendaftar per pelatihan')
            ->query(fn (): Builder => Pelatihan::query()
                ->with('judulPelatihan')
                ->withCount([
                    'pendaftaran as jumlah_menunggu' => fn (Builder $q) => $q->where('status', StatusPendaftaran::Terdaftar),
                    'pendaftaran as jumlah_terverifikasi' => fn (Builder $q) => $q->whereIn('status', [StatusPendaftaran::Terverifikasi, StatusPendaftaran::Selesai]),
                ])
                ->when($this->tahun(), fn (Builder $q, int $tahun) => $q->where('tahun_anggaran', $tahun)))
            ->columns([
                TextColumn::make('nama_tampilan')->label('Pelatihan')->wrap(),
                TextColumn::make('tanggal_mulai')
                    ->label('Tanggal')
                    ->formatStateUsing(fn (Pelatihan $record): string => FormatTanggal::rentang($record->tanggal_mulai, $record->tanggal_selesai)),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('jumlah_menunggu')->label('Menunggu')->alignEnd(),
                TextColumn::make('jumlah_terverifikasi')->label('Terverifikasi')->alignEnd(),
            ])
            ->defaultSort('tanggal_mulai', 'desc')
            ->recordUrl(fn (Pelatihan $record): string => ManagePendaftar::getUrl(['record' => $record]))
            ->paginated([5, 10])
            ->defaultPaginationPageOption(5)
            ->emptyStateHeading('Belum ada pelatihan pada tahun ini');
    }
}
