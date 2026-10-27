<?php

namespace App\Policies;

use App\Enums\Peran;
use App\Models\SertifikatPeserta;
use App\Models\User;

/**
 * Sertifikat PDF per peserta diunggah, diganti, dan dihapus akun internal (PF-27).
 */
class SertifikatPesertaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_aktif && $user->hasAnyRole(Peran::panelAdmin());
    }

    public function view(User $user, SertifikatPeserta $sertifikatPeserta): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, SertifikatPeserta $sertifikatPeserta): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, SertifikatPeserta $sertifikatPeserta): bool
    {
        return $this->viewAny($user);
    }
}
