<?php

namespace App\Support;

use App\Models\User;
use App\Scopes\TenantScope;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * ChamberQ helper logins on a clinic are vendor keys, not the founder's staff.
 * The owner must not see, create, edit, demote, or delete them. Super Admin
 * (and seeders / console with no login) may add helpers; the last helper on
 * a clinic cannot be removed.
 */
final class ChamberQHelperAccess
{
    public static function actorMayAdministerHelpers(): bool
    {
        $actor = auth()->user();

        if (! $actor instanceof User) {
            return true;
        }

        return $actor->isSuperAdmin();
    }

    public static function actorSeesHelpersOnStaffList(): bool
    {
        $actor = auth()->user();

        return $actor instanceof User && $actor->isHelper();
    }

    public static function helperCountForTenant(?string $tenantId, ?int $exceptUserId = null): int
    {
        if ($tenantId === null || $tenantId === '') {
            return 0;
        }

        $query = User::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->where('role', User::ROLE_HELPER);

        if ($exceptUserId !== null) {
            $query->where('id', '!=', $exceptUserId);
        }

        return $query->count();
    }

    public static function guardCreating(User $user): void
    {
        if ($user->role !== User::ROLE_HELPER) {
            return;
        }

        if (self::actorMayAdministerHelpers()) {
            return;
        }

        throw new AuthorizationException(__('ChamberQ helper access cannot be created from this login.'));
    }

    public static function guardUpdating(User $user): void
    {
        $wasHelper = $user->getOriginal('role') === User::ROLE_HELPER;
        $willBeHelper = $user->role === User::ROLE_HELPER;

        if (! $wasHelper && ! $willBeHelper) {
            return;
        }

        if (self::actorMayAdministerHelpers()) {
            return;
        }

        $actor = auth()->user();
        if (
            $actor instanceof User
            && $actor->isHelper()
            && $actor->id === $user->id
        ) {
            if ($user->isDirty('role') || $user->isDirty('email')) {
                throw new AuthorizationException(__('ChamberQ helper access cannot be changed from this login.'));
            }

            return;
        }

        throw new AuthorizationException(__('ChamberQ helper access cannot be changed from this login.'));
    }

    public static function guardDeleting(User $user): void
    {
        if (! $user->isHelper()) {
            return;
        }

        if (self::helperCountForTenant($user->tenant_id, $user->id) === 0) {
            throw new AuthorizationException(__('The last ChamberQ helper on a clinic cannot be removed.'));
        }

        if (! self::actorMayAdministerHelpers()) {
            throw new AuthorizationException(__('ChamberQ helper access cannot be removed from this login.'));
        }
    }
}
