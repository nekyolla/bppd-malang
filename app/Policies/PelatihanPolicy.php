<?php

namespace App\Policies;

use App\Enums\Peran;
use App\Models\Pelatihan;
use App\Models\User;

/**
 * Pelatihan dikelola superadmin dan admin (PRD §10).
 */
class PelatihanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(Peran::panelAdmin());
    }

    public function view(User $user, Pelatihan $pelatihan): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Pelatihan $pelatihan): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Hanya pelatihan tanpa pendaftar yang boleh dihapus, misal salah input.
     */
    public function delete(User $user, Pelatihan $pelatihan): bool
    {
        return $this->viewAny($user) && $pelatihan->pendaftaran()->doesntExist();
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
