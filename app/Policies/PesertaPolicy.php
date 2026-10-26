<?php

namespace App\Policies;

use App\Enums\Peran;
use App\Models\Peserta;
use App\Models\User;

/**
 * Data peserta hanya untuk akun internal yang aktif (PRD §10). Peserta dibuat lewat
 * form registrasi publik dan tidak dihapus dari panel.
 */
class PesertaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_aktif && $user->hasAnyRole(Peran::panelAdmin());
    }

    /**
     * Termasuk membuka KTP dan pas foto lewat BerkasController (FR-PST-03).
     */
    public function view(User $user, Peserta $peserta): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Merapikan data sesuai KTP (FR-PST-02), lewat PesertaService.
     */
    public function update(User $user, Peserta $peserta): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, Peserta $peserta): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
