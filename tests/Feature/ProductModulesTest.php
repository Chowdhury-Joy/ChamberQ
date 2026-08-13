<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Chamber;
use App\Models\Doctor;
use App\Models\Domain;
use App\Models\ScheduleSession;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductModulesTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private ScheduleSession $session;

    private string $host;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'id' => 'mod-test',
            'name' => 'Dr Module Test',
            'plan_tier' => 'solo',
            'billing_status' => 'active',
        ]);
        Domain::create(['domain' => 'mod-test.localhost', 'tenant_id' => 'mod-test']);
        $this->host = 'http://mod-test.localhost';

        tenancy()->initialize($this->tenant);

        $chamber = Chamber::create(['name' => 'Main']);
        $doctor = Doctor::create(['name' => 'Dr Test']);
        $this->session = ScheduleSession::create([
            'chamber_id' => $chamber->id,
            'doctor_id' => $doctor->id,
            'day_of_week' => Carbon::today()->dayOfWeek,
            'session_name' => 'Morning',
            'start_time' => '10:00',
            'end_time' => '13:00',
            'slot_cap' => 20,
        ]);

        tenancy()->end();
    }

    public function test_product_modules_default_on(): void
    {
        $this->assertTrue($this->tenant->hasFrontDoor());
        $this->assertTrue($this->tenant->hasLiveQueue());
        $this->assertTrue($this->tenant->hasPrescription());
    }

    public function test_front_door_only_blocks_queue_and_prescription_routes(): void
    {
        $this->tenant->update([
            'feature_flags' => Tenant::featureFlagsWithModules([], [
                Tenant::MODULE_FRONT_DOOR,
            ]),
        ]);
        $this->tenant->refresh();

        $this->assertTrue($this->tenant->hasFrontDoor());
        $this->assertFalse($this->tenant->hasLiveQueue());
        $this->assertFalse($this->tenant->hasPrescription());
        $this->assertTrue($this->tenant->acceptsBookings());

        $this->get($this->host.'/book')->assertOk();
        $this->get($this->host.'/screen/'.$this->session->id)->assertNotFound();
        $this->get($this->host.'/p/abcdefghij')->assertNotFound();
    }

    public function test_without_front_door_booking_is_closed(): void
    {
        $this->tenant->update([
            'feature_flags' => Tenant::featureFlagsWithModules([], [
                Tenant::MODULE_PRESCRIPTION,
            ]),
        ]);
        $this->tenant->refresh();

        $this->assertFalse($this->tenant->acceptsBookings());
        $this->get($this->host.'/book')->assertNotFound();
        $this->get($this->host.'/')->assertNotFound();
    }

    public function test_ticket_without_live_queue_omits_come_around_and_now_serving(): void
    {
        $this->tenant->update([
            'feature_flags' => Tenant::featureFlagsWithModules([], [
                Tenant::MODULE_FRONT_DOOR,
            ]),
        ]);
        $this->tenant->refresh();

        tenancy()->initialize($this->tenant);

        $booking = Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $this->session->id,
            'booking_date' => Carbon::today()->toDateString(),
            'serial_number' => 1,
            'patient_name' => 'Fatima',
            'patient_phone' => '01712345678',
            'status' => 'waiting',
        ]);

        tenancy()->end();

        $html = $this->get($this->host.'/bookings/'.$booking->id)
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Morning', $html);
        $this->assertStringContainsString('10:00', $html);
        $this->assertStringNotContainsString('id="etaContainer"', $html);
        $this->assertStringNotContainsString('id="nowServing"', $html);
        $this->assertStringNotContainsString('refreshQueue', $html);
    }

    public function test_live_queue_and_prescription_gates_staff_capabilities(): void
    {
        tenancy()->initialize($this->tenant);

        $doctor = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => User::ROLE_DOCTOR,
        ]);

        $this->tenant->update([
            'feature_flags' => Tenant::featureFlagsWithModules([], [
                Tenant::MODULE_FRONT_DOOR,
            ]),
        ]);
        $this->tenant->refresh();
        tenancy()->initialize($this->tenant);

        $this->assertFalse($doctor->fresh()->canAccessLiveQueueControl());
        $this->assertFalse($doctor->fresh()->canViewConsultScreen());
        $this->assertFalse($doctor->fresh()->canRecordVisitNotes());
        $this->assertTrue($doctor->fresh()->canManageQueue());

        tenancy()->end();
    }
}
