<?php

namespace App\Filament\Admin\Resources\Peserta\Schemas;

use App\Filament\Admin\Support\PratinjauBerkas;
use App\Models\Peserta;
use App\Support\IsianPeserta;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Detail peserta: KTP & pas foto di kiri, data di kanan (FR-PST-03). NIK tampil penuh
 * karena halaman ini hanya untuk akun internal (CLAUDE.md aturan 9).
 */
class PesertaInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Grid::make(['default' => 1, 'lg' => 5])->schema([
                    Section::make('Berkas')
                        ->schema(fn (Peserta $record): array => [PratinjauBerkas::peserta($record)])
                        ->columnSpan(['lg' => 2]),
                    Group::make([
                        self::bagian('Identitas', [
                            'nik', 'nama_lengkap', 'jenis_kelamin', 'tempat_lahir', 'tanggal_lahir', 'agama',
                            'no_hp', 'email', 'jenjang_pendidikan', 'jurusan_pendidikan', 'alamat_domisili',
                        ]),
                        self::bagian('Jabatan, desa & pajak', [
                            'jabatan_id', 'periode_jabatan', 'waktu_pelantikan', 'desa_id', 'alamat_kantor_desa', 'npwp', 'status_ptkp_id',
                        ]),
                    ])->columnSpan(['lg' => 3]),
                ]),
            ]);
    }

    /**
     * @param  list<string>  $kolom  Kolom IsianPeserta, ditampilkan seperti di form registrasi.
     */
    private static function bagian(string $judul, array $kolom): Section
    {
        return Section::make($judul)
            ->columns(2)
            ->schema(collect($kolom)
                ->map(fn (string $nama): TextEntry => TextEntry::make("isian_{$nama}")
                    ->label(ucfirst(IsianPeserta::LABEL[$nama]))
                    ->state(fn (Peserta $record): string => IsianPeserta::tampilkan($nama, IsianPeserta::dariPeserta($record)[$nama]))
                    // Periode hanya ada untuk jabatan berperiode (PF-25).
                    ->visible(fn (Peserta $record): bool => $nama !== 'periode_jabatan' || filled($record->periode_jabatan))
                    ->columnSpan(in_array($nama, ['alamat_domisili', 'alamat_kantor_desa', 'desa_id'], true) ? 'full' : 1))
                ->all());
    }
}
