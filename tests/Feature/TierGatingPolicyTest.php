<?php

namespace Tests\Feature;

use App\Models\Chamber;
use App\Models\Doctor;
use App\Models\Domain;
use App\Models\LabCollectionSlot;
use App\Models\LabTest;
use App\Models\ScheduleSession;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class TierGatingPolicyTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $soloTenant;
    private Tenant $clinicTenant;
    private User $soloAdmin;
    private User $clinicAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->soloTenant = Tenant::create(['id' => 'policy-solo', 'plan_tier' => 'solo']);
        Domain::create(['domain' => 'policy-solo.test', 'tenant_id' => 'policy-solo']);
        $this->soloAdmin = User::create([
            'name' => 'Solo Admin', 'email' => 'admin@policy-solo.test',
            'password' => bcrypt('password'), 'role' => 'admin',
            'tenant_id' => 'policy-solo',
        ]);

        $this->clinicTenant = Tenant::create(['id' => 'policy-clinic', 'plan_tier' => 'clinic']);
        Domain::create(['domain' => 'policy-clinic.test', 'tenant_id' => 'policy-clinic']);
        $this->clinicAdmin = User::create([
            'name' => 'Clinic Admin', 'email' => 'admin@policy-clinic.test',
            'password' => bcrypt('password'), 'role' => 'admin',
            'tenant_id' => 'policy-clinic',
        ]);
    }

    public function test_solo_tenant_cannot_view_lab_tests(): void
    {
        tenancy()->initialize($this->soloTenant);

        $this->assertFalse(Gate::forUser($this->soloAdmin)->allows('viewAny', LabTest::class));
        $this->assertFalse(Gate::forUser($this->soloAdmin)->allows('viewAny', LabCollectionSlot::class));

        tenancy()->end();
    }

    public function test_clinic_tenant_can_view_lab_tests(): void
    {
        tenancy()->initialize($this->clinicTenant);

        $this->assertTrue(Gate::forUser($this->clinicAdmin)->allows('viewAny', LabTest::class));
        $this->assertTrue(Gate::forUser($this->clinicAdmin)->allows('viewAny', LabCollectionSlot::class));

        tenancy()->end();
    }

    public function test_solo_with_feature_flag_override_can_view_lab_tests(): void
    {
        $upgraded = Tenant::create([
            'id' => 'upgraded-solo',
            'plan_tier' => 'solo',
            'feature_flags' => ['lab_tests' => true],
        ]);
        Domain::create(['domain' => 'upgraded-solo.test', 'tenant_id' => 'upgraded-solo']);
        $admin = User::create([
            'name' => 'Upgraded', 'email' => 'admin@upgraded-solo.test',
            'password' => bcrypt('password'), 'role' => 'admin',
            'tenant_id' => 'upgraded-solo',
        ]);

        tenancy()->initialize($upgraded);

        $this->assertTrue(Gate::forUser($admin)->allows('viewAny', LabTest::class));
        $this->assertTrue(Gate::forUser($admin)->allows('viewAny', LabCollectionSlot::class));

        tenancy()->end();
    }

    public function test_booking_api_rejects_wrong_day_of_week(): void
    {
        tenancy()->initialize($this->clinicTenant);

        $chamber = Chamber::create(['name' => 'Test Chamber']);
        $doctor = Doctor::create(['name' => 'Dr. Test']);
        $session = ScheduleSession::create([
            'chamber_id' => $chamber->id,
            'doctor_id' => $doctor->id,
            'day_of_week' => 2, // Tuesday
            'session_name' => 'Morning',
            'start_time' => '09:00',
            'end_time' => '12:00',
            'slot_cap' => 10,
        ]);

        // Pick a date that's definitely NOT a Tuesday
        $wrongDate = now()->next('Wednesday')->toDateString();

        tenancy()->end();

        $response = $this->postJson('http://policy-clinic.test/api/bookings', [
            'bookable_type' => 'session',
            'bookable_id' => $session->id,
            'booking_date' => $wrongDate,
            'patient_name' => 'Test Patient',
            'patient_phone' => '01712345678',
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['success' => false]);
    }

    public function test_solo_tenant_can_create_up_to_five_chambers(): void
    {
        tenancy()->initialize($this->soloTenant);

        for ($i = 1; $i <= Tenant::SOLO_MAX_CHAMBERS; $i++) {
            $this->assertTrue(
                Gate::forUser($this->soloAdmin)->allows('create', Chamber::class),
                "Expected create allowed before chamber {$i}"
            );
            Chamber::create(['name' => "Chamber {$i}"]);
        }

        $this->assertFalse(Gate::forUser($this->soloAdmin)->allows('create', Chamber::class));

        tenancy()->end();
    }

    public function test_solo_tenant_can_delete_extra_chamber_but_not_the_last_one(): void
    {
        tenancy()->initialize($this->soloTenant);

        $first = Chamber::create(['name' => 'Main']);
        $second = Chamber::create(['name' => 'Branch']);

        $this->assertTrue(Gate::forUser($this->soloAdmin)->allows('delete', $second));
        $this->assertTrue(Gate::forUser($this->soloAdmin)->allows('delete', $first));

        $second->delete();

        $this->assertFalse(Gate::forUser($this->soloAdmin)->allows('delete', $first));

        tenancy()->end();
    }

    public function test_clinic_tenant_can_create_more_than_five_chambers(): void
    {
        tenancy()->initialize($this->clinicTenant);

        for ($i = 1; $i <= 6; $i++) {
            $this->assertTrue(Gate::forUser($this->clinicAdmin)->allows('create', Chamber::class));
            Chamber::create(['name' => "Clinic Chamber {$i}"]);
        }

        tenancy()->end();
    }

    public function test_solo_with_multiple_chambers_disabled_is_capped_at_one(): void
    {
        $locked = Tenant::create([
            'id' => 'locked-solo',
            'plan_tier' => 'solo',
            'feature_flags' => ['multiple_chambers' => false],
        ]);
        Domain::create(['domain' => 'locked-solo.test', 'tenant_id' => 'locked-solo']);
        $admin = User::create([
            'name' => 'Locked Solo',
            'email' => 'admin@locked-solo.test',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'tenant_id' => 'locked-solo',
        ]);

        tenancy()->initialize($locked);

        $this->assertFalse($locked->hasFeature('multiple_chambers'));
        $this->assertSame(1, $locked->maxChambers());

        $this->assertTrue(Gate::forUser($admin)->allows('create', Chamber::class));
        $only = Chamber::create(['name' => 'Only']);

        $this->assertFalse(Gate::forUser($admin)->allows('create', Chamber::class));
        $this->assertFalse(Gate::forUser($admin)->allows('delete', $only));

        tenancy()->end();
    }
}
