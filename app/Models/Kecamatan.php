<?php

namespace App\Models;

use Database\Factories\KecamatanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['kab_kota_id', 'kode', 'nama'])]
class Kecamatan extends Model
{
    /** @use HasFactory<KecamatanFactory> */
    use HasFactory;

    protected $table = 'kecamatan';

    /**
     * @return BelongsTo<KabKota, $this>
     */
    public function kabKota(): BelongsTo
    {
        return $this->belongsTo(KabKota::class);
    }

    /**
     * @return HasMany<Desa, $this>
     */
    public function desa(): HasMany
    {
        return $this->hasMany(Desa::class);
    }
}
