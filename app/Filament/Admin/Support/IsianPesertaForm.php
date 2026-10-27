<?php

namespace App\Filament\Admin\Support;

use App\Enums\JenisKelamin;
use App\Models\Desa;
use App\Models\Jabatan;
use App\Models\Peserta;
use App\Models\StatusPtkp;
use App\Support\IsianPeserta;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Isian data peserta untuk admin, sama dengan kolom form registrasi (IsianPeserta::KOLOM).
 * Dipakai modal verifikasi dan halaman ubah Peserta. Jabatan & PTKP nonaktif yang sedang
 * dipakai tetap dapat dipilih.
 */
class IsianPesertaForm
{
    /**
     * @param  (Closure(string): ?string)|null  $petunjuk  Petunjuk per kolom, mis. "Data lama: …" saat verifikasi.
     * @return list<Section>
     */
    public static function bagian(?Closure $petunjuk = null): array
    {
        $isian = fn (Field $field): Field => $petunjuk
            ? $field->hint($petunjuk($field->getName()))->hintColor('warning')
            : $field;

        return [
            Section::make('Identitas')
                ->columns(2)
                ->schema([
                    $isian(TextInput::make('nik')->label('NIK')->required()->length(16)),
                    $isian(TextInput::make('nama_lengkap')->label('Nama lengkap')->required()->maxLength(255)),
                    $isian(Select::make('jenis_kelamin')->label('Jenis kelamin')->options(JenisKelamin::class)->required()),
                    $isian(TextInput::make('tempat_lahir')->label('Tempat lahir')->required()->maxLength(255)),
                    $isian(DatePicker::make('tanggal_lahir')->label('Tanggal lahir')->required()),
                    $isian(Select::make('agama')->label('Agama')->options(array_combine(Peserta::AGAMA, Peserta::AGAMA))->required()),
                    $isian(TextInput::make('no_hp')->label('Nomor HP')->required()->tel()),
                    $isian(TextInput::make('email')->label('Email')->email()),
                    $isian(Select::make('jenjang_pendidikan')->label('Pendidikan terakhir')->options(array_combine(Peserta::JENJANG_PENDIDIKAN, Peserta::JENJANG_PENDIDIKAN))->required()),
                    $isian(TextInput::make('jurusan_pendidikan')->label('Jurusan')),
                    $isian(Textarea::make('alamat_domisili')->label('Alamat domisili')->required()->rows(2)->columnSpanFull()),
                ]),
            Section::make('Jabatan, desa & pajak')
                ->columns(2)
                ->schema([
                    $isian(Select::make('jabatan_id')
                        ->label('Jabatan')
                        ->options(fn (Get $get): array => self::masterAktif(Jabatan::class, $get('jabatan_id'))->orderBy('urutan')->pluck('nama_jabatan', 'id')->all())
                        ->required()
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(fn (Set $set) => $set('periode_jabatan', null))),
                    // Hanya untuk jabatan berperiode, mis. kepala desa (PF-25).
                    $isian(Select::make('periode_jabatan')
                        ->label('Periode jabatan')
                        ->options(Peserta::PERIODE_JABATAN)
                        ->required()
                        ->validationMessages(['required' => 'Pilih periode jabatan.'])
                        ->visible(fn (Get $get): bool => Jabatan::berperiode($get('jabatan_id')))),
                    $isian(DatePicker::make('waktu_pelantikan')->label('Tanggal pelantikan')->required()),
                    $isian(Select::make('desa_id')
                        ->label('Desa')
                        ->searchable()
                        ->getSearchResultsUsing(fn (string $search): array => Desa::query()
                            ->where('nama', 'like', "%{$search}%")
                            ->orderBy('nama')
                            ->limit(50)
                            ->pluck('id')
                            ->mapWithKeys(fn (int $id): array => [$id => IsianPeserta::tampilkan('desa_id', $id)])
                            ->all())
                        ->getOptionLabelUsing(fn (mixed $value): string => IsianPeserta::tampilkan('desa_id', $value))
                        ->required()
                        ->live()
                        ->columnSpanFull()),
                    $isian(Textarea::make('alamat_kantor_desa')->label('Alamat kantor desa')->required()->rows(2)->columnSpanFull()),
                    $isian(TextInput::make('npwp')->label('NPWP')),
                    $isian(Select::make('status_ptkp_id')
                        ->label('Status PTKP')
                        ->options(fn (Get $get): array => self::masterAktif(StatusPtkp::class, $get('status_ptkp_id'))->orderBy('id')->pluck('kode', 'id')->all())
                        ->required()),
                ]),
        ];
    }

    /**
     * Data master aktif, ditambah nilai yang sedang dipilih (bisa sudah nonaktif).
     *
     * @param  class-string<Model>  $model
     * @return Builder<Model>
     */
    public static function masterAktif(string $model, mixed $terpilih)
    {
        return $model::query()->where(fn ($q) => $q->where('is_aktif', true)->orWhere('id', $terpilih));
    }
}
