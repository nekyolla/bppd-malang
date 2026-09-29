<?php

namespace App\Filament\Admin\Resources\Pelatihan\Schemas;

use App\Enums\TipeLokasi;
use App\Filament\Admin\Support\DataMaster;
use App\Models\Pelatihan;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * Status tidak ada di form: berubah hanya lewat tombol status (CLAUDE.md aturan 3).
 */
class PelatihanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Pelatihan')
                    ->columns(2)
                    ->schema([
                        Select::make('judul_pelatihan_id')
                            ->label('Judul pelatihan')
                            ->relationship('judulPelatihan', 'judul', DataMaster::pilihanAktif('judul_pelatihan_id'))
                            ->required()
                            ->searchable()
                            ->preload()
                            ->columnSpanFull(),
                        TextInput::make('batch_ke')
                            ->label('Batch ke')
                            ->required()
                            ->integer()
                            ->minValue(1)
                            ->maxValue(255)
                            ->default(1),
                    ]),
                Section::make('Jadwal')
                    ->columns(2)
                    ->schema([
                        DatePicker::make('tanggal_mulai')
                            ->label('Tanggal mulai')
                            ->helperText('Tahun anggaran mengikuti tahun tanggal mulai.')
                            ->required()
                            ->native(false)
                            ->displayFormat('j F Y')
                            ->live(),
                        DatePicker::make('tanggal_selesai')
                            ->label('Tanggal selesai')
                            ->helperText('1 hari = 10 JP.')
                            ->required()
                            ->native(false)
                            ->displayFormat('j F Y')
                            ->afterOrEqual('tanggal_mulai'),
                    ]),
                Section::make('Lokasi')
                    ->schema([
                        ToggleButtons::make('tipe_lokasi')
                            ->label('Tempat pelatihan')
                            ->options(TipeLokasi::class)
                            ->default(TipeLokasi::Bbpd)
                            ->required()
                            ->inline()
                            ->live()
                            ->disabled(fn (?Pelatihan $record): bool => (bool) $record?->penggunaanKamar()->whereHas('penghuni')->exists())
                            ->helperText(fn (?Pelatihan $record): ?string => $record?->penggunaanKamar()->whereHas('penghuni')->exists()
                                ? 'Tidak dapat diubah karena sudah ada peserta yang ditempatkan di kamar asrama.'
                                : 'Fitur asrama hanya aktif untuk pelatihan di BBPD Malang.'),
                        TextInput::make('keterangan_lokasi')
                            ->label('Keterangan lokasi')
                            ->placeholder('Nama tempat dan kota')
                            ->maxLength(255)
                            ->visible(fn (Get $get): bool => self::diLuar($get('tipe_lokasi')))
                            ->required(fn (Get $get): bool => self::diLuar($get('tipe_lokasi'))),
                    ]),
            ]);
    }

    private static function diLuar(TipeLokasi|string|null $tipe): bool
    {
        return ($tipe instanceof TipeLokasi ? $tipe : TipeLokasi::tryFrom((string) $tipe)) === TipeLokasi::Luar;
    }
}
