<?php

namespace App\Policies;

use App\Enums\Peran;
use App\Models\PelatihanPeserta;
use App\Models\User;

/**
 * Pendaftaran dan berkasnya hanya untuk akun internal yang aktif (PRD §10).
 * Aksi verifikasi, pembatalan, dan pengganti ditambahkan di M3.
 */
class PelatihanPesertaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_aktif && $user->hasAnyRole(Peran::panelAdmin());
    }

    /**
     * Termasuk membuka berkas KTP, pas foto, dan surat tugas lewat BerkasController.
     */
    public function view(User $user, PelatihanPeserta $pelatihanPeserta): bool
    {
        return $this->viewAny($user);
    }
}
