<?php

namespace Tests\Feature;

use App\Exceptions\BookingUnavailableException;
use App\Filament\TenantAdmin\Support\DeskActionLayout;
use App\Models\Booking;
use App\Models\Chamber;
use App\Models\ChamberCashEntry;
use App\Models\Doctor;
use App\Models\Domain;
use App\Models\FeeCatalogItem;
use App\Models\LabTest;
use App\Models\Patient;
use App\Models\ReferralCommission;
use App\Models\ReferringDoctor;
use App\Models\ScheduleSession;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BookingService;
use App\Services\CarePath;
use App\Services\ReferralCommissionService;
use App\Services\StationsTillService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DoorPayMskRefundTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Chamber $chamber;

    private Doctor $doctor;

    private ScheduleSession $visit;

    private ScheduleSession $msk;

    private User $staff;

    private FeeCatalogItem $mskFee;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-08-19 12:00'));

        $this->tenant = Tenant::create([
            'id' => 'door-pay-msk',
            'plan_tier' => 'clinic',
            'collect_fee_at_checkin' => true,
            'feature_flags' => Tenant::mergeOptInModuleFlag(
                Tenant::mergeStationsFlag([], true),
                Tenant::MODULE_REFERRALS,
                true,
            ),
        ]);
        Domain::create(['domain' => 'door-pay-msk.localhost', 'tenant_id' => 'door-pay-msk']);
        tenancy()->initialize($this->tenant);

        $this->chamber = Chamber::create(['name' => 'Panchlaish']);
        $this->doctor = Doctor::create(['name' => 'Dr Moin', 'default_fee_taka' => 1000]);
        $day = Carbon::today()->dayOfWeek;
        $this->visit = ScheduleSession::create([
            'chamber_id' => $this->chamber->id,
            'doctor_id' => $this->doctor->id,
            'day_of_week' => $day,
            'session_name' => 'Visit',
            'kind' => ScheduleSession::KIND_VISIT,
            'start_time' => '12:00',
            'end_time' => '14:00',
            'slot_cap' => 20,
        ]);
        $this->msk = ScheduleSession::create([
            'chamber_id' => $this->chamber->id,
            'doctor_id' => $this->doctor->id,
            'day_of_week' => $day,
            'session_name' => 'MSK',
            'kind' => ScheduleSession::KIND_MSK,
            'start_time' => '12:00',
            'end_time' => '14:00',
            'slot_cap' => 20,
        ]);
        $this->mskFee = FeeCatalogItem::create([
            'label' => 'MSK ultrasound',
            'list_price_taka' => 2200,
            'house_share_taka' => 0,
            'sitting_kind' => ScheduleSession::KIND_MSK,
        ]);
        $this->staff = User::create([
            'name' => 'Desk',
            'email' => 'desk@door-pay-msk.loc',
            'password' => Hash::make('secret'),
            'role' => User::ROLE_STAFF,
            'tenant_id' => 'door-pay-msk',
        ]);
        $this->actingAs($this->staff);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        tenancy()->end();

        parent::tearDown();
    }

    public function test_msk_is_a_priced_scan_not_a_free_room(): void
    {
        $this->assertFalse($this->msk->isFreeKind());
        $this->assertSame(2200, $this->mskFee->list_price_taka);

        $lab = LabTest::create([
            'name' => 'MSK ultrasound',
            'price' => 2200,
            'is_active' => true,
            'display_order' => 1,
        ]);

        $this->assertSame('2200.00', (string) $lab->price);
    }

    public function test_desk_can_walk_a_referred_patient_onto_msk_without_a_visit(): void
    {
        $booking = app(BookingService::class)->createBookingForBookable(
            $this->msk,
            Carbon::today()->toDateString(),
            'Referred Scan',
            '01715551111',
            sendSms: false,
            allowOverflow: true,
            allowMskWalkIn: true,
        );

        $this->assertSame(CarePath::MSK, $booking->care_path);
        $this->assertSame(ScheduleSession::KIND_MSK, $booking->bookable->kind);
    }

    public function test_overflow_alone_still_cannot_take_msk(): void
    {
        $this->expectException(BookingUnavailableException::class);

        app(BookingService::class)->createBookingForBookable(
            $this->msk,
            Carbon::today()->toDateString(),
            'Walk-in',
            '01715551112',
            sendSms: false,
            allowOverflow: true,
        );
    }

    public function test_no_show_after_door_pay_posts_a_refund_and_voids_pending_referral(): void
    {
        $this->tenant->update([
            'feature_flags' => array_merge($this->tenant->feature_flags ?? [], [
                'referral_msk_commission_taka' => 400,
            ]),
        ]);
        tenancy()->initialize($this->tenant->fresh());

        $referrer = ReferringDoctor::create(['name' => 'Dr Karim', 'phone' => '01710000001']);
        $booking = $this->mskBooking(['referring_doctor_id' => $referrer->id]);

        app(StationsTillService::class)->recordPatientIncome(
            $booking,
            $this->staff,
            $this->mskFee,
            2200,
            0,
        );

        $commission = ReferralCommission::query()->where('booking_id', $booking->id)->first();
        $this->assertNotNull($commission);
        $this->assertSame(ReferralCommission::KIND_MSK, $commission->kind);
        $this->assertSame(400, $commission->amount_taka);

        $booking->update(['status' => 'no_show']);

        $refund = ChamberCashEntry::query()
            ->where('booking_id', $booking->id)
            ->where('direction', ChamberCashEntry::DIRECTION_EXPENSE)
            ->where('category', ChamberCashEntry::CATEGORY_PATIENT_REFUND)
            ->first();

        $this->assertNotNull($refund);
        $this->assertSame(2200, $refund->amount);
        $this->assertSame(ReferralCommission::STATUS_VOID, $commission->fresh()->status);

        $booking->update(['status' => 'no_show']);
        $this->assertSame(1, ChamberCashEntry::query()
            ->where('booking_id', $booking->id)
            ->where('category', ChamberCashEntry::CATEGORY_PATIENT_REFUND)
            ->count());
    }

    public function test_waived_fee_is_not_refunded_on_no_show(): void
    {
        $booking = $this->mskBooking();
        app(StationsTillService::class)->recordPatientIncome(
            $booking,
            $this->staff,
            $this->mskFee,
            0,
            0,
            waived: true,
        );

        $booking->update(['status' => 'no_show']);

        $this->assertSame(0, ChamberCashEntry::query()
            ->where('booking_id', $booking->id)
            ->where('category', ChamberCashEntry::CATEGORY_PATIENT_REFUND)
            ->count());
    }

    public function test_after_a_refund_collect_fee_is_due_again_and_recollect_clears_the_refund(): void
    {
        $booking = $this->mskBooking();
        app(StationsTillService::class)->recordPatientIncome(
            $booking,
            $this->staff,
            $this->mskFee,
            2200,
            0,
        );

        $booking->update(['status' => 'no_show']);
        $booking = $booking->fresh(['cashEntry', 'bookable']);

        $this->assertTrue(DeskActionLayout::feeIsDue($booking));

        $booking->update(['status' => 'waiting']);

        app(StationsTillService::class)->recordPatientIncome(
            $booking->fresh(),
            $this->staff,
            $this->mskFee,
            2200,
            0,
        );

        $this->assertSame(0, ChamberCashEntry::query()
            ->where('booking_id', $booking->id)
            ->where('category', ChamberCashEntry::CATEGORY_PATIENT_REFUND)
            ->count());
        $this->assertFalse(DeskActionLayout::feeIsDue($booking->fresh(['cashEntry'])));
    }

    public function test_doctor_can_override_clinic_door_pay(): void
    {
        $this->assertTrue(DeskActionLayout::collectsFeeAtCheckin($this->visitBooking()));

        $this->doctor->update(['collect_fee_at_checkin' => false]);
        $this->visit->unsetRelation('doctor');

        $booking = $this->visitBooking(serial: 2);
        $this->assertFalse(DeskActionLayout::collectsFeeAtCheckin($booking));
    }

    public function test_default_msk_referral_cut_is_zero_until_set(): void
    {
        $this->assertSame(0, app(ReferralCommissionService::class)->mskCommissionTaka($this->tenant));

        $referrer = ReferringDoctor::create(['name' => 'Dr Sultana']);
        $booking = $this->mskBooking(['referring_doctor_id' => $referrer->id]);

        app(StationsTillService::class)->recordPatientIncome(
            $booking,
            $this->staff,
            $this->mskFee,
            2200,
            0,
        );

        $this->assertNull(ReferralCommission::query()->where('booking_id', $booking->id)->first());
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function mskBooking(array $extra = []): Booking
    {
        $patient = Patient::create([
            'name' => $extra['patient_name'] ?? 'Scan Patient',
            'phone' => $extra['patient_phone'] ?? '01715551113',
        ]);

        return Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $this->msk->id,
            'booking_date' => Carbon::today()->toDateString(),
            'patient_id' => $patient->id,
            'patient_name' => $patient->name,
            'patient_phone' => $patient->phone,
            'serial_number' => 1,
            'status' => 'waiting',
            'care_path' => CarePath::MSK,
            'referring_doctor_id' => $extra['referring_doctor_id'] ?? null,
        ]);
    }

    private function visitBooking(int $serial = 1): Booking
    {
        $patient = Patient::create([
            'name' => 'Visit Patient '.$serial,
            'phone' => '0171555111'.$serial,
        ]);

        $booking = Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $this->visit->id,
            'booking_date' => Carbon::today()->toDateString(),
            'patient_id' => $patient->id,
            'patient_name' => $patient->name,
            'patient_phone' => $patient->phone,
            'serial_number' => $serial,
            'status' => 'waiting',
        ]);

        return $booking->fresh(['bookable.doctor']);
    }
}
