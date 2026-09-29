<?php

namespace App\Models;

use Database\Factories\DesaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['kecamatan_id', 'kode', 'nama'])]
class Desa extends Model
{
    /** @use HasFactory<DesaFactory> */
    use HasFactory;

    protected $table = 'desa';

    /**
     * @return BelongsTo<Kecamatan, $this>
     */
    public function kecamatan(): BelongsTo
    {
        return $this->belongsTo(Kecamatan::class);
    }
}
