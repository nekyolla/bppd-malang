<?php

namespace App\Models;

use App\Models\Concerns\BisaNonaktif;
use Database\Factories\SumberDanaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nama', 'butuh_keterangan', 'is_aktif'])]
class SumberDana extends Model
{
    use BisaNonaktif;

    /** @use HasFactory<SumberDanaFactory> */
    use HasFactory;

    protected $table = 'sumber_dana';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'butuh_keterangan' => 'boolean',
        ];
    }

    /**
     * @return HasMany<PelatihanPeserta, $this>
     */
    public function pendaftaran(): HasMany
    {
        return $this->hasMany(PelatihanPeserta::class);
    }
}
