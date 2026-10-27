<?php

namespace App\Models;

use App\Enums\StatusPendaftaran;
use App\Models\Concerns\Diaudit;
use App\Services\StatistikService;
use Database\Factories\PelatihanPesertaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Pendaftaran satu peserta di satu pelatihan (ARCHITECTURE §5.5).
 * Status, snapshot `*_saat_pelatihan`, data verifikasi/pembatalan, `menggantikan_id`,
 * dan `kelas_pelatihan_id` tidak fillable: hanya diisi lewat service.
 */
#[Fillable([
    'pelatihan_id', 'peserta_id', 'data_isian', 'file_surat_tugas',
    'sumber_dana_id', 'sumber_dana_keterangan', 'catatan_panitia',
])]
class PelatihanPeserta extends Model
{
    use Diaudit;

    /** @use HasFactory<PelatihanPesertaFactory> */
    use HasFactory;

    protected $table = 'pelatihan_peserta';

    /**
     * Angka dashboard dihitung ulang setelah data berubah (StatistikService).
     */
    protected static function booted(): void
    {
        static::saved(fn () => StatistikService::bersihkanCache());
        static::deleted(fn () => StatistikService::bersihkanCache());
    }

    protected $attributes = [
        'status' => 'terdaftar',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data_isian' => 'array',
            'waktu_pelantikan_saat_pelatihan' => 'date',
            'periode_jabatan_saat_pelatihan' => 'integer',
            'status' => StatusPendaftaran::class,
            'diverifikasi_pada' => 'datetime',
            'dibatalkan_pada' => 'datetime',
        ];
    }

    /**
     * Tahun menjabat saat pelatihan: tahun mulai pelatihan − tahun pelantikan + 1 (ARCHITECTURE §6).
     * Memakai snapshot pelantikan; sebelum verifikasi memakai data peserta terkini.
     *
     * @return Attribute<int, never>
     */
    protected function tahunMenjabat(): Attribute
    {
        return Attribute::get(fn (): int => Peserta::hitungTahunMenjabat(
            $this->waktu_pelantikan_saat_pelatihan ?? $this->peserta->waktu_pelantikan,
            $this->pelatihan->tanggal_mulai->year,
        ));
    }

    /**
     * Jabatan saat pelatihan (snapshot) beserta periodenya (PF-25).
     *
     * @return Attribute<?string, never>
     */
    protected function jabatanSaatPelatihanTampilan(): Attribute
    {
        return Attribute::get(fn (): ?string => Jabatan::tampilan($this->jabatanSaatPelatihan?->nama_jabatan, $this->periode_jabatan_saat_pelatihan));
    }

    /**
     * @return BelongsTo<Pelatihan, $this>
     */
    public function pelatihan(): BelongsTo
    {
        return $this->belongsTo(Pelatihan::class);
    }

    /**
     * @return BelongsTo<Peserta, $this>
     */
    public function peserta(): BelongsTo
    {
        return $this->belongsTo(Peserta::class);
    }

    /**
     * @return BelongsTo<KelasPelatihan, $this>
     */
    public function kelasPelatihan(): BelongsTo
    {
        return $this->belongsTo(KelasPelatihan::class);
    }

    /**
     * @return BelongsTo<SumberDana, $this>
     */
    public function sumberDana(): BelongsTo
    {
        return $this->belongsTo(SumberDana::class);
    }

    /**
     * @return BelongsTo<Jabatan, $this>
     */
    public function jabatanSaatPelatihan(): BelongsTo
    {
        return $this->belongsTo(Jabatan::class, 'jabatan_id_saat_pelatihan');
    }

    /**
     * @return BelongsTo<Desa, $this>
     */
    public function desaSaatPelatihan(): BelongsTo
    {
        return $this->belongsTo(Desa::class, 'desa_id_saat_pelatihan');
    }

    /**
     * @return BelongsTo<StatusPtkp, $this>
     */
    public function statusPtkpSaatPelatihan(): BelongsTo
    {
        return $this->belongsTo(StatusPtkp::class, 'status_ptkp_id_saat_pelatihan');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function diverifikasiOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diverifikasi_oleh');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function dibatalkanOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibatalkan_oleh');
    }

    /**
     * Pendaftaran batal yang digantikan oleh pendaftaran ini.
     *
     * @return BelongsTo<PelatihanPeserta, $this>
     */
    public function menggantikan(): BelongsTo
    {
        return $this->belongsTo(self::class, 'menggantikan_id');
    }

    /**
     * Pendaftaran yang menggantikan pendaftaran (batal) ini.
     *
     * @return HasOne<PelatihanPeserta, $this>
     */
    public function pengganti(): HasOne
    {
        return $this->hasOne(self::class, 'menggantikan_id');
    }

    /**
     * @return HasOne<SertifikatPeserta, $this>
     */
    public function sertifikat(): HasOne
    {
        return $this->hasOne(SertifikatPeserta::class);
    }

    /**
     * @return HasOne<AsramaPeserta, $this>
     */
    public function penempatanKamar(): HasOne
    {
        return $this->hasOne(AsramaPeserta::class);
    }
}
