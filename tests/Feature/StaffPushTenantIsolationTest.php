<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\StaffPushSubscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StaffPushTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_cannot_register_a_push_endpoint_on_another_chamber(): void
    {
        $tenantA = Tenant::create([
            'id' => 'push-a',
            'plan_tier' => 'solo',
            'feature_flags' => Tenant::featureFlagsWithModules([], [
                Tenant::MODULE_LIVE_QUEUE,
            ]),
        ]);
        $tenantB = Tenant::create([
            'id' => 'push-b',
            'plan_tier' => 'solo',
            'feature_flags' => Tenant::featureFlagsWithModules([], [
                Tenant::MODULE_LIVE_QUEUE,
            ]),
        ]);
        Domain::create(['domain' => 'push-a.localhost', 'tenant_id' => 'push-a']);
        Domain::create(['domain' => 'push-b.localhost', 'tenant_id' => 'push-b']);

        $doctorA = User::create([
            'name' => 'Dr A',
            'email' => 'dra@push.test',
            'password' => Hash::make('secret'),
            'role' => User::ROLE_DOCTOR,
            'tenant_id' => $tenantA->id,
        ]);

        $this->actingAs($doctorA)
            ->postJson('http://push-b.localhost/api/staff/push', [
                'endpoint' => 'https://fcm.googleapis.com/fcm/send/test-endpoint',
                'keys' => [
                    'p256dh' => str_repeat('A', 40),
                    'auth' => str_repeat('B', 24),
                ],
            ])
            ->assertForbidden();

        $this->assertSame(0, StaffPushSubscription::query()->count());
    }
}
