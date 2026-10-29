<?php

namespace App\Models;

use App\Models\Concerns\BisaNonaktif;
use Database\Factories\JabatanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Once;

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

    protected static function booted(): void
    {
        // Tanda periode dicabut: periode di profil peserta tidak berlaku lagi (PF-25).
        // Snapshot `*_saat_pelatihan` tidak disentuh (CLAUDE.md aturan 6).
        static::updated(function (Jabatan $jabatan): void {
            if ($jabatan->wasChanged('memiliki_periode') && ! $jabatan->memiliki_periode) {
                $jabatan->peserta()->whereNotNull('periode_jabatan')->update(['periode_jabatan' => null]);
            }
        });

        // Memo berperiode() berlaku per request; dikosongkan begitu ada jabatan berubah.
        static::saved(fn () => Once::flush());
        static::deleted(fn () => Once::flush());
    }

    /**
     * Jabatan dengan periode (mis. kepala desa) mewajibkan isian periode ke-1/ke-2 (PF-25).
     * Dipanggil berulang kali per render form, jadi daftar id-nya dimemo.
     */
    public static function berperiode(mixed $id): bool
    {
        return filled($id) && is_numeric($id) && in_array((int) $id, self::idBerperiode(), true);
    }

    /**
     * @return list<int>
     */
    private static function idBerperiode(): array
    {
        return once(fn (): array => self::query()->where('memiliki_periode', true)->pluck('id')->map(fn (mixed $id): int => (int) $id)->all());
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
