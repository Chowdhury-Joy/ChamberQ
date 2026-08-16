<?php

namespace Tests\Feature;

use App\Filament\TenantAdmin\Pages\LiveQueueControl;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The seeded demo tenant has admin + doctor + staff, which hides the case that
 * matters most: a solo doctor working alone. `queue_runner` defaults to staff,
 * so without a fallback nobody in that practice could call patients.
 *
 * Every practice here is built deliberately incomplete. Staff vs doctor
 * exclusivity is unchanged; the account owner never runs the queue.
 */
class QueueRunnerFallbackTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    private function makeUser(Tenant $tenant, string $role): User
    {
        return User::create([
            'name' => ucfirst($role),
            'email' => $role . '@' . $tenant->id . '.test',
            'password' => Hash::make('secret'),
            'role' => $role,
            'tenant_id' => $tenant->id,
        ]);
    }

    public function test_solo_doctor_without_staff_can_run_their_own_queue(): void
    {
        $tenant = Tenant::create(['id' => 'lone-doctor', 'plan_tier' => 'solo']);
        tenancy()->initialize($tenant);

        // Never set queue_runner — this is the out-of-the-box default.
        $this->assertSame(Tenant::QUEUE_RUNNER_STAFF, tenant()->queueRunner());

        $doctor = $this->makeUser($tenant, User::ROLE_DOCTOR);

        $this->assertSame(Tenant::QUEUE_RUNNER_DOCTOR, tenant()->effectiveQueueRunner());
        $this->assertTrue($doctor->canOperateQueueControls());

        $this->actingAs($doctor);
        $this->assertTrue(LiveQueueControl::canAccess());
    }

    public function test_staff_only_practice_configured_doctor_run_still_works(): void
    {
        $tenant = Tenant::create(['id' => 'staff-only', 'plan_tier' => 'clinic']);
        tenancy()->initialize($tenant);
        tenant()->update(['queue_runner' => Tenant::QUEUE_RUNNER_DOCTOR]);

        $staff = $this->makeUser($tenant, User::ROLE_STAFF);

        $this->assertSame(Tenant::QUEUE_RUNNER_STAFF, tenant()->effectiveQueueRunner());
        $this->assertTrue($staff->canOperateQueueControls());
    }

    public function test_exclusivity_holds_when_both_parties_exist(): void
    {
        $tenant = Tenant::create(['id' => 'both-parties', 'plan_tier' => 'clinic']);
        tenancy()->initialize($tenant);

        $doctor = $this->makeUser($tenant, User::ROLE_DOCTOR);
        $staff = $this->makeUser($tenant, User::ROLE_STAFF);

        // Staff-run (default): staff only, doctor follows along.
        $this->assertSame(Tenant::QUEUE_RUNNER_STAFF, tenant()->effectiveQueueRunner());
        $this->assertTrue($staff->canOperateQueueControls());
        $this->assertFalse($doctor->canOperateQueueControls());

        $admin = $this->makeUser($tenant, User::ROLE_ADMIN);
        $this->assertFalse($admin->canOperateQueueControls());

        // Doctor-run: the other way round, still exactly one desk party.
        tenant()->update(['queue_runner' => Tenant::QUEUE_RUNNER_DOCTOR]);
        tenancy()->end();
        tenancy()->initialize(Tenant::find('both-parties'));

        $doctor->refresh();
        $staff->refresh();
        $admin->refresh();

        $this->assertSame(Tenant::QUEUE_RUNNER_DOCTOR, tenant()->effectiveQueueRunner());
        $this->assertTrue($doctor->canOperateQueueControls());
        $this->assertFalse($staff->canOperateQueueControls());
        $this->assertFalse($admin->canOperateQueueControls());
    }

    public function test_account_owner_never_gains_queue_controls(): void
    {
        $tenant = Tenant::create(['id' => 'owner-only', 'plan_tier' => 'solo']);
        tenancy()->initialize($tenant);

        $admin = $this->makeUser($tenant, User::ROLE_ADMIN);

        $this->assertFalse($admin->canOperateQueueControls());
        $this->assertFalse($admin->canAccessLiveQueueControl());

        $this->actingAs($admin);
        $this->assertFalse(LiveQueueControl::canAccess());
    }
}
