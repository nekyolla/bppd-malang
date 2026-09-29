<?php

namespace App\Models;

use App\Models\Concerns\BisaNonaktif;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['kategori_pelatihan_id', 'judul', 'deskripsi', 'is_aktif'])]
class JudulPelatihan extends Model
{
    use BisaNonaktif;

    protected $table = 'judul_pelatihan';

    /**
     * @return BelongsTo<KategoriPelatihan, $this>
     */
    public function kategoriPelatihan(): BelongsTo
    {
        return $this->belongsTo(KategoriPelatihan::class);
    }

    /**
     * @return HasMany<Pelatihan, $this>
     */
    public function pelatihan(): HasMany
    {
        return $this->hasMany(Pelatihan::class);
    }
}
