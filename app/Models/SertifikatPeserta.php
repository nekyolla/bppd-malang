<?php

namespace App\Models;

use App\Models\Concerns\Diaudit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['pelatihan_peserta_id', 'nomor_sertifikat', 'tanggal_terbit', 'file_sertifikat', 'diupload_oleh'])]
class SertifikatPeserta extends Model
{
    use Diaudit;

    protected $table = 'sertifikat_peserta';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal_terbit' => 'date',
        ];
    }

    /**
     * @return BelongsTo<PelatihanPeserta, $this>
     */
    public function pelatihanPeserta(): BelongsTo
    {
        return $this->belongsTo(PelatihanPeserta::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function diuploadOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diupload_oleh');
    }
}
