<?php

namespace App\Filament\Admin\Resources\Peserta\Schemas;

use App\Filament\Admin\Support\PratinjauBerkas;
use App\Models\Peserta;
use App\Support\IsianPeserta;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Detail peserta (FR-PST-03): tiga kolom ringkas (identitas · jabatan & desa · berkas), label
 * sebaris dengan nilainya. NIK tampil penuh karena halaman ini hanya untuk akun internal
 * (CLAUDE.md aturan 9).
 */
class PesertaInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Grid::make(['default' => 1, 'md' => 2, 'xl' => 3])->schema([
                    self::bagian('Identitas', [
                        'nik', 'jenis_kelamin', 'tempat_lahir', 'tanggal_lahir', 'agama',
                        'no_hp', 'email', 'jenjang_pendidikan', 'jurusan_pendidikan', 'alamat_domisili',
                    ]),
                    self::bagian('Jabatan, desa & pajak', [
                        'jabatan_id', 'periode_jabatan', 'waktu_pelantikan', 'desa_id', 'alamat_kantor_desa', 'npwp', 'status_ptkp_id',
                    ]),
                    Section::make('Berkas')
                        ->compact()
                        ->schema(fn (Peserta $record): array => [PratinjauBerkas::peserta($record)])
                        ->columnSpan(['md' => 2, 'xl' => 1]),
                ]),
            ]);
    }

    /**
     * @param  list<string>  $kolom  Kolom IsianPeserta, ditampilkan seperti di form registrasi.
     */
    private static function bagian(string $judul, array $kolom): Section
    {
        return Section::make($judul)
            ->compact()
            ->inlineLabel()
            ->extraAttributes(['class' => 'fi-ringkas'])
            ->schema(collect($kolom)
                ->map(fn (string $nama): TextEntry => TextEntry::make("isian_{$nama}")
                    ->label(ucfirst(IsianPeserta::LABEL[$nama]))
                    ->state(fn (Peserta $record): string => IsianPeserta::tampilkan($nama, IsianPeserta::dariPeserta($record)[$nama]))
                    // Periode hanya ada untuk jabatan berperiode (PF-25).
                    ->visible(fn (Peserta $record): bool => $nama !== 'periode_jabatan' || filled($record->periode_jabatan)))
                ->all());
    }
}
