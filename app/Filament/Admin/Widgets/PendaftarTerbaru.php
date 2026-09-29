<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Resources\Pendaftaran\PendaftaranResource;
use App\Filament\Admin\Widgets\Concerns\MembacaFilterTahun;
use App\Models\PelatihanPeserta;
use App\Support\Masking;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * 10 pendaftar terbaru dengan tautan ke detail (FR-DSB-05).
 */
class PendaftarTerbaru extends TableWidget
{
    use MembacaFilterTahun;

    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Pendaftar terbaru')
            ->query(fn (): Builder => PelatihanPeserta::query()
                ->with(['peserta.desa.kecamatan.kabKota', 'pelatihan.judulPelatihan'])
                ->when($this->tahun(), fn (Builder $q, int $tahun) => $q->whereHas('pelatihan', fn (Builder $q) => $q->where('tahun_anggaran', $tahun)))
                ->latest()
                ->limit(10))
            ->columns([
                TextColumn::make('peserta.nama_lengkap')
                    ->label('Nama')
                    ->description(fn (PelatihanPeserta $record): string => Masking::nik($record->peserta->nik)),
                TextColumn::make('peserta.desa.nama')
                    ->label('Desa')
                    ->description(fn (PelatihanPeserta $record): string => $record->peserta->desa->kecamatan->kabKota->nama),
                TextColumn::make('pelatihan.nama_tampilan')->label('Pelatihan')->wrap(),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('created_at')->label('Tanggal daftar')->dateTime('j M Y, H:i'),
            ])
            ->recordUrl(fn (PelatihanPeserta $record): string => PendaftaranResource::getUrl('view', ['record' => $record]))
            ->headerActions([
                Action::make('semua')
                    ->label('Lihat semua')
                    ->link()
                    ->url(PendaftaranResource::getUrl('index')),
            ])
            ->paginated(false)
            ->emptyStateHeading('Belum ada pendaftar');
    }
}
