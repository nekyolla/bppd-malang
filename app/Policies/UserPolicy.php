<?php

namespace App\Policies;

use App\Enums\Peran;
use App\Models\User;

/**
 * Akun internal hanya dikelola superadmin (PRD §10). Akun tidak dihapus.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(Peran::Superadmin);
    }

    public function view(User $user, User $model): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, User $model): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, User $model): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
