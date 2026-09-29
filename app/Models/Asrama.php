<?php

namespace App\Models;

use App\Models\Concerns\BisaNonaktif;
use Database\Factories\AsramaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nama_asrama', 'is_aktif'])]
class Asrama extends Model
{
    use BisaNonaktif;

    /** @use HasFactory<AsramaFactory> */
    use HasFactory;

    protected $table = 'asrama';

    /**
     * @return HasMany<KamarAsrama, $this>
     */
    public function kamar(): HasMany
    {
        return $this->hasMany(KamarAsrama::class);
    }
}
