<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Whitelist for tenant-panel Staff & Roles — blocks forged super_admin / marketer
 * payloads even if someone bypasses the Filament select options.
 */
final class TenantPanelUserRoles
{
    /**
     * @return list<string>
     */
    public static function creatableRoles(?User $actor): array
    {
        if ($actor instanceof User && StaffDeskJobs::isLeadDesk($actor) && ! $actor->canManageUsers()) {
            return [User::ROLE_STAFF];
        }

        return [
            User::ROLE_OWNER,
            User::ROLE_DOCTOR,
            User::ROLE_STAFF,
        ];
    }

    public static function normalize(?string $role, ?User $actor, ?User $record = null): string
    {
        if ($record instanceof User && $record->isHelper()) {
            return User::ROLE_HELPER;
        }

        if (($role ?? '') === User::ROLE_HELPER) {
            throw ValidationException::withMessages([
                'role' => __('ChamberQ helper access cannot be created from this login.'),
            ]);
        }

        $allowed = self::creatableRoles($actor);
        $normalized = (string) ($role ?? User::ROLE_STAFF);

        if (! in_array($normalized, $allowed, true)) {
            throw ValidationException::withMessages([
                'role' => __('That access role is not allowed here.'),
            ]);
        }

        if ($record instanceof User
            && $actor instanceof User
            && StaffDeskJobs::isLeadDesk($actor)
            && ! $actor->canManageUsers()) {
            return User::ROLE_STAFF;
        }

        return $normalized;
    }
}
