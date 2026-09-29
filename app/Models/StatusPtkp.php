<?php

namespace App\Models;

use App\Models\Concerns\BisaNonaktif;
use Database\Factories\StatusPtkpFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['kode', 'nama', 'is_aktif'])]
class StatusPtkp extends Model
{
    use BisaNonaktif;

    /** @use HasFactory<StatusPtkpFactory> */
    use HasFactory;

    protected $table = 'status_ptkp';

    /**
     * @return HasMany<Peserta, $this>
     */
    public function peserta(): HasMany
    {
        return $this->hasMany(Peserta::class);
    }
}
