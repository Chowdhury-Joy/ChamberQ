<?php

namespace App\Support;

use App\Models\User;
use App\Scopes\TenantScope;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Demo / client seeders must never reset a live password, and must never run
 * against a production database at all.
 */
class SeedAccounts
{
    public static function refuseProduction(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('Refusing to seed demo accounts in production.');
        }
    }

    /**
     * @param  array<string, mixed>  $match
     * @param  array<string, mixed>  $values
     */
    public static function upsert(array $match, array $values, string $plainPassword): User
    {
        self::refuseProduction();

        $user = User::withoutGlobalScope(TenantScope::class)->where($match)->first();

        if ($user) {
            $privileged = array_intersect_key($values, array_flip(['role', 'tenant_id']));
            $safe = array_diff_key($values, array_flip(['role', 'tenant_id']));

            $user->fill($safe);
            if ($privileged !== []) {
                $user->forceFill($privileged);
            }
            $user->save();

            return $user;
        }

        return User::provision([
            ...$match,
            ...$values,
            'password' => Hash::make($plainPassword),
        ]);
    }
}
