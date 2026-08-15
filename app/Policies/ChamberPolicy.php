<?php

namespace App\Policies;

use App\Models\Chamber;
use App\Models\User;

class ChamberPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canManageSittingSetup();
    }

    public function view(User $user, Chamber $chamber): bool
    {
        return $user->canManageSittingSetup();
    }

    public function create(User $user): bool
    {
        if (! $user->canManageSittingSetup()) {
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
        return $user->canManageSittingSetup();
    }

    public function delete(User $user, Chamber $chamber): bool
    {
        if (! $user->canManageSittingSetup()) {
            return false;
        }

        // Always keep at least one chamber for bookings and schedules.
        return Chamber::count() > 1;
    }

    /**
     * Bulk delete is checked once for the whole selection, so the
     * "keep at least one chamber" rule above cannot be enforced — every record
     * passes a count check taken before any row is removed. Deny it outright;
     * chambers are deleted one at a time, where `delete()` applies.
     */
    public function deleteAny(User $user): bool
    {
        return false;
    }
}
