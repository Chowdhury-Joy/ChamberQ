<?php

namespace Tests\Feature;

use App\Filament\TenantAdmin\Pages\DailyRoster;
use App\Models\Booking;
use App\Models\Chamber;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\ScheduleSession;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BookingService;
use App\Services\PatientService;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class PatientSeenBeforeSoftwareTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $staff;

    private ScheduleSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        // Mid-morning on a fixed date. These tests book onto today's sitting,
        // and `BookingService::isDateBlocked()` refuses a sitting whose end
        // time has passed — so on a real clock the file passed all day and
        // failed every evening after 21:00, which is how a suite teaches
        // people to ignore it.
        Carbon::setTestNow(Carbon::parse('2026-08-19 10:00'));

        $this->tenant = Tenant::create(['id' => 'paper-file', 'plan_tier' => 'solo']);
        tenancy()->initialize($this->tenant);

        $chamber = Chamber::create(['name' => 'Main']);
        $doctor = Doctor::create(['name' => 'Dr Paper']);
        $this->session = ScheduleSession::create([
            'chamber_id' => $chamber->id,
            'doctor_id' => $doctor->id,
            'day_of_week' => Carbon::today()->dayOfWeek,
            'session_name' => 'Morning',
            'start_time' => '09:00',
            'end_time' => '21:00',
            'slot_cap' => 20,
        ]);

        $this->staff = User::create([
            'name' => 'Desk',
            'email' => 'staff@paper-file.test',
            'password' => Hash::make('secret'),
            'role' => User::ROLE_STAFF,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        tenancy()->end();

        parent::tearDown();
    }

    public function test_a_new_file_is_a_first_visit_until_staff_mark_the_paper_history(): void
    {
        $patient = Patient::create([
            'name' => 'Fatima Rahman',
            'phone' => '01711112222',
        ]);

        $this->assertSame('first_time', $patient->consultHistoryState());
        $this->assertSame(__('First visit — no history'), $patient->consultHistoryLabel());

        app(PatientService::class)->setSeenBeforeSoftware($patient, true);

        $patient = $patient->fresh();
        $this->assertTrue($patient->seen_before_software);
        $this->assertSame('paper_file', $patient->consultHistoryState());
        $this->assertSame(
            __('Seen here before ChamberQ · paper file'),
            $patient->consultHistoryLabel(),
        );
    }

    public function test_staff_can_clear_the_paper_mark_so_the_person_counts_as_a_first_visit_again(): void
    {
        $patient = Patient::create([
            'name' => 'Fatima Rahman',
            'phone' => '01711112222',
            'seen_before_software' => true,
        ]);

        app(PatientService::class)->setSeenBeforeSoftware($patient, false);

        $this->assertFalse($patient->fresh()->seen_before_software);
        $this->assertSame('first_time', $patient->fresh()->consultHistoryState());
    }

    public function test_completed_chamberq_visits_outrank_the_paper_mark(): void
    {
        $patient = Patient::create([
            'name' => 'Fatima Rahman',
            'phone' => '01711112222',
            'seen_before_software' => true,
        ]);

        Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $this->session->id,
            'booking_date' => Carbon::today()->subMonths(2),
            'patient_id' => $patient->id,
            'patient_name' => $patient->name,
            'patient_phone' => $patient->phone,
            'serial_number' => 1,
            'status' => 'completed',
            'completed_at' => now()->subMonths(2),
        ]);

        $this->assertSame('visits_no_notes', $patient->fresh()->consultHistoryState());
    }

    public function test_walk_in_follow_up_stamps_the_paper_mark_on_a_new_file(): void
    {
        Filament::setCurrentPanel('tenantAdmin');
        $this->actingAs($this->staff);

        Livewire::test(DailyRoster::class)
            ->mountTableAction('newWalkIn')
            ->fillForm([
                'visit_type' => 'followup',
                'bookable' => 'session:'.$this->session->id,
                'patient_phone' => '01711112222',
                'patient_name' => 'Fatima Rahman',
            ])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $patient = Patient::query()->where('phone', '01711112222')->first();
        $this->assertNotNull($patient);
        $this->assertTrue($patient->seen_before_software);
        $this->assertSame('paper_file', $patient->consultHistoryState());
    }

    public function test_roster_row_action_toggles_the_paper_mark_both_ways(): void
    {
        $booking = app(BookingService::class)->createBookingForBookable(
            $this->session,
            Carbon::today()->toDateString(),
            'Fatima Rahman',
            '01711112222',
            sendSms: false,
        );

        $this->assertFalse($booking->patient->seen_before_software);

        Filament::setCurrentPanel('tenantAdmin');
        $this->actingAs($this->staff);

        Livewire::test(DailyRoster::class)
            ->callTableAction('toggleSeenBeforeSoftware', $booking)
            ->assertSuccessful();

        $this->assertTrue($booking->patient->fresh()->seen_before_software);

        Livewire::test(DailyRoster::class)
            ->callTableAction('toggleSeenBeforeSoftware', $booking)
            ->assertSuccessful();

        $this->assertFalse($booking->patient->fresh()->seen_before_software);
    }

    public function test_joining_records_keeps_the_paper_mark_if_either_file_had_it(): void
    {
        $keep = Patient::create([
            'name' => 'Fatima Rahman',
            'phone' => '01711112222',
        ]);
        $remove = Patient::create([
            'name' => 'Fatima R',
            'phone' => '01711112222',
            'seen_before_software' => true,
        ]);

        app(PatientService::class)->mergePatients($keep, $remove);

        $this->assertTrue($keep->fresh()->seen_before_software);
        $this->assertNull(Patient::find($remove->id));
    }

    public function test_resolve_for_booking_does_not_clear_an_existing_paper_mark_when_the_flag_is_omitted(): void
    {
        $patient = Patient::create([
            'name' => 'Fatima Rahman',
            'phone' => '01711112222',
            'seen_before_software' => true,
        ]);

        $resolved = app(PatientService::class)->resolveForBooking(
            '01711112222',
            'Fatima Rahman',
        );

        $this->assertTrue($resolved->seen_before_software);
        $this->assertSame($patient->id, $resolved->id);
    }
}
