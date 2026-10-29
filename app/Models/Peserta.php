<?php

namespace App\Models;

use App\Enums\JenisKelamin;
use App\Models\Concerns\Diaudit;
use Carbon\CarbonInterface;
use Database\Factories\PesertaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
    'jabatan_id', 'periode_jabatan', 'waktu_pelantikan', 'desa_id', 'alamat_kantor_desa', 'npwp',
    'status_ptkp_id', 'foto', 'file_ktp',
])]
class Peserta extends Model
{
    public const AGAMA = ['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha', 'Konghucu', 'Kepercayaan terhadap Tuhan YME'];

    public const JENJANG_PENDIDIKAN = ['SD/Sederajat', 'SMP/Sederajat', 'SMA/Sederajat', 'D1', 'D2', 'D3', 'D4', 'S1', 'S2', 'S3'];

    /**
     * Jenjang perguruan tinggi: hanya jenjang ini yang punya (dan wajib mengisi) jurusan (PF-28).
     */
    public const JENJANG_KULIAH = ['D1', 'D2', 'D3', 'D4', 'S1', 'S2', 'S3'];

    public static function jenjangKuliah(?string $jenjang): bool
    {
        return in_array($jenjang, self::JENJANG_KULIAH, true);
    }

    /**
     * Pilihan periode untuk jabatan berperiode, maksimal dua periode (PF-25).
     */
    public const PERIODE_JABATAN = [1 => 'Periode ke-1', 2 => 'Periode ke-2'];

    use Diaudit;

    /** @use HasFactory<PesertaFactory> */
    use HasFactory;

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
            'periode_jabatan' => 'integer',
        ];
    }

    /**
     * Jabatan terkini beserta periodenya, mis. "Kepala Desa (periode ke-2)" (PF-25).
     *
     * @return Attribute<?string, never>
     */
    protected function jabatanTampilan(): Attribute
    {
        return Attribute::get(fn (): ?string => Jabatan::tampilan($this->jabatan?->nama_jabatan, $this->periode_jabatan));
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
