<?php

namespace App\Policies;

use App\Models\LabTest;
use App\Models\User;

class LabTestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canManageOps() && (tenant()?->hasFeature('lab_tests') ?? false);
    }

    public function view(User $user, LabTest $labTest): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, LabTest $labTest): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, LabTest $labTest): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Filament checks bulk deletes against `deleteAny()`, not `delete()`. Without
     * this method the role and `lab_tests` gates above are skipped entirely.
     */
    public function deleteAny(User $user): bool
    {
        return $this->viewAny($user);
    }
}
