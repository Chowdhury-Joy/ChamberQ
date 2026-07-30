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

        $max = tenant()?->maxChambers();

        if ($max === null) {
            return true;
        }

        return Chamber::count() < $max;
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

        // Always keep at least one chamber for bookings and schedules.
        return Chamber::count() > 1;
    }
}
