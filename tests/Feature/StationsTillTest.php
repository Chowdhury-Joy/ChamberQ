<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Chamber;
use App\Models\ChamberCashEntry;
use App\Models\Doctor;
use App\Models\Domain;
use App\Models\FeeCatalogItem;
use App\Models\LiveSession;
use App\Models\ScheduleSession;
use App\Models\Tenant;
use App\Models\User;
use App\Services\StationsHandoffService;
use App\Services\StationsTillService;
use App\Services\VoucherService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StationsTillTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Chamber $chamber;

    private Doctor $doctor;

    private ScheduleSession $session;

    private FeeCatalogItem $catalogItem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'id' => 'stations-till',
            'plan_tier' => 'clinic',
            'feature_flags' => Tenant::mergeStationsFlag([], true),
        ]);
        Domain::create(['domain' => 'stations-till.localhost', 'tenant_id' => 'stations-till']);
        tenancy()->initialize($this->tenant);

        $this->chamber = Chamber::create(['name' => 'Main']);
        $this->doctor = Doctor::create(['name' => 'Dr Till', 'default_fee_taka' => 1000]);
        $this->session = ScheduleSession::create([
            'chamber_id' => $this->chamber->id,
            'doctor_id' => $this->doctor->id,
            'day_of_week' => Carbon::today()->dayOfWeek,
            'session_name' => 'Visit',
            'kind' => ScheduleSession::KIND_VISIT,
            'start_time' => '12:00',
            'end_time' => '14:00',
            'slot_cap' => 20,
        ]);
        $this->catalogItem = FeeCatalogItem::create([
            'label' => 'Visit',
            'list_price_taka' => 1000,
            'house_share_taka' => 200,
            'sort_order' => 1,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        tenancy()->end();

        parent::tearDown();
    }

    private function staff(): User
    {
        return User::create([
            'name' => 'Desk',
            'email' => 'desk@stations-till.loc',
            'password' => Hash::make('secret'),
            'role' => User::ROLE_STAFF,
            'tenant_id' => 'stations-till',
        ]);
    }

    private function booking(): Booking
    {
        return Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $this->session->id,
            'booking_date' => Carbon::today()->toDateString(),
            'patient_name' => 'Fatima',
            'patient_phone' => '01712222222',
            'serial_number' => 1,
            'status' => 'waiting',
        ]);
    }

    public function test_full_cash_payment_split(): void
    {
        $split = app(StationsTillService::class)->computeSplit(1000, 1000, 0, 200);

        $this->assertSame(1000, $split['collected']);
        $this->assertSame(0, $split['discount']);
        $this->assertSame(200, $split['clinic_share']);
        $this->assertSame(800, $split['doctor_share']);
    }

    public function test_split_cash_and_mobile_with_discount(): void
    {
        $split = app(StationsTillService::class)->computeSplit(1000, 600, 200, 200);

        $this->assertSame(800, $split['collected']);
        $this->assertSame(200, $split['discount']);
        $this->assertSame(200, $split['clinic_share']);
        $this->assertSame(600, $split['doctor_share']);
    }

    public function test_overpay_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        app(StationsTillService::class)->computeSplit(1000, 700, 400, 200);
    }

    public function test_zero_collect_yields_zero_clinic_share(): void
    {
        $split = app(StationsTillService::class)->computeSplit(3500, 0, 0, 500);

        $this->assertSame(0, $split['collected']);
        $this->assertSame(3500, $split['discount']);
        $this->assertSame(0, $split['clinic_share']);
        $this->assertSame(0, $split['doctor_share']);
    }

    public function test_record_patient_income_uses_collected_amount(): void
    {
        $entry = app(StationsTillService::class)->recordPatientIncome(
            $this->booking(),
            $this->staff(),
            $this->catalogItem,
            600,
            200,
            ChamberCashEntry::METHOD_BKASH,
        );

        $this->assertSame(800, $entry->amount);
        $this->assertSame(1000, $entry->list_price_taka);
        $this->assertSame(200, $entry->discount_taka);
        $this->assertSame(200, $entry->clinic_share_taka);
        $this->assertSame(600, $entry->doctor_share_taka);
        $this->assertSame(ChamberCashEntry::METHOD_MIXED, $entry->method);
    }

    public function test_waive_records_zero_collected(): void
    {
        $entry = app(StationsTillService::class)->recordPatientIncome(
            $this->booking(),
            $this->staff(),
            $this->catalogItem,
            0,
            0,
            waived: true,
        );

        $this->assertSame(0, $entry->amount);
        $this->assertTrue($entry->isWaived());
        $this->assertSame(0, $entry->clinic_share_taka);
    }

    public function test_voucher_numbers_are_per_day(): void
    {
        $bookingA = $this->booking();
        app(VoucherService::class)->assignIfNeeded($bookingA);
        $bookingA->refresh();

        $bookingB = Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $this->session->id,
            'booking_date' => Carbon::today()->toDateString(),
            'patient_name' => 'Rahim',
            'patient_phone' => '01713333333',
            'serial_number' => 2,
            'status' => 'waiting',
        ]);
        app(VoucherService::class)->assignIfNeeded($bookingB);
        $bookingB->refresh();

        $this->assertSame(1, $bookingA->voucher_number);
        $this->assertSame(2, $bookingB->voucher_number);
    }

    public function test_send_visit_to_intervention_creates_linked_row(): void
    {
        Carbon::setTestNow(Carbon::today()->setTime(10, 0));

        $intervention = ScheduleSession::create([
            'chamber_id' => $this->chamber->id,
            'doctor_id' => $this->doctor->id,
            'day_of_week' => Carbon::today()->dayOfWeek,
            'session_name' => 'Procedures',
            'kind' => ScheduleSession::KIND_INTERVENTION,
            'start_time' => '10:00',
            'end_time' => '12:00',
            'slot_cap' => 10,
        ]);

        $visitBooking = $this->booking();
        $procedure = app(StationsHandoffService::class)->sendVisitToIntervention(
            $visitBooking,
            Carbon::today()->toDateString(),
        );

        $this->assertSame($visitBooking->id, $procedure->related_booking_id);
        $this->assertSame(Booking::PROCEDURE_LOGGED, $procedure->procedure_status);
        $this->assertSame($intervention->id, $procedure->bookable_id);
        $this->assertTrue($procedure->booking_date->isToday());

        Carbon::setTestNow();
    }

    public function test_same_day_handoff_works_after_morning_intervention_hours(): void
    {
        Carbon::setTestNow(Carbon::today()->setTime(17, 30));

        $intervention = ScheduleSession::create([
            'chamber_id' => $this->chamber->id,
            'doctor_id' => $this->doctor->id,
            'day_of_week' => Carbon::today()->dayOfWeek,
            'session_name' => 'Morning OT',
            'kind' => ScheduleSession::KIND_INTERVENTION,
            'start_time' => '08:00',
            'end_time' => '10:00',
            'slot_cap' => 10,
        ]);

        $visitBooking = $this->booking();
        $procedure = app(StationsHandoffService::class)->sendVisitToIntervention(
            $visitBooking,
            Carbon::today()->toDateString(),
        );

        $this->assertSame($intervention->id, $procedure->bookable_id);
        $this->assertTrue($procedure->booking_date->isToday());
        $this->assertSame($visitBooking->id, $procedure->related_booking_id);

        Carbon::setTestNow();
    }

    public function test_handoff_can_book_the_next_intervention_sitting(): void
    {
        Carbon::setTestNow(Carbon::today()->setTime(17, 30));

        $intervention = ScheduleSession::create([
            'chamber_id' => $this->chamber->id,
            'doctor_id' => $this->doctor->id,
            'day_of_week' => Carbon::today()->dayOfWeek,
            'session_name' => 'Morning OT',
            'kind' => ScheduleSession::KIND_INTERVENTION,
            'start_time' => '08:00',
            'end_time' => '10:00',
            'slot_cap' => 10,
        ]);

        $nextWeek = Carbon::today()->addWeek()->toDateString();
        $visitBooking = $this->booking();
        $procedure = app(StationsHandoffService::class)->sendVisitToIntervention(
            $visitBooking,
            $nextWeek,
        );

        $this->assertSame($intervention->id, $procedure->bookable_id);
        $this->assertSame($nextWeek, $procedure->booking_date->toDateString());

        Carbon::setTestNow();
    }

    public function test_sitting_options_keep_same_day_but_default_to_next_open_ot(): void
    {
        Carbon::setTestNow(Carbon::today()->setTime(17, 30));

        ScheduleSession::create([
            'chamber_id' => $this->chamber->id,
            'doctor_id' => $this->doctor->id,
            'day_of_week' => Carbon::today()->dayOfWeek,
            'session_name' => 'Morning OT',
            'kind' => ScheduleSession::KIND_INTERVENTION,
            'start_time' => '08:00',
            'end_time' => '10:00',
            'slot_cap' => 10,
        ]);

        $options = app(StationsHandoffService::class)->sittingOptions($this->booking());

        $this->assertNotEmpty($options);
        $this->assertTrue($options[0]['is_same_day']);
        $this->assertTrue($options[0]['sitting_ended']);
        $this->assertSame($options[0]['date'], Carbon::today()->toDateString());

        $default = collect($options)->first(fn (array $option): bool => $option['is_default']);
        $this->assertNotNull($default);
        $this->assertFalse($default['is_same_day']);
        $this->assertFalse($default['sitting_ended']);
        $this->assertSame(Carbon::today()->addWeek()->toDateString(), $default['date']);

        Carbon::setTestNow();
    }

    public function test_move_procedure_to_another_sitting(): void
    {
        Carbon::setTestNow(Carbon::today()->setTime(17, 30));

        $intervention = ScheduleSession::create([
            'chamber_id' => $this->chamber->id,
            'doctor_id' => $this->doctor->id,
            'day_of_week' => Carbon::today()->dayOfWeek,
            'session_name' => 'Morning OT',
            'kind' => ScheduleSession::KIND_INTERVENTION,
            'start_time' => '08:00',
            'end_time' => '10:00',
            'slot_cap' => 10,
        ]);

        $visitBooking = $this->booking();
        $procedure = app(StationsHandoffService::class)->sendVisitToIntervention(
            $visitBooking,
            Carbon::today()->addWeek()->toDateString(),
        );

        $procedure->update(['status' => 'no_show']);

        $moved = app(StationsHandoffService::class)->moveProcedure(
            $procedure,
            Carbon::today()->addWeeks(2)->toDateString(),
        );

        $this->assertSame($intervention->id, $moved->bookable_id);
        $this->assertSame(Carbon::today()->addWeeks(2)->toDateString(), $moved->booking_date->toDateString());
        $this->assertSame('waiting', $moved->status);
        $this->assertSame(Booking::PROCEDURE_LOGGED, $moved->procedure_status);
        $this->assertSame($visitBooking->id, $moved->related_booking_id);

        Carbon::setTestNow();
    }

    public function test_moving_a_called_procedure_clears_todays_live_pointer(): void
    {
        Carbon::setTestNow(Carbon::today()->setTime(11, 0));

        $intervention = ScheduleSession::create([
            'chamber_id' => $this->chamber->id,
            'doctor_id' => $this->doctor->id,
            'day_of_week' => Carbon::today()->dayOfWeek,
            'session_name' => 'OT',
            'kind' => ScheduleSession::KIND_INTERVENTION,
            'start_time' => '10:00',
            'end_time' => '13:00',
            'slot_cap' => 10,
        ]);

        $procedure = app(StationsHandoffService::class)->sendVisitToIntervention(
            $this->booking(),
            Carbon::today()->toDateString(),
        );
        $procedure->update(['status' => 'called', 'called_at' => now()]);

        $live = LiveSession::create([
            'schedule_session_id' => $intervention->id,
            'session_date' => Carbon::today()->toDateString(),
            'status' => 'active',
            'started_at' => now(),
            'current_booking_id' => $procedure->id,
        ]);

        $moved = app(StationsHandoffService::class)->moveProcedure(
            $procedure,
            Carbon::today()->addWeek()->toDateString(),
        );

        $this->assertSame('waiting', $moved->status);
        $this->assertNull($moved->called_at);
        $this->assertNull($live->fresh()->current_booking_id);
        $this->assertSame(Carbon::today()->addWeek()->toDateString(), $moved->booking_date->toDateString());

        Carbon::setTestNow();
    }

    public function test_move_refuses_when_the_target_sitting_is_past_staff_cap(): void
    {
        Carbon::setTestNow(Carbon::today()->setTime(11, 0));

        $intervention = ScheduleSession::create([
            'chamber_id' => $this->chamber->id,
            'doctor_id' => $this->doctor->id,
            'day_of_week' => Carbon::today()->dayOfWeek,
            'session_name' => 'Tiny OT',
            'kind' => ScheduleSession::KIND_INTERVENTION,
            'start_time' => '10:00',
            'end_time' => '13:00',
            'slot_cap' => 1,
            'walk_in_overflow_cap' => 0,
        ]);

        $firstVisit = $this->booking();
        app(StationsHandoffService::class)->sendVisitToIntervention(
            $firstVisit,
            Carbon::today()->addWeek()->toDateString(),
        );

        $secondVisit = Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $this->session->id,
            'booking_date' => Carbon::today()->toDateString(),
            'patient_name' => 'Rahim',
            'patient_phone' => '01713333333',
            'serial_number' => 2,
            'status' => 'waiting',
        ]);
        $secondProcedure = app(StationsHandoffService::class)->sendVisitToIntervention(
            $secondVisit,
            Carbon::today()->addWeeks(2)->toDateString(),
        );

        $this->expectException(\InvalidArgumentException::class);

        app(StationsHandoffService::class)->moveProcedure(
            $secondProcedure,
            Carbon::today()->addWeek()->toDateString(),
            $intervention->id,
        );

        Carbon::setTestNow();
    }

    /**
     * The action is a plain confirm modal with no form, so there is nowhere to
     * explain a missing counseling sitting. If visibility does not match
     * capability, staff tap it, confirm, and only then get a red toast.
     */
    public function test_send_to_counseling_is_hidden_until_a_counseling_sitting_exists(): void
    {
        $intervention = ScheduleSession::create([
            'chamber_id' => $this->chamber->id,
            'doctor_id' => $this->doctor->id,
            'day_of_week' => Carbon::today()->dayOfWeek,
            'session_name' => 'OT',
            'kind' => ScheduleSession::KIND_INTERVENTION,
            'start_time' => '10:00',
            'end_time' => '12:00',
            'slot_cap' => 10,
        ]);

        $procedure = Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $intervention->id,
            'booking_date' => Carbon::today()->toDateString(),
            'patient_name' => 'Rahim',
            'patient_phone' => '01712222222',
            'serial_number' => 1,
            'status' => 'completed',
            'procedure_status' => Booking::PROCEDURE_DONE,
        ]);

        $handoff = app(StationsHandoffService::class);

        $this->assertFalse(
            $handoff->canSendToCounseling($procedure->fresh(['bookable'])),
            'Send to counseling was offered with no counseling sitting to send them to.',
        );

        ScheduleSession::create([
            'chamber_id' => $this->chamber->id,
            'doctor_id' => $this->doctor->id,
            'day_of_week' => Carbon::today()->dayOfWeek,
            'session_name' => 'Counseling',
            'kind' => ScheduleSession::KIND_COUNSELING,
            'start_time' => '10:00',
            'end_time' => '14:30',
            'slot_cap' => 20,
        ]);

        $this->assertTrue(
            $handoff->canSendToCounseling($procedure->fresh(['bookable'])),
            'Send to counseling stayed hidden even though a counseling sitting exists.',
        );
    }

    /**
     * Both endpoints are unauthenticated and take up to 50 caller-supplied ids,
     * so gating only the wizard and the POST left an intervention sitting's
     * remaining capacity and open dates readable through them.
     */
    public function test_public_availability_and_open_dates_ignore_non_bookable_sittings(): void
    {
        $intervention = ScheduleSession::create([
            'chamber_id' => $this->chamber->id,
            'doctor_id' => $this->doctor->id,
            'day_of_week' => Carbon::today()->dayOfWeek,
            'session_name' => 'OT',
            'kind' => ScheduleSession::KIND_INTERVENTION,
            'start_time' => '10:00',
            'end_time' => '12:00',
            'slot_cap' => 10,
        ]);

        $availability = $this->getJson('http://stations-till.localhost/api/bookings/availability?'.http_build_query([
            'bookable_type' => 'session',
            'bookable_ids' => [$intervention->id, $this->session->id],
            'booking_date' => Carbon::today()->toDateString(),
        ]))->assertOk()->json();

        $this->assertFalse(
            $availability['items'][(string) $intervention->id]['available'] ?? true,
            'An intervention sitting reported its availability on the public endpoint.',
        );

        $openDates = $this->getJson('http://stations-till.localhost/api/bookings/open-dates?'.http_build_query([
            'bookable_type' => 'session',
            'bookable_ids' => [$intervention->id],
        ]))->assertOk()->json();

        $this->assertSame(
            [],
            $openDates['options'] ?? [],
            'The public open-dates endpoint offered dates on an intervention sitting.',
        );
    }

    /**
     * The voucher is assigned after the booking transaction has committed, so
     * letting a failure escape 500s a booking the patient has in fact been
     * given — and skips the confirmation SMS that follows.
     */
    public function test_a_voucher_failure_does_not_lose_the_booking(): void
    {
        // Inside the 12:00–14:00 visit sitting. Without this the booking is
        // refused as an ended sitting whenever the suite runs after 2pm, which
        // would make this test pass or fail on the wall clock.
        Carbon::setTestNow(Carbon::today()->setTime(12, 30));

        $this->app->bind(VoucherService::class, fn () => new class extends VoucherService
        {
            public function assignIfNeeded(Booking $booking): void
            {
                throw new \RuntimeException('voucher gateway down');
            }
        });

        $booking = app(\App\Services\BookingService::class)->createBookingForBookable(
            $this->session,
            Carbon::today()->toDateString(),
            'Fatima',
            '01715555555',
            sendSms: false,
        );

        $this->assertNotNull($booking->fresh(), 'The booking was rolled back by a voucher failure.');
        $this->assertNull($booking->fresh()->voucher_number);

        Carbon::setTestNow();
    }

    public function test_online_booking_still_refuses_an_ended_sitting(): void
    {
        Carbon::setTestNow(Carbon::today()->setTime(17, 30));

        $intervention = ScheduleSession::create([
            'chamber_id' => $this->chamber->id,
            'doctor_id' => $this->doctor->id,
            'day_of_week' => Carbon::today()->dayOfWeek,
            'session_name' => 'Morning OT',
            'kind' => ScheduleSession::KIND_INTERVENTION,
            'start_time' => '08:00',
            'end_time' => '10:00',
            'slot_cap' => 10,
        ]);

        $this->expectException(\App\Exceptions\BookingUnavailableException::class);

        app(\App\Services\BookingService::class)->createBookingForBookable(
            $intervention,
            Carbon::today()->toDateString(),
            'Walk-in',
            '01714444444',
            sendSms: false,
        );

        Carbon::setTestNow();
    }
}
