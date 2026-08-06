<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
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

        $existingDoctor = User::withoutGlobalScope(\App\Scopes\TenantScope::class)
            ->where('tenant_id', $tenant->id)
            ->where('role', User::ROLE_DOCTOR)
            ->first();

        if ($existingDoctor) {
            return $existingDoctor;
        }

        return User::withoutGlobalScope(\App\Scopes\TenantScope::class)->firstOrCreate(
            ['email' => $email, 'tenant_id' => $tenant->id],
            [
                'name' => $name ?: $tenant->displayName(),
                'password' => Hash::make($password ?: Str::password(16)),
                'role' => User::ROLE_DOCTOR,
            ],
        );
    }

    public function hasDoctorLogin(Tenant $tenant): bool
    {
        return User::withoutGlobalScope(\App\Scopes\TenantScope::class)
            ->where('tenant_id', $tenant->id)
            ->where('role', User::ROLE_DOCTOR)
            ->exists();
    }

    public function defaultDoctorEmail(Tenant $tenant): string
    {
        return 'doctor@'.$tenant->id.'.local';
    }
}
