<?php

namespace App\Models;

use App\Models\Concerns\BisaNonaktif;
use Database\Factories\KamarAsramaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['asrama_id', 'no_kamar', 'kapasitas', 'is_aktif'])]
class KamarAsrama extends Model
{
    use BisaNonaktif;

    /** @use HasFactory<KamarAsramaFactory> */
    use HasFactory;

    protected $table = 'kamar_asrama';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kapasitas' => 'integer',
        ];
    }

    /**
     * `{asrama}-{no_kamar}`, mis. "Anggrek-01" (DESIGN_SYSTEM §7).
     *
     * @return Attribute<string, never>
     */
    protected function namaTampilan(): Attribute
    {
        return Attribute::get(fn (): string => "{$this->asrama->nama_asrama}-{$this->no_kamar}");
    }

    /**
     * @return BelongsTo<Asrama, $this>
     */
    public function asrama(): BelongsTo
    {
        return $this->belongsTo(Asrama::class);
    }

    /**
     * @return HasMany<PenggunaanKamar, $this>
     */
    public function penggunaan(): HasMany
    {
        return $this->hasMany(PenggunaanKamar::class);
    }
}
