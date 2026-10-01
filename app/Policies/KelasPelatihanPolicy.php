<?php

namespace App\Policies;

use App\Enums\Peran;
use App\Models\KelasPelatihan;
use App\Models\User;

/**
 * Kelas dikelola superadmin dan admin bersama pelatihannya (PRD §10).
 */
class KelasPelatihanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_aktif && $user->hasAnyRole(Peran::panelAdmin());
    }

    public function view(User $user, KelasPelatihan $kelasPelatihan): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, KelasPelatihan $kelasPelatihan): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Kelas yang masih berisi peserta atau sudah punya presensi ditolak KelasService.
     */
    public function delete(User $user, KelasPelatihan $kelasPelatihan): bool
    {
        return $this->viewAny($user);
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
