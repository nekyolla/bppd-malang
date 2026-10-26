<?php

namespace App\Policies;

use App\Enums\Peran;
use App\Models\User;
use Spatie\Activitylog\Models\Activity;

/**
 * Audit log hanya dapat dilihat superadmin dan tidak dapat diubah (FR-AUD-03).
 */
class ActivityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_aktif && $user->hasRole(Peran::Superadmin->value);
    }

    public function view(User $user, Activity $activity): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Activity $activity): bool
    {
        return false;
    }

    public function delete(User $user, Activity $activity): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
