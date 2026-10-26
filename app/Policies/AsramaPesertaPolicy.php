<?php

namespace App\Policies;

use App\Enums\Peran;
use App\Models\AsramaPeserta;
use App\Models\User;

/**
 * Penempatan kamar diatur superadmin dan admin (PRD §10). Aturan penempatan ada di AsramaService.
 */
class AsramaPesertaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_aktif && $user->hasAnyRole(Peran::panelAdmin());
    }

    public function view(User $user, AsramaPeserta $asramaPeserta): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Pindah kamar dilakukan dengan mengeluarkan lalu menempatkan ulang (FR-ASR-08).
     */
    public function update(User $user, AsramaPeserta $asramaPeserta): bool
    {
        return false;
    }

    /**
     * Mengeluarkan peserta dari kamar.
     */
    public function delete(User $user, AsramaPeserta $asramaPeserta): bool
    {
        return $this->viewAny($user);
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
