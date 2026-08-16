<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Chamber;
use App\Models\ChamberCashEntry;
use App\Models\Doctor;
use App\Models\Domain;
use App\Models\FeeCatalogItem;
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
        $procedure = app(StationsHandoffService::class)->sendVisitToIntervention($visitBooking);

        $this->assertSame($visitBooking->id, $procedure->related_booking_id);
        $this->assertSame(Booking::PROCEDURE_LOGGED, $procedure->procedure_status);
        $this->assertSame($intervention->id, $procedure->bookable_id);

        Carbon::setTestNow();
    }
}
