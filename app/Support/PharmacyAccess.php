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

    public static function canManageStock(?User $user): bool
    {
        return self::moduleOn()
            && $user instanceof User
            && $user->isAdmin();
    }
}
