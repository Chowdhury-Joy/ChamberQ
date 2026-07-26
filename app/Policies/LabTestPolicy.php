<?php

namespace App\Policies;

use App\Models\LabTest;
use App\Models\User;

class LabTestPolicy
{
    public function viewAny(User $user): bool
    {
        return tenant()?->hasFeature('lab_tests') ?? false;
    }

    public function view(User $user, LabTest $labTest): bool
    {
        return tenant()?->hasFeature('lab_tests') ?? false;
    }

    public function create(User $user): bool
    {
        return tenant()?->hasFeature('lab_tests') ?? false;
    }

    public function update(User $user, LabTest $labTest): bool
    {
        return tenant()?->hasFeature('lab_tests') ?? false;
    }

    public function delete(User $user, LabTest $labTest): bool
    {
        return tenant()?->hasFeature('lab_tests') ?? false;
    }
}
