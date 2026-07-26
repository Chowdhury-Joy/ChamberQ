<?php

namespace App\Policies;

use App\Models\LabCollectionSlot;
use App\Models\User;

class LabCollectionSlotPolicy
{
    public function viewAny(User $user): bool
    {
        return tenant()?->hasFeature('lab_tests') ?? false;
    }

    public function view(User $user, LabCollectionSlot $slot): bool
    {
        return tenant()?->hasFeature('lab_tests') ?? false;
    }

    public function create(User $user): bool
    {
        return tenant()?->hasFeature('lab_tests') ?? false;
    }

    public function update(User $user, LabCollectionSlot $slot): bool
    {
        return tenant()?->hasFeature('lab_tests') ?? false;
    }

    public function delete(User $user, LabCollectionSlot $slot): bool
    {
        return tenant()?->hasFeature('lab_tests') ?? false;
    }
}
