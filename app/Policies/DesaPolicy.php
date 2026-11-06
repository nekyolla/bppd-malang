<?php

namespace App\Policies;

use App\Enums\Peran;
use App\Models\Desa;
use App\Models\User;

/**
 * Data wilayah hanya-baca untuk superadmin (Data Master). Isinya berasal dari
 * WilayahSeeder, sehingga tidak ada tambah, ubah, maupun hapus lewat panel.
 */
class DesaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(Peran::Superadmin);
    }

    public function view(User $user, Desa $desa): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Desa $desa): bool
    {
        return false;
    }

    public function delete(User $user, Desa $desa): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
