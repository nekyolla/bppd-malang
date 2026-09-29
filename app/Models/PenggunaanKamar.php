<?php

namespace App\Models;

use App\Enums\TipeKamar;
use App\Models\Concerns\Diaudit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Pemakaian satu kamar oleh satu pelatihan. `tipe` tidak fillable karena
 * ditentukan AsramaService dari penghuni pertama (CLAUDE.md aturan 5).
 */
#[Fillable(['pelatihan_id', 'kamar_asrama_id', 'disetujui_oleh', 'catatan'])]
class PenggunaanKamar extends Model
{
    use Diaudit;

    protected $table = 'penggunaan_kamar';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipe' => TipeKamar::class,
        ];
    }

    /**
     * Memuat `jumlah_penghuni` dalam satu query untuk denah kamar.
     */
    #[Scope]
    protected function denganJumlahPenghuni(Builder $query): void
    {
        $query->withCount('penghuni as jumlah_penghuni');
    }

    /**
     * Memakai hasil `denganJumlahPenghuni()` bila dimuat, selain itu menghitung langsung.
     *
     * @return Attribute<int, never>
     */
    protected function jumlahPenghuni(): Attribute
    {
        return Attribute::get(fn (?int $value): int => $value ?? $this->penghuni()->count());
    }

    /**
     * @return BelongsTo<Pelatihan, $this>
     */
    public function pelatihan(): BelongsTo
    {
        return $this->belongsTo(Pelatihan::class);
    }

    /**
     * @return BelongsTo<KamarAsrama, $this>
     */
    public function kamarAsrama(): BelongsTo
    {
        return $this->belongsTo(KamarAsrama::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function disetujuiOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disetujui_oleh');
    }

    /**
     * @return HasMany<AsramaPeserta, $this>
     */
    public function penghuni(): HasMany
    {
        return $this->hasMany(AsramaPeserta::class);
    }
}
