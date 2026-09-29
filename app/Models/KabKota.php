<?php

namespace App\Models;

use Database\Factories\KabKotaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['provinsi_id', 'kode', 'nama'])]
class KabKota extends Model
{
    /** @use HasFactory<KabKotaFactory> */
    use HasFactory;

    protected $table = 'kab_kota';

    /**
     * @return BelongsTo<Provinsi, $this>
     */
    public function provinsi(): BelongsTo
    {
        return $this->belongsTo(Provinsi::class);
    }

    /**
     * @return HasMany<Kecamatan, $this>
     */
    public function kecamatan(): HasMany
    {
        return $this->hasMany(Kecamatan::class);
    }
}
