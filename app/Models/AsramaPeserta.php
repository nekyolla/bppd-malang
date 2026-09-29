<?php

namespace App\Models;

use App\Models\Concerns\Diaudit;
use Database\Factories\AsramaPesertaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Penempatan satu peserta di satu kamar. Dibuat hanya lewat AsramaService (ARCHITECTURE §7.3).
 */
#[Fillable(['pelatihan_peserta_id', 'penggunaan_kamar_id', 'ditempatkan_oleh'])]
class AsramaPeserta extends Model
{
    use Diaudit;

    /** @use HasFactory<AsramaPesertaFactory> */
    use HasFactory;

    protected $table = 'asrama_peserta';

    /**
     * @return BelongsTo<PelatihanPeserta, $this>
     */
    public function pelatihanPeserta(): BelongsTo
    {
        return $this->belongsTo(PelatihanPeserta::class);
    }

    /**
     * @return BelongsTo<PenggunaanKamar, $this>
     */
    public function penggunaanKamar(): BelongsTo
    {
        return $this->belongsTo(PenggunaanKamar::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function ditempatkanOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ditempatkan_oleh');
    }
}
