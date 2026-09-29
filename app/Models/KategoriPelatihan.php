<?php

namespace App\Models;

use App\Models\Concerns\BisaNonaktif;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nama_kategori', 'is_aktif'])]
class KategoriPelatihan extends Model
{
    use BisaNonaktif;

    protected $table = 'kategori_pelatihan';

    /**
     * @return HasMany<JudulPelatihan, $this>
     */
    public function judulPelatihan(): HasMany
    {
        return $this->hasMany(JudulPelatihan::class);
    }
}
