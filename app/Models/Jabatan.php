<?php

namespace App\Models;

use App\Models\Concerns\BisaNonaktif;
use Database\Factories\JabatanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nama_jabatan', 'urutan', 'memiliki_periode', 'is_aktif'])]
class Jabatan extends Model
{
    use BisaNonaktif;

    /** @use HasFactory<JabatanFactory> */
    use HasFactory;

    protected $table = 'jabatan';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'memiliki_periode' => 'boolean',
        ];
    }

    /**
     * Jabatan dengan periode (mis. kepala desa) mewajibkan isian periode ke-1/ke-2 (PF-25).
     */
    public static function berperiode(mixed $id): bool
    {
        return filled($id) && is_numeric($id)
            && self::query()->whereKey($id)->where('memiliki_periode', true)->exists();
    }

    /**
     * Nama jabatan beserta periodenya, mis. "Kepala Desa (periode ke-2)".
     */
    public static function tampilan(?string $nama, ?int $periode): ?string
    {
        if ($nama === null) {
            return null;
        }

        return $periode ? "{$nama} (periode ke-{$periode})" : $nama;
    }

    /**
     * @return HasMany<Peserta, $this>
     */
    public function peserta(): HasMany
    {
        return $this->hasMany(Peserta::class);
    }
}
