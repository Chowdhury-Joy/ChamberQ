<?php

namespace Tests\Feature;

use App\Models\User;
use App\Scopes\TenantScope;
use App\Support\SeedAccounts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class SeedAccountsTest extends TestCase
{
    use RefreshDatabase;

    public function test_refuses_to_seed_when_the_app_is_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('production');

        SeedAccounts::refuseProduction();
    }

    public function test_re_seeding_does_not_reset_an_existing_password(): void
    {
        $user = User::withoutGlobalScope(TenantScope::class)->create([
            'name' => 'Kept',
            'email' => 'kept@demo.test',
            'password' => Hash::make('original-secret'),
            'role' => User::ROLE_ADMIN,
            'tenant_id' => null,
        ]);

        $updated = SeedAccounts::upsert(
            ['email' => 'kept@demo.test'],
            ['name' => 'Renamed', 'role' => User::ROLE_ADMIN],
            'pass',
        );

        $this->assertSame($user->id, $updated->id);
        $this->assertSame('Renamed', $updated->fresh()->name);
        $this->assertTrue(Hash::check('original-secret', $updated->fresh()->password));
        $this->assertFalse(Hash::check('pass', $updated->fresh()->password));
    }
}
