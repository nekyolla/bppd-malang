<?php

namespace App\Models;

use App\Models\Concerns\BisaNonaktif;
use Database\Factories\JabatanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nama_jabatan', 'urutan', 'is_aktif'])]
class Jabatan extends Model
{
    use BisaNonaktif;

    /** @use HasFactory<JabatanFactory> */
    use HasFactory;

    protected $table = 'jabatan';

    /**
     * @return HasMany<Peserta, $this>
     */
    public function peserta(): HasMany
    {
        return $this->hasMany(Peserta::class);
    }
}
