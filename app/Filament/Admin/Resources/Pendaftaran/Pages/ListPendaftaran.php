<?php

namespace App\Filament\Admin\Resources\Pendaftaran\Pages;

use App\Enums\StatusPelatihan;
use App\Enums\StatusPendaftaran;
use App\Filament\Admin\Resources\Pelatihan\Pages\ManagePendaftar;
use App\Filament\Admin\Resources\Pendaftaran\PendaftaranResource;
use App\Models\Pelatihan;
use App\Support\FormatTanggal;
use Filament\Resources\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Menu Pendaftaran: satu baris per pelatihan beserta jumlah pendaftar per status.
 * Klik pelatihan membuka sub-halaman Pendaftar-nya (PF-17).
 */
class ListPendaftaran extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = PendaftaranResource::class;

    protected string $view = 'filament.admin.pages.tabel';

    protected static ?string $title = 'Pendaftaran';

    /**
     * Halaman induk menu ini sendiri, jadi breadcrumb tidak perlu.
     *
     * @return array<string>
     */
    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function getSubheading(): ?string
    {
        return 'Pilih pelatihan untuk memverifikasi pendaftarnya.';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Pelatihan::query()
                ->with('judulPelatihan')
                ->withCount([
                    'pendaftaran as jumlah_menunggu' => fn (Builder $q) => $q->where('status', StatusPendaftaran::Terdaftar),
                    'pendaftaran as jumlah_terverifikasi' => fn (Builder $q) => $q->whereIn('status', [StatusPendaftaran::Terverifikasi, StatusPendaftaran::Selesai]),
                    'pendaftaran as jumlah_batal' => fn (Builder $q) => $q->where('status', StatusPendaftaran::Batal),
                    'pendaftaran as jumlah_pendaftar',
                ]))
            ->columns([
                TextColumn::make('nama_tampilan')
                    ->label('Pelatihan')
                    ->description(fn (Pelatihan $record): string => FormatTanggal::rentang($record->tanggal_mulai, $record->tanggal_selesai))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query
                        ->whereHas('judulPelatihan', fn (Builder $q) => $q->where('judul', 'like', "%{$search}%")))
                    ->wrap(),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('jumlah_menunggu')
                    ->label('Menunggu verifikasi')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'warning' : 'gray'),
                TextColumn::make('jumlah_terverifikasi')->label('Terverifikasi/selesai'),
                TextColumn::make('jumlah_batal')->label('Batal'),
                TextColumn::make('jumlah_pendaftar')->label('Total'),
            ])
            // Pelatihan yang sedang berjalan/dibuka di atas, lalu yang terbaru.
            ->defaultSort(fn (Builder $query): Builder => $query
                ->orderByRaw('FIELD(status, ?, ?, ?, ?)', [StatusPelatihan::Berjalan->value, StatusPelatihan::Dibuka->value, StatusPelatihan::Draft->value, StatusPelatihan::Selesai->value])
                ->orderByDesc('tanggal_mulai'))
            ->filters([
                SelectFilter::make('tahun_anggaran')
                    ->label('Tahun')
                    ->options(fn (): array => Pelatihan::query()->distinct()->orderByDesc('tahun_anggaran')->pluck('tahun_anggaran', 'tahun_anggaran')->all()),
                SelectFilter::make('status')
                    ->label('Status pelatihan')
                    ->options(StatusPelatihan::class),
            ])
            ->recordUrl(fn (Pelatihan $record): string => ManagePendaftar::getUrl(['record' => $record]))
            ->emptyStateHeading('Belum ada pelatihan')
            ->emptyStateDescription('Buat pelatihan di menu Pelatihan, lalu buka pendaftarannya.');
    }
}
