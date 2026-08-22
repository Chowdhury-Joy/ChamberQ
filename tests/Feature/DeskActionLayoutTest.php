<?php

namespace Tests\Feature;

use App\Filament\TenantAdmin\Support\DeskActionLayout;
use App\Models\Booking;
use App\Models\Chamber;
use App\Models\Doctor;
use App\Models\Domain;
use App\Models\LiveSession;
use App\Models\Patient;
use App\Models\ScheduleSession;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VisitRecord;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DeskActionLayoutTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $staff;

    private ScheduleSession $session;

    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-08-19 10:00'));

        $this->tenant = Tenant::create(['id' => 'desk-layout', 'plan_tier' => 'clinic']);
        Domain::create(['domain' => 'desk-layout.localhost', 'tenant_id' => 'desk-layout']);
        tenancy()->initialize($this->tenant);

        $this->staff = User::create([
            'name' => 'Desk',
            'email' => 'staff@desk-layout.test',
            'password' => Hash::make('secret'),
            'role' => User::ROLE_STAFF,
            'tenant_id' => 'desk-layout',
        ]);

        $chamber = Chamber::create(['name' => 'Main']);
        $doctor = Doctor::create(['name' => 'Dr Layout', 'default_fee_taka' => 800]);
        $this->session = ScheduleSession::create([
            'chamber_id' => $chamber->id,
            'doctor_id' => $doctor->id,
            'day_of_week' => Carbon::today()->dayOfWeek,
            'session_name' => 'Morning',
            'start_time' => '09:00',
            'end_time' => '12:00',
            'slot_cap' => 20,
            'kind' => ScheduleSession::KIND_VISIT,
        ]);

        $patient = Patient::create(['name' => 'Fatima Rahman', 'phone' => '01711112222']);
        $this->booking = Booking::create([
            'patient_id' => $patient->id,
            'patient_name' => $patient->name,
            'patient_phone' => $patient->phone,
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $this->session->id,
            'booking_date' => Carbon::today()->toDateString(),
            'serial_number' => 1,
            'status' => 'waiting',
        ]);

        $this->actingAs($this->staff);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        tenancy()->end();

        parent::tearDown();
    }

    public function test_waiting_stations_opening_puts_vitals_first_and_keeps_fee_in_more(): void
    {
        $this->enableStations();

        $primaries = DeskActionLayout::primaryKeys($this->booking, DeskActionLayout::SURFACE_ROSTER);

        $this->assertContains(DeskActionLayout::KEY_VITALS, $primaries);
        $this->assertNotContains(DeskActionLayout::KEY_COLLECT_FEE, $primaries);
        $this->assertNotContains(DeskActionLayout::KEY_CALL, $primaries);
        $this->assertLessThanOrEqual(2, count($primaries));
        $this->assertTrue(DeskActionLayout::shows(
            $this->booking,
            DeskActionLayout::KEY_COLLECT_FEE,
            DeskActionLayout::SLOT_MORE,
            DeskActionLayout::SURFACE_ROSTER,
        ));
    }

    public function test_check_in_fee_promotes_collect_fee_on_waiting_rows(): void
    {
        $this->enableStations();
        $this->tenant->update(['collect_fee_at_checkin' => true]);
        tenancy()->initialize($this->tenant->fresh());

        $primaries = DeskActionLayout::primaryKeys($this->booking->fresh(), DeskActionLayout::SURFACE_ROSTER);

        $this->assertSame([
            DeskActionLayout::KEY_VITALS,
            DeskActionLayout::KEY_COLLECT_FEE,
        ], $primaries);
    }

    public function test_completed_unpaid_visit_makes_collect_fee_primary_on_queue_and_roster(): void
    {
        $this->booking->update(['status' => 'completed']);

        $this->assertSame(
            [DeskActionLayout::KEY_COLLECT_FEE],
            DeskActionLayout::primaryKeys($this->booking->fresh(), DeskActionLayout::SURFACE_ROSTER),
        );
        $this->assertSame(
            [DeskActionLayout::KEY_COLLECT_FEE],
            DeskActionLayout::primaryKeys($this->booking->fresh(), DeskActionLayout::SURFACE_QUEUE),
        );
    }

    /**
     * Taking the vitals comes before calling the patient in, in every chamber
     * that has prep staff — not only the ones running the stations module. This
     * staff login holds all three desk jobs, so the gap is real here and Call
     * waits under More until the reading exists.
     */
    public function test_running_queue_waiting_leads_with_vitals_before_the_catch_up_call(): void
    {
        LiveSession::create([
            'schedule_session_id' => $this->session->id,
            'session_date' => Carbon::today()->toDateString(),
            'status' => 'active',
        ]);

        $primaries = DeskActionLayout::primaryKeys($this->booking->fresh(), DeskActionLayout::SURFACE_ROSTER);

        $this->assertContains(DeskActionLayout::KEY_VITALS, $primaries);
        $this->assertNotContains(DeskActionLayout::KEY_CALL, $primaries);
        $this->assertNotContains(DeskActionLayout::KEY_COLLECT_FEE, $primaries);
        $this->assertTrue(DeskActionLayout::shows(
            $this->booking->fresh(),
            DeskActionLayout::KEY_CALL,
            DeskActionLayout::SLOT_MORE,
            DeskActionLayout::SURFACE_ROSTER,
        ));
    }

    public function test_running_queue_waiting_promotes_call_as_catch_up_once_vitals_exist(): void
    {
        LiveSession::create([
            'schedule_session_id' => $this->session->id,
            'session_date' => Carbon::today()->toDateString(),
            'status' => 'active',
        ]);

        VisitRecord::create([
            'booking_id' => $this->booking->id,
            'patient_id' => $this->booking->patient_id,
            'recorded_by' => $this->staff->id,
            'vitals_recorded_by' => $this->staff->id,
            'recorded_at' => now(),
            'bp_systolic' => 120,
            'bp_diastolic' => 80,
        ]);

        $primaries = DeskActionLayout::primaryKeys($this->booking->fresh(), DeskActionLayout::SURFACE_ROSTER);

        $this->assertContains(DeskActionLayout::KEY_CALL, $primaries);
        $this->assertNotContains(DeskActionLayout::KEY_VITALS, $primaries);
        $this->assertNotContains(DeskActionLayout::KEY_COLLECT_FEE, $primaries);
    }

    /**
     * A desk login that does not hold the Prep job is never asked for vitals,
     * so Call stays the primary catch-up action for them.
     */
    public function test_a_desk_without_the_prep_job_still_leads_with_call(): void
    {
        $this->staff->update(['desk_jobs' => [\App\Support\StaffDeskJobs::JOB_QUEUE]]);
        $this->actingAs($this->staff->fresh());

        LiveSession::create([
            'schedule_session_id' => $this->session->id,
            'session_date' => Carbon::today()->toDateString(),
            'status' => 'active',
        ]);

        $primaries = DeskActionLayout::primaryKeys($this->booking->fresh(), DeskActionLayout::SURFACE_ROSTER);

        $this->assertContains(DeskActionLayout::KEY_CALL, $primaries);
        $this->assertNotContains(DeskActionLayout::KEY_VITALS, $primaries);
    }

    public function test_queue_table_waiting_with_stations_leads_with_vitals_then_call_now(): void
    {
        $this->enableStations();
        LiveSession::create([
            'schedule_session_id' => $this->session->id,
            'session_date' => Carbon::today()->toDateString(),
            'status' => 'active',
        ]);

        $this->assertSame([
            DeskActionLayout::KEY_VITALS,
            DeskActionLayout::KEY_CALL_NOW,
        ], DeskActionLayout::primaryKeys($this->booking->fresh(), DeskActionLayout::SURFACE_QUEUE));
    }

    public function test_recorded_vitals_drop_the_vitals_primary(): void
    {
        $this->enableStations();
        VisitRecord::create([
            'booking_id' => $this->booking->id,
            'patient_id' => $this->booking->patient_id,
            'recorded_by' => $this->staff->id,
            'recorded_at' => now(),
            'weight_kg' => 62,
            'bp_systolic' => 120,
            'bp_diastolic' => 80,
        ]);

        $this->booking->unsetRelation('visitRecord');

        $primaries = DeskActionLayout::primaryKeys($this->booking->fresh(), DeskActionLayout::SURFACE_ROSTER);

        $this->assertNotContains(DeskActionLayout::KEY_VITALS, $primaries);
    }

    private function enableStations(): void
    {
        $this->tenant->update([
            'feature_flags' => array_merge($this->tenant->feature_flags ?? [], [
                Tenant::MODULE_STATIONS => true,
            ]),
        ]);
        tenancy()->initialize($this->tenant->fresh());
        $this->booking->setRelation('bookable', $this->session->fresh());
    }
}
