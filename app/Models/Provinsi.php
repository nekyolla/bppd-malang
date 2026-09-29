<?php

namespace App\Models;

use Database\Factories\ProvinsiFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['kode', 'nama'])]
class Provinsi extends Model
{
    /** @use HasFactory<ProvinsiFactory> */
    use HasFactory;

    protected $table = 'provinsi';

    /**
     * @return HasMany<KabKota, $this>
     */
    public function kabKota(): HasMany
    {
        return $this->hasMany(KabKota::class);
    }
}
