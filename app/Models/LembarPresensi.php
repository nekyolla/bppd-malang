<?php

namespace App\Models;

use App\Models\Concerns\Diaudit;
use Database\Factories\LembarPresensiFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Presensi satu kelas pada satu tanggal, beserta scan lembar kertasnya.
 */
#[Fillable(['kelas_pelatihan_id', 'tanggal', 'file_scan', 'diinput_oleh'])]
class LembarPresensi extends Model
{
    use Diaudit;

    /** @use HasFactory<LembarPresensiFactory> */
    use HasFactory;

    protected $table = 'lembar_presensi';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
        ];
    }

    /**
     * @return BelongsTo<KelasPelatihan, $this>
     */
    public function kelasPelatihan(): BelongsTo
    {
        return $this->belongsTo(KelasPelatihan::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function diinputOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diinput_oleh');
    }

    /**
     * @return HasMany<Presensi, $this>
     */
    public function presensi(): HasMany
    {
        return $this->hasMany(Presensi::class);
    }
}
