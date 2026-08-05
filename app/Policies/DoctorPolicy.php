<?php

namespace App\Policies;

use App\Models\Doctor;
use App\Models\User;

class DoctorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canManageOps();
    }

    public function view(User $user, Doctor $doctor): bool
    {
        return $user->canManageOps();
    }

    public function create(User $user): bool
    {
        if (! $user->canManageOps()) {
            return false;
        }

        // Clinic tier can add unlimited doctors.
        if (tenant()?->hasFeature('multiple_doctors')) {
            return true;
        }

        // Solo can only create the first doctor.
        return Doctor::count() < 1;
    }

    public function update(User $user, Doctor $doctor): bool
    {
        return $user->canManageOps();
    }

    public function delete(User $user, Doctor $doctor): bool
    {
        if (! $user->canManageOps()) {
            return false;
        }

        // Solo cannot delete their only doctor.
        if (! tenant()?->hasFeature('multiple_doctors')) {
            return false;
        }

        return true;
    }

    /**
     * Bulk delete is authorized once for the whole selection, which would let a
     * solo tenant wipe the doctor every schedule and booking points at. Deny it;
     * doctors are removed one at a time through `delete()`.
     */
    public function deleteAny(User $user): bool
    {
        return false;
    }
}
