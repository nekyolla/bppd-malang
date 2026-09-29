<?php

namespace App\Filament\Admin\Resources\Pendaftaran\Schemas;

use App\Enums\StatusPendaftaran;
use App\Models\PelatihanPeserta;
use App\Services\PendaftaranService;
use App\Support\FormatTanggal;
use App\Support\IsianPeserta;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PendaftaranInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Pendaftaran')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('pelatihan.nama_tampilan')
                            ->label('Pelatihan')
                            ->columnSpanFull(),
                        TextEntry::make('status')
                            ->label('Status')
                            ->badge(),
                        TextEntry::make('created_at')
                            ->label('Tanggal daftar')
                            ->dateTime('j F Y, H:i'),
                        TextEntry::make('sumberDana.nama')
                            ->label('Sumber dana')
                            ->suffix(fn (PelatihanPeserta $record): string => filled($record->sumber_dana_keterangan) ? " ({$record->sumber_dana_keterangan})" : ''),
                        TextEntry::make('diverifikasi_pada')
                            ->label('Diverifikasi')
                            ->state(fn (PelatihanPeserta $record): ?string => $record->diverifikasi_pada
                                ? $record->diverifikasi_pada->translatedFormat('j F Y, H:i').' oleh '.$record->diverifikasiOleh?->username
                                : null)
                            ->placeholder('Belum'),
                        TextEntry::make('catatan_panitia')
                            ->label('Catatan panitia')
                            ->placeholder('–')
                            ->columnSpanFull(),
                    ]),
                Section::make('Berkas')
                    ->columns(3)
                    ->schema(collect(['ktp' => 'KTP', 'foto' => 'Pas foto', 'surat-tugas' => 'Surat tugas'])
                        ->map(fn (string $label, string $jenis): TextEntry => TextEntry::make("berkas_{$jenis}")
                            ->label($label)
                            ->state('Buka')
                            ->url(fn (PelatihanPeserta $record): string => route('berkas.pendaftaran', [$record, $jenis]), shouldOpenInNewTab: true)
                            ->color('primary'))
                        ->values()
                        ->all()),
                Section::make('Isian berbeda dari data lama')
                    ->description('Peserta ini pernah terdaftar. Periksa perubahan berikut saat verifikasi.')
                    ->visible(fn (PelatihanPeserta $record): bool => $record->status === StatusPendaftaran::Terdaftar
                        && app(PendaftaranService::class)->perbedaanIsian($record) !== [])
                    ->schema([
                        RepeatableEntry::make('perbedaan')
                            ->hiddenLabel()
                            ->state(fn (PelatihanPeserta $record): array => collect(app(PendaftaranService::class)->perbedaanIsian($record))
                                ->map(fn (array $nilai, string $kolom): array => [
                                    'kolom' => ucfirst(IsianPeserta::LABEL[$kolom]),
                                    'lama' => IsianPeserta::tampilkan($kolom, $nilai[0]),
                                    'baru' => IsianPeserta::tampilkan($kolom, $nilai[1]),
                                ])->values()->all())
                            ->columns(3)
                            ->schema([
                                TextEntry::make('kolom')->label('Isian'),
                                TextEntry::make('lama')->label('Data lama'),
                                TextEntry::make('baru')->label('Isian baru')->color('warning'),
                            ]),
                    ]),
                Section::make(fn (PelatihanPeserta $record): string => $record->status === StatusPendaftaran::Terdaftar ? 'Isian peserta' : 'Data peserta')
                    ->columns(2)
                    ->schema(collect(IsianPeserta::KOLOM)
                        ->map(fn (string $kolom): TextEntry => TextEntry::make("isian_{$kolom}")
                            ->label(ucfirst(IsianPeserta::LABEL[$kolom]))
                            ->state(fn (PelatihanPeserta $record): string => IsianPeserta::tampilkan(
                                $kolom,
                                $record->status === StatusPendaftaran::Terdaftar
                                    ? app(PendaftaranService::class)->isianVerifikasi($record)[$kolom]
                                    : IsianPeserta::dariPeserta($record->peserta)[$kolom],
                            ))
                            ->columnSpan(in_array($kolom, ['alamat_domisili', 'alamat_kantor_desa', 'desa_id'], true) ? 'full' : 1))
                        ->all()),
                Section::make('Snapshot saat pelatihan')
                    ->description('Diambil saat verifikasi; laporan membaca data ini.')
                    ->columns(2)
                    ->visible(fn (PelatihanPeserta $record): bool => $record->status !== StatusPendaftaran::Terdaftar && $record->jabatan_id_saat_pelatihan !== null)
                    ->schema([
                        TextEntry::make('jabatanSaatPelatihan.nama_jabatan')->label('Jabatan'),
                        TextEntry::make('tahun_menjabat')->label('Tahun menjabat')->suffix(' tahun'),
                        TextEntry::make('desaSaatPelatihan.nama')
                            ->label('Desa')
                            ->state(fn (PelatihanPeserta $record): string => IsianPeserta::tampilkan('desa_id', $record->desa_id_saat_pelatihan)),
                        TextEntry::make('statusPtkpSaatPelatihan.kode')->label('Status PTKP'),
                        TextEntry::make('waktu_pelantikan_saat_pelatihan')
                            ->label('Tanggal pelantikan')
                            ->formatStateUsing(fn (PelatihanPeserta $record): string => FormatTanggal::tanggal($record->waktu_pelantikan_saat_pelatihan)),
                    ]),
            ]);
    }
}
