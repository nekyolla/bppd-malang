<?php

namespace App\Models;

use App\Enums\JenisKelamin;
use App\Models\Concerns\Diaudit;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Aparatur desa peserta pelatihan, satu baris per NIK (ARCHITECTURE §5.2).
 * Peserta tidak punya akun; data masuk lewat form registrasi publik.
 */
#[Fillable([
    'nik', 'nama_lengkap', 'jenis_kelamin', 'tempat_lahir', 'tanggal_lahir', 'agama',
    'alamat_domisili', 'no_hp', 'email', 'jenjang_pendidikan', 'jurusan_pendidikan',
    'jabatan_id', 'waktu_pelantikan', 'desa_id', 'alamat_kantor_desa', 'npwp',
    'status_ptkp_id', 'foto', 'file_ktp',
])]
class Peserta extends Model
{
    use Diaudit;

    protected $table = 'peserta';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'jenis_kelamin' => JenisKelamin::class,
            'tanggal_lahir' => 'date',
            'waktu_pelantikan' => 'date',
        ];
    }

    /**
     * Tahun menjabat per tahun kalender: tahun − tahun pelantikan + 1 (BR-08).
     */
    public static function hitungTahunMenjabat(CarbonInterface $waktuPelantikan, int $tahun): int
    {
        return $tahun - $waktuPelantikan->year + 1;
    }

    public function tahunMenjabatPada(int $tahun): int
    {
        return self::hitungTahunMenjabat($this->waktu_pelantikan, $tahun);
    }

    /**
     * Tahun menjabat pada tahun berjalan, untuk form registrasi (FR-REG-06).
     *
     * @return Attribute<int, never>
     */
    protected function tahunMenjabat(): Attribute
    {
        return Attribute::get(fn (): int => $this->tahunMenjabatPada(now()->year));
    }

    /**
     * @return BelongsTo<Jabatan, $this>
     */
    public function jabatan(): BelongsTo
    {
        return $this->belongsTo(Jabatan::class);
    }

    /**
     * @return BelongsTo<Desa, $this>
     */
    public function desa(): BelongsTo
    {
        return $this->belongsTo(Desa::class);
    }

    /**
     * @return BelongsTo<StatusPtkp, $this>
     */
    public function statusPtkp(): BelongsTo
    {
        return $this->belongsTo(StatusPtkp::class);
    }

    /**
     * @return HasMany<PelatihanPeserta, $this>
     */
    public function pendaftaran(): HasMany
    {
        return $this->hasMany(PelatihanPeserta::class);
    }
}
