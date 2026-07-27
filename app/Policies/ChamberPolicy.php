<?php

namespace App\Policies;

use App\Models\Chamber;
use App\Models\User;

class ChamberPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canManageOps();
    }

    public function view(User $user, Chamber $chamber): bool
    {
        return $user->canManageOps();
    }

    public function create(User $user): bool
    {
        if (! $user->canManageOps()) {
            return false;
        }

        if (tenant()?->hasFeature('multiple_chambers')) {
            return true;
        }

        return Chamber::count() < 1;
    }

    public function update(User $user, Chamber $chamber): bool
    {
        return $user->canManageOps();
    }

    public function delete(User $user, Chamber $chamber): bool
    {
        if (! $user->canManageOps()) {
            return false;
        }

        if (! tenant()?->hasFeature('multiple_chambers')) {
            return false;
        }

        return true;
    }
}
