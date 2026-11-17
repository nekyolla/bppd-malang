<?php

namespace App\Filament\Admin\Resources\Pelatihan\Pages\Concerns;

use App\Models\Pelatihan;
use LogicException;

/**
 * Halaman yang record-nya sebuah pelatihan (sub-halaman kerja dan Peserta Kelas).
 */
trait MembacaPelatihan
{
    /**
     * Pelatihan yang sedang dibuka. `getRecord()`/`getOwnerRecord()` Filament hanya bertipe
     * `Model`; pakai method ini agar properti dan relasi pelatihan dikenali IDE & Larastan.
     */
    public function pelatihan(): Pelatihan
    {
        $pelatihan = $this->getRecord();

        if (! $pelatihan instanceof Pelatihan) {
            throw new LogicException('Halaman ini harus dibuka dari sebuah pelatihan.');
        }

        return $pelatihan;
    }
}
