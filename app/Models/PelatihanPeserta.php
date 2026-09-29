<?php

namespace App\Models;

use App\Enums\StatusPendaftaran;
use App\Enums\StatusPresensi;
use App\Models\Concerns\Diaudit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Pendaftaran satu peserta di satu pelatihan (ARCHITECTURE §5.5).
 * Status, snapshot `*_saat_pelatihan`, data verifikasi/pembatalan, dan
 * `menggantikan_id` tidak fillable: hanya diisi lewat service.
 */
#[Fillable([
    'pelatihan_id', 'peserta_id', 'kelas_pelatihan_id', 'data_isian', 'file_surat_tugas',
    'sumber_dana_id', 'sumber_dana_keterangan', 'catatan_panitia', 'nilai_pretest', 'nilai_posttest',
])]
class PelatihanPeserta extends Model
{
    use Diaudit;

    protected $table = 'pelatihan_peserta';

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
            'status' => StatusPendaftaran::class,
            'diverifikasi_pada' => 'datetime',
            'dibatalkan_pada' => 'datetime',
            'nilai_pretest' => 'decimal:2',
            'nilai_posttest' => 'decimal:2',
        ];
    }

    /**
     * Memuat `jumlah_hadir` dalam satu query untuk tabel/rekap.
     */
    #[Scope]
    protected function denganJumlahHadir(Builder $query): void
    {
        $query->withCount(['presensi as jumlah_hadir' => fn (Builder $q) => $q->where('status', StatusPresensi::Hadir)]);
    }

    /**
     * Tahun menjabat saat pelatihan: tahun mulai pelatihan − tahun pelantikan + 1 (ARCHITECTURE §6).
     *
     * @return Attribute<int, never>
     */
    protected function tahunMenjabat(): Attribute
    {
        return Attribute::get(fn (): int => $this->peserta->tahunMenjabatPada($this->pelatihan->tanggal_mulai->year));
    }

    /**
     * Memakai hasil `denganJumlahHadir()` bila dimuat, selain itu menghitung langsung.
     *
     * @return Attribute<int, never>
     */
    protected function jumlahHadir(): Attribute
    {
        return Attribute::get(fn (?int $value): int => $value
            ?? $this->presensi()->where('status', StatusPresensi::Hadir)->count());
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
     * @return HasMany<Presensi, $this>
     */
    public function presensi(): HasMany
    {
        return $this->hasMany(Presensi::class);
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
