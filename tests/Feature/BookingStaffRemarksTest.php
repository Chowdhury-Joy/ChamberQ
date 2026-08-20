<?php

namespace Tests\Feature;

use App\Filament\TenantAdmin\Pages\LiveQueueControl;
use App\Filament\TenantAdmin\Support\StaffBookingForm;
use App\Models\Booking;
use App\Models\Chamber;
use App\Models\Doctor;
use App\Models\LiveSession;
use App\Models\ScheduleSession;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BookingService;
use App\Services\RepeatBookingService;
use App\Services\StationsHandoffService;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class BookingStaffRemarksTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private ScheduleSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-08-19 10:00'));

        $this->tenant = Tenant::create([
            'id' => 'remarks-desk',
            'plan_tier' => 'clinic',
            'feature_flags' => Tenant::mergeStationsFlag([], true),
        ]);
        tenancy()->initialize($this->tenant);

        $chamber = Chamber::create(['name' => 'Main']);
        $doctor = Doctor::create([
            'name' => 'Dr Remarks',
            'allows_repeat_serials' => true,
            'default_fee_taka' => 800,
        ]);
        $this->session = ScheduleSession::create([
            'chamber_id' => $chamber->id,
            'doctor_id' => $doctor->id,
            'day_of_week' => Carbon::today()->dayOfWeek,
            'session_name' => 'Morning',
            'kind' => ScheduleSession::KIND_VISIT,
            'start_time' => '09:00',
            'end_time' => '12:00',
            'slot_cap' => 20,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        tenancy()->end();

        parent::tearDown();
    }

    public function test_staff_booking_stores_remarks_and_strips_html(): void
    {
        $booking = StaffBookingForm::createFromState([
            'bookable' => 'session:'.$this->session->id,
            'visit_type' => StaffBookingForm::TYPE_USUAL,
            'patient_phone' => '01715553001',
            'patient_name' => 'Fatima Rahman',
            'remarks' => '<b>wheelchair</b> — sister of Dr Karim',
            'share_clinical_history' => true,
        ], Carbon::today()->toDateString(), true, true, false);

        $this->assertSame('wheelchair — sister of Dr Karim', $booking->remarks);
    }

    public function test_online_booking_does_not_store_remarks(): void
    {
        $booking = app(BookingService::class)->createBookingForBookable(
            $this->session,
            Carbon::today()->toDateString(),
            'Online Patient',
            '01715553002',
            sendSms: false,
        );

        $this->assertNull($booking->remarks);
    }

    public function test_live_queue_shows_remarks_on_the_call_card_and_table(): void
    {
        $booking = Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $this->session->id,
            'booking_date' => Carbon::today()->toDateString(),
            'patient_name' => 'Joy Walk-In',
            'patient_phone' => '01711112222',
            'serial_number' => 1,
            'status' => 'waiting',
            'remarks' => 'Needs extra stool',
        ]);

        LiveSession::create([
            'schedule_session_id' => $this->session->id,
            'session_date' => Carbon::today(),
            'status' => 'active',
            'started_at' => now(),
        ]);

        $staff = User::create([
            'name' => 'Desk',
            'email' => 'desk@remarks-desk.test',
            'password' => Hash::make('secret'),
            'role' => User::ROLE_STAFF,
            'tenant_id' => $this->tenant->id,
        ]);

        Filament::setCurrentPanel('tenantAdmin');
        $this->actingAs($staff);

        Livewire::test(LiveQueueControl::class)
            ->set('selectedSessionId', $this->session->id)
            ->assertSee('Joy Walk-In')
            ->assertSee('Needs extra stool');

        $this->assertSame('Needs extra stool', $booking->fresh()->remarks);
    }

    public function test_repeat_serials_copy_the_origin_remarks(): void
    {
        $origin = app(BookingService::class)->createBookingForBookable(
            $this->session,
            Carbon::today()->toDateString(),
            'Fatima Rahman',
            '01710000001',
            sendSms: false,
            remarks: 'Always late from office',
        );

        $result = app(RepeatBookingService::class)->repeatFromBooking($origin, 1);

        $this->assertNotEmpty($result['created']);
        $this->assertSame('Always late from office', $result['created'][0]->remarks);
    }

    public function test_book_intervention_copies_or_overrides_remarks(): void
    {
        ScheduleSession::create([
            'chamber_id' => $this->session->chamber_id,
            'doctor_id' => $this->session->doctor_id,
            'day_of_week' => Carbon::today()->dayOfWeek,
            'session_name' => 'OT',
            'kind' => ScheduleSession::KIND_INTERVENTION,
            'start_time' => '08:00',
            'end_time' => '10:00',
            'slot_cap' => 8,
        ]);

        $visit = app(BookingService::class)->createBookingForBookable(
            $this->session,
            Carbon::today()->toDateString(),
            'Fatima Rahman',
            '01710000002',
            sendSms: false,
            remarks: 'Allergic to lidocaine',
        );

        $copied = app(StationsHandoffService::class)->sendVisitToIntervention(
            $visit,
            Carbon::today()->toDateString(),
        );
        $this->assertSame('Allergic to lidocaine', $copied->remarks);

        $otherVisit = app(BookingService::class)->createBookingForBookable(
            $this->session,
            Carbon::today()->toDateString(),
            'Rahim Uddin',
            '01710000003',
            sendSms: false,
            remarks: 'From visit',
        );

        $overridden = app(StationsHandoffService::class)->sendVisitToIntervention(
            $otherVisit,
            Carbon::today()->toDateString(),
            remarks: 'PRP today',
        );
        $this->assertSame('PRP today', $overridden->remarks);
    }
}
