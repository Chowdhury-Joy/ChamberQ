<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use App\Scopes\TenantScope;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TenantUserBootstrapService
{
    /**
     * Every tenant must have at least one doctor login so the practice can run
     * the queue and consult screen — not only an owner (admin) account.
     */
    public function ensureDoctorLogin(
        Tenant $tenant,
        ?string $email = null,
        ?string $name = null,
        ?string $password = null,
    ): User {
        $email = $email ?: $this->defaultDoctorEmail($tenant);

        $existingDoctor = User::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenant->id)
            ->where('role', User::ROLE_DOCTOR)
            ->first();

        if ($existingDoctor) {
            return $existingDoctor;
        }

        return User::withoutGlobalScope(TenantScope::class)->firstOrCreate(
            ['email' => $email, 'tenant_id' => $tenant->id],
            [
                'name' => $name ?: $tenant->displayName(),
                'password' => Hash::make($password ?: Str::password(16)),
                'role' => User::ROLE_DOCTOR,
            ],
        );
    }

    public function ensureOwnerLogin(
        Tenant $tenant,
        ?string $email = null,
        ?string $name = null,
        ?string $password = null,
    ): User {
        $email = $email ?: $this->defaultOwnerEmail($tenant);

        $existing = User::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenant->id)
            ->where('role', User::ROLE_OWNER)
            ->first();

        if ($existing) {
            return $existing;
        }

        return User::withoutGlobalScope(TenantScope::class)->firstOrCreate(
            ['email' => $email, 'tenant_id' => $tenant->id],
            [
                'name' => $name ?: $tenant->displayName().' owner',
                'password' => Hash::make($password ?: Str::password(16)),
                'role' => User::ROLE_OWNER,
            ],
        );
    }

    public function ensureHelperLogin(
        Tenant $tenant,
        ?string $email = null,
        ?string $name = null,
        ?string $password = null,
    ): User {
        $email = $email ?: $this->defaultHelperEmail($tenant);

        $existing = User::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenant->id)
            ->where('role', User::ROLE_HELPER)
            ->first();

        if ($existing) {
            return $existing;
        }

        return User::withoutGlobalScope(TenantScope::class)->firstOrCreate(
            ['email' => $email, 'tenant_id' => $tenant->id],
            [
                'name' => $name ?: 'ChamberQ Support',
                'password' => Hash::make($password ?: Str::password(16)),
                'role' => User::ROLE_HELPER,
            ],
        );
    }

    /**
     * Extra ChamberQ helper — unlike ensureHelperLogin(), this always creates
     * when the email is free, even if the clinic already has a helper.
     */
    public function addHelperLogin(
        Tenant $tenant,
        string $email,
        ?string $name = null,
        ?string $password = null,
    ): User {
        return User::withoutGlobalScope(TenantScope::class)->firstOrCreate(
            ['email' => $email, 'tenant_id' => $tenant->id],
            [
                'name' => $name ?: 'ChamberQ Support',
                'password' => Hash::make($password ?: Str::password(16)),
                'role' => User::ROLE_HELPER,
            ],
        );
    }

    public function hasDoctorLogin(Tenant $tenant): bool
    {
        return User::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenant->id)
            ->where('role', User::ROLE_DOCTOR)
            ->exists();
    }

    public function hasOwnerLogin(Tenant $tenant): bool
    {
        return User::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenant->id)
            ->where('role', User::ROLE_OWNER)
            ->exists();
    }

    public function hasHelperLogin(Tenant $tenant): bool
    {
        return User::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenant->id)
            ->where('role', User::ROLE_HELPER)
            ->exists();
    }

    public function defaultDoctorEmail(Tenant $tenant): string
    {
        return 'doctor@'.$tenant->id.'.local';
    }

    public function defaultOwnerEmail(Tenant $tenant): string
    {
        return 'owner@'.$tenant->id.'.local';
    }

    public function defaultHelperEmail(Tenant $tenant): string
    {
        return 'support@'.$tenant->id.'.chamberq.internal';
    }
}
