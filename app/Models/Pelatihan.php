<?php

namespace App\Models;

use App\Enums\StatusPelatihan;
use App\Enums\TipeLokasi;
use Database\Factories\PelatihanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu penyelenggaraan pelatihan (judul + tahun anggaran + batch).
 * `status` tidak fillable: hanya berubah lewat service (CLAUDE.md aturan 3).
 */
#[Fillable([
    'judul_pelatihan_id', 'tahun_anggaran', 'batch_ke', 'tanggal_mulai', 'tanggal_selesai',
    'tipe_lokasi', 'keterangan_lokasi',
])]
class Pelatihan extends Model
{
    public const JP_PER_HARI = 10;

    /** @use HasFactory<PelatihanFactory> */
    use HasFactory;

    protected $table = 'pelatihan';

    protected $attributes = [
        'status' => 'draft',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tahun_anggaran' => 'integer',
            'batch_ke' => 'integer',
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
            'tipe_lokasi' => TipeLokasi::class,
            'status' => StatusPelatihan::class,
        ];
    }

    /**
     * Pelatihan yang tampil di form registrasi publik (PF-02).
     */
    #[Scope]
    protected function menerimaPendaftaran(Builder $query): void
    {
        $query->whereIn($this->qualifyColumn('status'), StatusPelatihan::yangMenerimaPendaftaran());
    }

    /**
     * `{judul} {tahun} Batch {batch_ke}` (FR-PLT-02).
     *
     * @return Attribute<string, never>
     */
    protected function namaTampilan(): Attribute
    {
        return Attribute::get(fn (): string => "{$this->judulPelatihan->judul} {$this->tahun_anggaran} Batch {$this->batch_ke}");
    }

    /**
     * @return Attribute<int, never>
     */
    protected function jumlahHari(): Attribute
    {
        return Attribute::get(fn (): int => (int) $this->tanggal_mulai->diffInDays($this->tanggal_selesai) + 1);
    }

    /**
     * Jumlah jam pelajaran: jumlah hari × 10 (FR-PLT-04).
     *
     * @return Attribute<int, never>
     */
    protected function jumlahJp(): Attribute
    {
        return Attribute::get(fn (): int => $this->jumlah_hari * self::JP_PER_HARI);
    }

    /**
     * @return BelongsTo<JudulPelatihan, $this>
     */
    public function judulPelatihan(): BelongsTo
    {
        return $this->belongsTo(JudulPelatihan::class);
    }

    /**
     * @return HasMany<KelasPelatihan, $this>
     */
    public function kelas(): HasMany
    {
        return $this->hasMany(KelasPelatihan::class);
    }

    /**
     * @return HasMany<PelatihanPeserta, $this>
     */
    public function pendaftaran(): HasMany
    {
        return $this->hasMany(PelatihanPeserta::class);
    }

    /**
     * @return HasMany<PenggunaanKamar, $this>
     */
    public function penggunaanKamar(): HasMany
    {
        return $this->hasMany(PenggunaanKamar::class);
    }
}
