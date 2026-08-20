<?php

namespace App\Support;

use App\Models\User;

final class PharmacyAccess
{
    public static function moduleOn(): bool
    {
        return tenant()?->hasPharmacy() ?? false;
    }

    public static function canRunCounter(?User $user): bool
    {
        return self::moduleOn()
            && $user instanceof User
            && StaffDeskJobs::canCollectFee($user);
    }

    /**
     * Shop list + physical count. For now this is the Money desk (the usual
     * one-person staff login), not a separate chemist role. A later dedicated
     * pharmacy staff tick can split this without changing the till.
     */
    public static function canManageStock(?User $user): bool
    {
        if (! self::moduleOn() || ! $user instanceof User) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return $user->isStaff()
            && StaffDeskJobs::hasJob($user, StaffDeskJobs::JOB_MONEY)
            && $user->canManageCash();
    }
}
