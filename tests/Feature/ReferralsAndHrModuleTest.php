<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Chamber;
use App\Models\ChamberCashEntry;
use App\Models\Doctor;
use App\Models\Domain;
use App\Models\Employee;
use App\Models\FeeCatalogItem;
use App\Models\PayrollPayment;
use App\Models\ReferralCommission;
use App\Models\ReferringDoctor;
use App\Models\ScheduleSession;
use App\Models\Tenant;
use App\Models\User;
use App\Services\HrPayrollService;
use App\Services\ReferralCommissionService;
use App\Services\StationsTillService;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use Tests\TestCase;

class ReferralsAndHrModuleTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Chamber $chamber;

    private Doctor $doctor;

    private ScheduleSession $visitSession;

    private ScheduleSession $interventionSession;

    private FeeCatalogItem $visitFee;

    private FeeCatalogItem $interventionFee;

    private ReferringDoctor $referrer;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'id' => 'referrals-hr',
            'plan_tier' => 'clinic',
            'feature_flags' => Tenant::mergeOptInModuleFlag(
                Tenant::mergeOptInModuleFlag(
                    Tenant::mergeStationsFlag([], true),
                    Tenant::MODULE_REFERRALS,
                    true,
                ),
                Tenant::MODULE_HR,
                true,
            ),
            'practice_rules' => [
                'referral_visit_taka' => 200,
                'referral_intervention_taka' => 1000,
                'referral_msk_taka' => 0,
            ],
        ]);
        Domain::create(['domain' => 'referrals-hr.localhost', 'tenant_id' => 'referrals-hr']);
        tenancy()->initialize($this->tenant);

        $this->chamber = Chamber::create(['name' => 'Main']);
        $this->doctor = Doctor::create(['name' => 'Dr Pain', 'default_fee_taka' => 1000]);
        $this->visitSession = ScheduleSession::create([
            'chamber_id' => $this->chamber->id,
            'doctor_id' => $this->doctor->id,
            'day_of_week' => Carbon::today()->dayOfWeek,
            'session_name' => 'Visit',
            'kind' => ScheduleSession::KIND_VISIT,
            'start_time' => '12:00',
            'end_time' => '14:00',
            'slot_cap' => 20,
        ]);
        $this->interventionSession = ScheduleSession::create([
            'chamber_id' => $this->chamber->id,
            'doctor_id' => $this->doctor->id,
            'day_of_week' => Carbon::today()->dayOfWeek,
            'session_name' => 'Intervention',
            'kind' => ScheduleSession::KIND_INTERVENTION,
            'start_time' => '10:00',
            'end_time' => '12:00',
            'slot_cap' => 20,
        ]);
        $this->visitFee = FeeCatalogItem::create([
            'label' => 'Visit',
            'list_price_taka' => 1000,
            'house_share_taka' => 200,
            'sitting_kind' => ScheduleSession::KIND_VISIT,
        ]);
        $this->interventionFee = FeeCatalogItem::create([
            'label' => 'MSK',
            'list_price_taka' => 3500,
            'house_share_taka' => 1000,
            'sitting_kind' => ScheduleSession::KIND_INTERVENTION,
        ]);
        $this->referrer = ReferringDoctor::create([
            'name' => 'Dr Karim',
            'phone' => '01710000000',
        ]);
        $this->staff = User::create([
            'name' => 'Desk',
            'email' => 'desk@referrals-hr.loc',
            'password' => Hash::make('secret'),
            'role' => User::ROLE_STAFF,
            'tenant_id' => 'referrals-hr',
        ]);
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    public function test_opt_in_modules_default_off(): void
    {
        $plain = Tenant::create(['id' => 'plain', 'plan_tier' => 'solo']);

        $this->assertFalse($plain->hasReferrals());
        $this->assertFalse($plain->hasHr());
    }

    public function test_visit_referral_commission_is_two_hundred(): void
    {
        $booking = $this->booking($this->visitSession, ['referring_doctor_id' => $this->referrer->id]);

        app(StationsTillService::class)->recordPatientIncome(
            $booking,
            $this->staff,
            $this->visitFee,
            1000,
            0,
        );

        $commission = ReferralCommission::query()->where('booking_id', $booking->id)->first();

        $this->assertNotNull($commission);
        $this->assertSame(ReferralCommission::KIND_VISIT, $commission->kind);
        $this->assertSame(200, $commission->amount_taka);
        $this->assertSame(ReferralCommission::STATUS_PENDING, $commission->status);
    }

    public function test_intervention_referral_commission_is_one_thousand(): void
    {
        $booking = $this->booking($this->interventionSession, ['referring_doctor_id' => $this->referrer->id]);

        app(StationsTillService::class)->recordPatientIncome(
            $booking,
            $this->staff,
            $this->interventionFee,
            3500,
            0,
        );

        $commission = ReferralCommission::query()->where('booking_id', $booking->id)->first();

        $this->assertNotNull($commission);
        $this->assertSame(ReferralCommission::KIND_INTERVENTION, $commission->kind);
        $this->assertSame(1000, $commission->amount_taka);
    }

    public function test_waived_fee_voids_referral_commission(): void
    {
        $booking = $this->booking($this->visitSession, ['referring_doctor_id' => $this->referrer->id]);

        app(StationsTillService::class)->recordPatientIncome(
            $booking,
            $this->staff,
            $this->visitFee,
            0,
            0,
            waived: true,
        );

        $this->assertDatabaseMissing('referral_commissions', [
            'booking_id' => $booking->id,
            'status' => ReferralCommission::STATUS_PENDING,
        ]);
    }

    public function test_mark_paid_posts_cashbook_expense(): void
    {
        $booking = $this->booking($this->visitSession, ['referring_doctor_id' => $this->referrer->id]);
        app(StationsTillService::class)->recordPatientIncome($booking, $this->staff, $this->visitFee, 1000, 0);
        $commission = ReferralCommission::query()->where('booking_id', $booking->id)->firstOrFail();

        app(ReferralCommissionService::class)->markPaid(
            collect([$commission]),
            $this->staff,
            ChamberCashEntry::METHOD_CASH,
        );

        $commission->refresh();
        $this->assertSame(ReferralCommission::STATUS_PAID, $commission->status);
        $this->assertDatabaseHas('chamber_cash_entries', [
            'direction' => ChamberCashEntry::DIRECTION_EXPENSE,
            'category' => ChamberCashEntry::CATEGORY_REFERRAL_PAYOUT,
            'amount' => 200,
        ]);
    }

    public function test_payroll_posts_salary_expense(): void
    {
        $employee = Employee::create([
            'name' => 'Nurse Rina',
            'monthly_salary_taka' => 22000,
        ]);

        app(HrPayrollService::class)->recordSalaryPayment(
            $employee,
            $this->staff,
            now()->format('Y-m'),
            22000,
            ChamberCashEntry::METHOD_CASH,
        );

        $this->assertDatabaseHas('payroll_payments', [
            'employee_id' => $employee->id,
            'amount_taka' => 22000,
        ]);
        $this->assertDatabaseHas('chamber_cash_entries', [
            'direction' => ChamberCashEntry::DIRECTION_EXPENSE,
            'category' => ChamberCashEntry::CATEGORY_SALARY,
            'amount' => 22000,
        ]);
    }

    /**
     * Two staff (or one double-click) paying out the same commission.
     *
     * The Filament bulk action hands the service a snapshot of what the table
     * showed, so each request carries its own copy of the row — the second
     * request's copy still says "pending" even after the first has paid. The
     * status must therefore be re-read at payout time, not trusted from the
     * selection.
     */
    public function test_a_second_payout_on_a_stale_selection_does_not_pay_the_doctor_twice(): void
    {
        $booking = $this->booking($this->visitSession, ['referring_doctor_id' => $this->referrer->id]);
        app(StationsTillService::class)->recordPatientIncome($booking, $this->staff, $this->visitFee, 1000, 0);
        $commissionId = ReferralCommission::query()->where('booking_id', $booking->id)->firstOrFail()->id;

        // Two independent snapshots — this is what two concurrent requests hold.
        $selectionA = ReferralCommission::query()->whereKey($commissionId)->get();
        $selectionB = ReferralCommission::query()->whereKey($commissionId)->get();

        app(ReferralCommissionService::class)->markPaid($selectionA, $this->staff, ChamberCashEntry::METHOD_CASH);

        try {
            app(ReferralCommissionService::class)->markPaid($selectionB, $this->staff, ChamberCashEntry::METHOD_CASH);
            $this->fail('The second payout should have been rejected as already paid.');
        } catch (InvalidArgumentException) {
            // Expected: nothing left pending to pay.
        }

        $this->assertSame(
            1,
            ChamberCashEntry::query()->where('category', ChamberCashEntry::CATEGORY_REFERRAL_PAYOUT)->count(),
            'The referring doctor was paid twice.',
        );
    }

    /**
     * A salary expense must never outlive the payroll row that explains it.
     *
     * The duplicate read in recordSalaryPayment() is a friendly early message,
     * not a guard — two submissions can both pass it. Here the conflicting
     * payroll row lands in exactly that window (injected on cash-entry
     * creation), so the payroll insert hits the unique index. The cashbook must
     * not keep the expense.
     */
    public function test_a_rejected_payroll_row_leaves_no_salary_expense_behind(): void
    {
        $employee = Employee::create([
            'name' => 'Nurse Rina',
            'monthly_salary_taka' => 22000,
        ]);

        $period = now()->format('Y-m');
        $injected = false;

        ChamberCashEntry::created(function () use ($employee, $period, &$injected): void {
            if ($injected) {
                return;
            }
            $injected = true;

            // A concurrent request that committed its payroll row first.
            PayrollPayment::create([
                'employee_id' => $employee->id,
                'pay_period' => $period,
                'amount_taka' => 22000,
                'paid_on' => now()->toDateString(),
                'method' => ChamberCashEntry::METHOD_CASH,
                'recorded_by' => $this->staff->id,
            ]);
        });

        try {
            app(HrPayrollService::class)->recordSalaryPayment(
                $employee,
                $this->staff,
                $period,
                22000,
                ChamberCashEntry::METHOD_CASH,
            );
            $this->fail('The duplicate salary should have been rejected.');
        } catch (InvalidArgumentException|UniqueConstraintViolationException) {
            // Expected: the pay period is already recorded.
        }

        $this->assertSame(
            0,
            ChamberCashEntry::query()->where('category', ChamberCashEntry::CATEGORY_SALARY)->count(),
            'A salary expense was left in the cashbook with no payroll row to explain it.',
        );
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function booking(ScheduleSession $session, array $extra = []): Booking
    {
        return Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $session->id,
            'booking_date' => Carbon::today()->toDateString(),
            'patient_name' => 'Rahim',
            'patient_phone' => '01712222222',
            'serial_number' => 1,
            'status' => 'waiting',
            ...$extra,
        ]);
    }
}
