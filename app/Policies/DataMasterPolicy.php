<?php

namespace App\Policies;

use App\Enums\Peran;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Data master hanya dikelola superadmin (PRD §10) dan tidak pernah dihapus
 * permanen (CLAUDE.md aturan 2). Setiap model master punya turunan sendiri.
 */
abstract class DataMasterPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(Peran::Superadmin);
    }

    public function view(User $user, Model $model): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Model $model): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, Model $model): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, Model $model): bool
    {
        return false;
    }

    public function forceDelete(User $user, Model $model): bool
    {
        return false;
    }
}
