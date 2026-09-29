<?php

namespace App\Models;

use App\Enums\StatusPresensi;
use App\Models\Concerns\Diaudit;
use Database\Factories\PresensiFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['lembar_presensi_id', 'pelatihan_peserta_id', 'status'])]
class Presensi extends Model
{
    use Diaudit;

    /** @use HasFactory<PresensiFactory> */
    use HasFactory;

    protected $table = 'presensi';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => StatusPresensi::class,
        ];
    }

    /**
     * @return BelongsTo<LembarPresensi, $this>
     */
    public function lembarPresensi(): BelongsTo
    {
        return $this->belongsTo(LembarPresensi::class);
    }

    /**
     * @return BelongsTo<PelatihanPeserta, $this>
     */
    public function pelatihanPeserta(): BelongsTo
    {
        return $this->belongsTo(PelatihanPeserta::class);
    }
}
