<?php

namespace App\Policies;

use App\Models\LabCollectionSlot;
use App\Models\User;

class LabCollectionSlotPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canManageOps() && (tenant()?->hasFeature('lab_tests') ?? false);
    }

    public function view(User $user, LabCollectionSlot $labCollectionSlot): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, LabCollectionSlot $labCollectionSlot): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, LabCollectionSlot $labCollectionSlot): bool
    {
        return $this->viewAny($user);
    }
}
