<?php

namespace App\Models;

use App\Models\Concerns\Diaudit;
use Database\Factories\KelasPelatihanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['pelatihan_id', 'nama_kelas'])]
class KelasPelatihan extends Model
{
    use Diaudit;

    /** @use HasFactory<KelasPelatihanFactory> */
    use HasFactory;

    protected $table = 'kelas_pelatihan';

    /**
     * @return BelongsTo<Pelatihan, $this>
     */
    public function pelatihan(): BelongsTo
    {
        return $this->belongsTo(Pelatihan::class);
    }

    /**
     * @return HasMany<PelatihanPeserta, $this>
     */
    public function pendaftaran(): HasMany
    {
        return $this->hasMany(PelatihanPeserta::class);
    }

    /**
     * @return HasMany<LembarPresensi, $this>
     */
    public function lembarPresensi(): HasMany
    {
        return $this->hasMany(LembarPresensi::class);
    }
}
