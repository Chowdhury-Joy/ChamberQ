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
            $user->fill(collect($values)->except('password')->all());
            $user->save();

            return $user;
        }

        return User::withoutGlobalScope(TenantScope::class)->create([
            ...$match,
            ...$values,
            'password' => Hash::make($plainPassword),
        ]);
    }
}
