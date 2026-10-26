<?php

namespace App\Filament\Admin\Resources\Pendaftaran\Tables;

use App\Enums\StatusPendaftaran;
use App\Filament\Admin\Actions\AturKelasAction;
use App\Filament\Admin\Actions\BatalkanAction;
use App\Filament\Admin\Actions\HapusPendaftaranAction;
use App\Filament\Admin\Actions\KembalikanPendaftaranAction;
use App\Filament\Admin\Actions\VerifikasiAction;
use App\Filament\Admin\Actions\VerifikasiMassalAction;
use App\Filament\Admin\Resources\Pendaftaran\PendaftaranResource;
use App\Filament\Admin\Support\FilterWilayah;
use App\Models\Desa;
use App\Models\PelatihanPeserta;
use App\Services\PendaftaranService;
use App\Support\Masking;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Tabel pendaftar satu pelatihan, dipakai sub-halaman Pendaftar (PF-17).
 */
class PendaftaranTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordTitle(fn (PelatihanPeserta $record): string => $record->peserta->nama_lengkap)
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'peserta.desa.kecamatan.kabKota',
                'desaSaatPelatihan.kecamatan.kabKota',
                'pelatihan.judulPelatihan',
                'kelasPelatihan',
                'menggantikan.peserta',
                'pengganti.peserta',
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
                TextColumn::make('kelasPelatihan.nama_kelas')
                    ->label('Kelas')
                    ->placeholder('–')
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->description(fn (PelatihanPeserta $record): ?string => match (true) {
                        $record->status === StatusPendaftaran::Terdaftar && app(PendaftaranService::class)->perbedaanIsian($record) !== [] => 'Isian berbeda dari data lama',
                        $record->pengganti !== null => "Digantikan {$record->pengganti->peserta->nama_lengkap}",
                        $record->menggantikan !== null => "Pengganti {$record->menggantikan->peserta->nama_lengkap}",
                        default => null,
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(StatusPendaftaran::class),
                FilterWilayah::make('peserta.desa'),
            ])
            ->recordUrl(fn (PelatihanPeserta $record): string => PendaftaranResource::getUrl('view', ['record' => $record]))
            ->recordActions([
                VerifikasiAction::make(),
                ActionGroup::make([
                    ViewAction::make()->url(fn (PelatihanPeserta $record): string => PendaftaranResource::getUrl('view', ['record' => $record])),
                    AturKelasAction::make(),
                    KembalikanPendaftaranAction::make(),
                    BatalkanAction::make(),
                    HapusPendaftaranAction::make(),
                ]),
            ])
            ->toolbarActions([
                VerifikasiMassalAction::make(),
            ])
            ->emptyStateHeading('Belum ada pendaftar')
            ->emptyStateDescription('Belum ada peserta yang mendaftar di pelatihan ini. Pendaftar muncul setelah mengisi form registrasi di /daftar.');
    }

    /**
     * Desa saat pelatihan (snapshot) setelah verifikasi; sebelumnya desa di data peserta.
     */
    private static function desa(PelatihanPeserta $record): Desa
    {
        return $record->desaSaatPelatihan ?? $record->peserta->desa;
    }
}
