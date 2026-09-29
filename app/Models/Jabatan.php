<?php

namespace App\Models;

use App\Models\Concerns\BisaNonaktif;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nama_jabatan', 'urutan', 'is_aktif'])]
class Jabatan extends Model
{
    use BisaNonaktif;

    protected $table = 'jabatan';

    /**
     * @return HasMany<Peserta, $this>
     */
    public function peserta(): HasMany
    {
        return $this->hasMany(Peserta::class);
    }
}
