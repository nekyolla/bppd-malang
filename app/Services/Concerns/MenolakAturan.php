<?php

namespace App\Services\Concerns;

use Illuminate\Validation\ValidationException;

/**
 * Service menolak aturan bisnis dengan ValidationException berkunci nama kolom biasa;
 * panel memetakannya ke form atau notifikasi (CLAUDE.md, "Galat service").
 */
trait MenolakAturan
{
    /**
     * @throws ValidationException
     */
    protected function tolak(string $kolom, string $pesan): never
    {
        throw ValidationException::withMessages([$kolom => $pesan]);
    }
}
