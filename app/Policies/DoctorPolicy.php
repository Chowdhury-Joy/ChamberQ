<?php

namespace App\Policies;

use App\Models\Doctor;
use App\Models\User;

class DoctorPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Doctor $doctor): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        // Clinic tier (multiple_doctors = true) can add unlimited doctors.
        if (tenant()?->hasFeature('multiple_doctors')) {
            return true;
        }

        // Solo tier (multiple_doctors = false) can only create 1 doctor if none exists yet.
        return Doctor::count() < 1;
    }

    public function update(User $user, Doctor $doctor): bool
    {
        return true;
    }

    public function delete(User $user, Doctor $doctor): bool
    {
        // Solo tier cannot delete their primary doctor.
        if (! tenant()?->hasFeature('multiple_doctors')) {
            return false;
        }

        return true;
    }
}
