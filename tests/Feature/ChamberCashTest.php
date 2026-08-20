<?php

namespace Tests\Feature;

use App\Filament\TenantAdmin\Pages\Cashbook;
use App\Filament\TenantAdmin\Pages\DailyRoster;
use App\Models\Booking;
use App\Models\Chamber;
use App\Models\ChamberCashEntry;
use App\Models\Doctor;
use App\Models\Domain;
use App\Models\ScheduleSession;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ChamberCashService;
use App\Services\OperationalReportService;
use App\Support\StaffDeskJobs;
use App\Support\StaffDeskScope;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class ChamberCashTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Chamber $chamber;

    private Doctor $doctor;

    private ScheduleSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['id' => 'cashbook-clinic', 'plan_tier' => 'solo']);
        Domain::create(['domain' => 'cashbook-clinic.localhost', 'tenant_id' => 'cashbook-clinic']);
        tenancy()->initialize($this->tenant);

        $this->chamber = Chamber::create(['name' => 'Main']);
        $this->doctor = Doctor::create([
            'name' => 'Dr. Cash',
            'default_fee_taka' => 800,
        ]);
        $this->session = ScheduleSession::create([
            'chamber_id' => $this->chamber->id,
            'doctor_id' => $this->doctor->id,
            'day_of_week' => 1,
            'session_name' => 'Morning',
            'start_time' => '09:00',
            'end_time' => '12:00',
            'slot_cap' => 20,
        ]);
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    private function makeUser(string $role): User
    {
        return User::create([
            'name' => ucfirst($role),
            'email' => $role.'@cashbook-clinic.loc',
            'password' => Hash::make('secret'),
            'role' => $role,
            'tenant_id' => 'cashbook-clinic',
        ]);
    }

    private function makeBooking(string $status = 'waiting', int $serial = 1): Booking
    {
        return Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $this->session->id,
            'booking_date' => '2026-08-13',
            'patient_name' => 'Fatima Rahman',
            'patient_phone' => '0171'.str_pad((string) $serial, 7, '0', STR_PAD_LEFT),
            'serial_number' => $serial,
            'status' => $status,
        ]);
    }

    public function test_suggested_amount_is_doctor_fee(): void
    {
        $booking = $this->makeBooking();

        $this->assertSame(800, app(ChamberCashService::class)->suggestedAmountTaka($booking));
    }

    public function test_collecting_patient_fee_creates_income_and_second_collect_updates(): void
    {
        $staff = $this->makeUser(User::ROLE_STAFF);
        $booking = $this->makeBooking();

        $service = app(ChamberCashService::class);
        $first = $service->recordPatientIncome(
            $booking,
            $staff,
            ChamberCashEntry::METHOD_CASH,
            occurredOn: Carbon::parse('2026-08-13', OperationalReportService::TIMEZONE),
        );
        $second = $service->recordPatientIncome(
            $booking,
            $staff,
            ChamberCashEntry::METHOD_BKASH,
            occurredOn: Carbon::parse('2026-08-13', OperationalReportService::TIMEZONE),
        );

        $this->assertTrue($first->is($second));
        $this->assertSame(1, ChamberCashEntry::query()->count());
        $this->assertSame(800, $second->amount);
        $this->assertSame(Doctor::FEE_CONSULTATION, $second->fee_type);
        $this->assertSame(ChamberCashEntry::DIRECTION_INCOME, $second->direction);
        $this->assertSame(ChamberCashEntry::CATEGORY_PATIENT, $second->category);
        $this->assertSame(ChamberCashEntry::METHOD_BKASH, $second->method);
        $this->assertSame($this->chamber->id, $second->chamber_id);
        $this->assertSame($this->doctor->id, $second->doctor_id);
    }

    public function test_posted_amount_cannot_override_the_doctors_fee(): void
    {
        $staff = $this->makeUser(User::ROLE_STAFF);
        $booking = $this->makeBooking();
        Carbon::setTestNow(Carbon::parse('2026-08-13 10:00', OperationalReportService::TIMEZONE));

        $this->actingAs($staff);
        Filament::setCurrentPanel(Filament::getPanel('tenantAdmin'));

        Livewire::test(DailyRoster::class)
            ->callTableAction('collectFee', $booking, [
                'amount' => 1,
                'fee_type' => Doctor::FEE_CONSULTATION,
                'method' => ChamberCashEntry::METHOD_CASH,
                'waived' => false,
                'note' => null,
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('chamber_cash_entries', [
            'booking_id' => $booking->id,
            'amount' => 800,
            'fee_type' => Doctor::FEE_CONSULTATION,
        ]);

        Carbon::setTestNow();
    }

    public function test_optional_extra_fee_type_is_the_only_way_to_charge_a_different_price(): void
    {
        $this->doctor->update([
            'extra_fees' => [
                ['label' => 'Follow-up', 'amount' => 500],
            ],
        ]);
        $staff = $this->makeUser(User::ROLE_STAFF);
        $booking = $this->makeBooking();

        $entry = app(ChamberCashService::class)->recordPatientIncome(
            $booking,
            $staff,
            ChamberCashEntry::METHOD_CASH,
            feeType: 'extra:follow-up',
            occurredOn: Carbon::parse('2026-08-13', OperationalReportService::TIMEZONE),
        );

        $this->assertSame(500, $entry->amount);
        $this->assertSame('extra:follow-up', $entry->fee_type);
    }

    public function test_unknown_fee_type_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        app(ChamberCashService::class)->recordPatientIncome(
            $this->makeBooking(),
            $this->makeUser(User::ROLE_STAFF),
            ChamberCashEntry::METHOD_CASH,
            feeType: 'extra:not-on-the-list',
        );
    }

    public function test_waive_stores_zero_income_and_expense_is_separate(): void
    {
        $admin = $this->makeUser(User::ROLE_ADMIN);
        $booking = $this->makeBooking();
        $service = app(ChamberCashService::class);
        $day = Carbon::parse('2026-08-13', OperationalReportService::TIMEZONE);

        $service->recordPatientIncome(
            $booking,
            $admin,
            ChamberCashEntry::METHOD_CASH,
            waived: true,
            occurredOn: $day,
        );
        $service->recordExpense(
            $admin,
            2000,
            ChamberCashEntry::CATEGORY_RENT,
            ChamberCashEntry::METHOD_CASH,
            $day,
            $this->chamber->id,
            'August chamber rent',
        );
        $service->recordOtherIncome(
            $admin,
            500,
            ChamberCashEntry::CATEGORY_OTHER_INCOME,
            ChamberCashEntry::METHOD_NAGAD,
            $day,
            $this->chamber->id,
            'Sold old chair',
        );

        $summary = $service->summaryForRange($day->copy()->startOfDay(), $day->copy()->endOfDay());

        $this->assertSame(500, $summary['income']);
        $this->assertSame(2000, $summary['expense']);
        $this->assertSame(-1500, $summary['net']);
        $this->assertSame(1, $summary['waived_count']);
        $this->assertSame(800, $summary['waived_amount']);
        $this->assertSame(800, ChamberCashEntry::query()->where('category', ChamberCashEntry::CATEGORY_WAIVED)->value('amount'));
    }

    public function test_cash_entries_stay_inside_their_tenant(): void
    {
        $staff = $this->makeUser(User::ROLE_STAFF);
        $booking = $this->makeBooking();
        app(ChamberCashService::class)->recordPatientIncome(
            $booking,
            $staff,
            ChamberCashEntry::METHOD_CASH,
            occurredOn: Carbon::parse('2026-08-13', OperationalReportService::TIMEZONE),
        );

        $other = Tenant::create(['id' => 'cashbook-other', 'plan_tier' => 'solo']);
        tenancy()->initialize($other);

        $this->assertSame(0, ChamberCashEntry::query()->count());

        tenancy()->initialize($this->tenant);
        $this->assertSame(1, ChamberCashEntry::query()->count());
    }

    public function test_staff_doctor_and_admin_can_open_cashbook(): void
    {
        $this->actingAs($this->makeUser(User::ROLE_ADMIN));
        $this->assertTrue(Cashbook::canAccess());

        $this->actingAs($this->makeUser(User::ROLE_DOCTOR));
        $this->assertTrue(Cashbook::canAccess());

        $this->actingAs($this->makeUser(User::ROLE_STAFF));
        $this->assertTrue(Cashbook::canAccess());
    }

    public function test_cashbook_page_shows_income_expense_and_net(): void
    {
        $admin = $this->makeUser(User::ROLE_ADMIN);
        $day = Carbon::parse('2026-08-13', OperationalReportService::TIMEZONE);
        $service = app(ChamberCashService::class);
        $service->recordOtherIncome(
            $admin,
            800,
            ChamberCashEntry::CATEGORY_OTHER_INCOME,
            ChamberCashEntry::METHOD_CASH,
            $day,
            $this->chamber->id,
        );
        $service->recordExpense(
            $admin,
            150,
            ChamberCashEntry::CATEGORY_SUPPLIES,
            ChamberCashEntry::METHOD_CASH,
            $day,
            $this->chamber->id,
            'Tea and biscuits',
        );

        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('tenantAdmin'));

        Livewire::test(Cashbook::class)
            ->set('anchorDate', '2026-08-13')
            ->set('period', 'day')
            ->assertOk()
            ->assertSee('৳800')
            ->assertSee('৳150')
            ->assertSee('৳650')
            ->assertSee('Tea and biscuits');
    }

    public function test_cashbook_page_shows_waived_taka(): void
    {
        $admin = $this->makeUser(User::ROLE_ADMIN);
        $booking = $this->makeBooking();
        $day = Carbon::parse('2026-08-13', OperationalReportService::TIMEZONE);
        app(ChamberCashService::class)->recordPatientIncome(
            $booking,
            $admin,
            ChamberCashEntry::METHOD_CASH,
            waived: true,
            occurredOn: $day,
        );

        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('tenantAdmin'));

        Livewire::test(Cashbook::class)
            ->set('anchorDate', '2026-08-13')
            ->set('period', 'day')
            ->assertOk()
            ->assertSee('৳800')
            ->assertSee(__('Waived'))
            ->assertDontSee(__(':count waived', ['count' => 1]));
    }

    public function test_daily_roster_collect_records_income(): void
    {
        $staff = $this->makeUser(User::ROLE_STAFF);
        $booking = $this->makeBooking();
        Carbon::setTestNow(Carbon::parse('2026-08-13 10:00', OperationalReportService::TIMEZONE));

        $this->actingAs($staff);
        Filament::setCurrentPanel(Filament::getPanel('tenantAdmin'));

        Livewire::test(DailyRoster::class)
            ->callTableAction('collectFee', $booking, [
                'fee_type' => Doctor::FEE_CONSULTATION,
                'method' => ChamberCashEntry::METHOD_CASH,
                'waived' => false,
                'note' => null,
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('chamber_cash_entries', [
            'booking_id' => $booking->id,
            'direction' => ChamberCashEntry::DIRECTION_INCOME,
            'amount' => 800,
            'method' => ChamberCashEntry::METHOD_CASH,
        ]);

        Carbon::setTestNow();
    }

    public function test_mixed_cash_and_online_income_stores_split(): void
    {
        $admin = $this->makeUser(User::ROLE_ADMIN);
        $day = Carbon::parse('2026-08-13', OperationalReportService::TIMEZONE);
        $entry = app(ChamberCashService::class)->recordOtherIncome(
            $admin,
            1000,
            ChamberCashEntry::CATEGORY_OTHER_INCOME,
            ChamberCashEntry::METHOD_MIXED,
            $day,
            $this->chamber->id,
            'Chair sale',
            cashTaka: 400,
            onlineTaka: 600,
            onlineMethod: ChamberCashEntry::METHOD_BKASH,
        );

        $this->assertSame(1000, $entry->amount);
        $this->assertSame(400, $entry->cash_taka);
        $this->assertSame(600, $entry->mobile_taka);
        $this->assertSame(ChamberCashEntry::METHOD_BKASH, $entry->mobile_method);
        $this->assertSame(__('Cash + online (bKash)'), $entry->paymentMethodLabel());
    }

    public function test_mixed_expense_requires_online_method_when_online_amount_positive(): void
    {
        $admin = $this->makeUser(User::ROLE_ADMIN);
        $day = Carbon::parse('2026-08-13', OperationalReportService::TIMEZONE);

        $this->expectException(\InvalidArgumentException::class);

        app(ChamberCashService::class)->recordExpense(
            $admin,
            500,
            ChamberCashEntry::CATEGORY_SUPPLIES,
            ChamberCashEntry::METHOD_MIXED,
            $day,
            $this->chamber->id,
            cashTaka: 200,
            onlineTaka: 300,
            onlineMethod: null,
        );
    }

    public function test_collect_fee_mixed_stores_cash_and_online_split(): void
    {
        $staff = $this->makeUser(User::ROLE_STAFF);
        $booking = $this->makeBooking();
        Carbon::setTestNow(Carbon::parse('2026-08-13 10:00', OperationalReportService::TIMEZONE));

        $this->actingAs($staff);
        Filament::setCurrentPanel(Filament::getPanel('tenantAdmin'));

        Livewire::test(DailyRoster::class)
            ->callTableAction('collectFee', $booking, [
                'fee_type' => Doctor::FEE_CONSULTATION,
                'method' => ChamberCashEntry::METHOD_MIXED,
                'cash_taka' => 300,
                'online_taka' => 500,
                'online_method' => ChamberCashEntry::METHOD_BKASH,
                'waived' => false,
                'note' => null,
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('chamber_cash_entries', [
            'booking_id' => $booking->id,
            'amount' => 800,
            'method' => ChamberCashEntry::METHOD_MIXED,
            'cash_taka' => 300,
            'mobile_taka' => 500,
            'mobile_method' => ChamberCashEntry::METHOD_BKASH,
        ]);

        Carbon::setTestNow();
    }

    public function test_collect_fee_mixed_rejects_split_that_does_not_match_fee(): void
    {
        $staff = $this->makeUser(User::ROLE_STAFF);
        $booking = $this->makeBooking();
        Carbon::setTestNow(Carbon::parse('2026-08-13 10:00', OperationalReportService::TIMEZONE));

        $this->actingAs($staff);
        Filament::setCurrentPanel(Filament::getPanel('tenantAdmin'));

        Livewire::test(DailyRoster::class)
            ->callTableAction('collectFee', $booking, [
                'fee_type' => Doctor::FEE_CONSULTATION,
                'method' => ChamberCashEntry::METHOD_MIXED,
                'cash_taka' => 100,
                'online_taka' => 100,
                'online_method' => ChamberCashEntry::METHOD_BKASH,
                'waived' => false,
                'note' => null,
            ])
            ->assertHasTableActionErrors();

        $this->assertDatabaseMissing('chamber_cash_entries', [
            'booking_id' => $booking->id,
        ]);

        Carbon::setTestNow();
    }

    public function test_record_patient_income_mixed_requires_split_to_equal_fee(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        app(ChamberCashService::class)->recordPatientIncome(
            $this->makeBooking(),
            $this->makeUser(User::ROLE_STAFF),
            ChamberCashEntry::METHOD_MIXED,
            occurredOn: Carbon::parse('2026-08-13', OperationalReportService::TIMEZONE),
        );
    }

    public function test_fee_receipt_uses_the_prescription_pad(): void
    {
        $this->doctor->update([
            'qualifications' => 'MBBS, FCPS',
            'registration_number' => 'A-12345',
        ]);
        $this->chamber->update([
            'address' => 'Mehedibag (near Max Hospital), Chattogram',
            'contact' => '01800-000000',
        ]);

        $staff = $this->makeUser(User::ROLE_STAFF);
        $booking = $this->makeBooking();
        $entry = app(ChamberCashService::class)->recordPatientIncome(
            $booking,
            $staff,
            ChamberCashEntry::METHOD_CASH,
            occurredOn: Carbon::parse('2026-08-13', OperationalReportService::TIMEZONE),
        );

        $this->actingAs($staff);
        $response = $this->get(tenant_web_route('fee-receipts.show', ['entry' => $entry]));

        $response->assertOk();
        $response->assertSee('Hind Siliguri', false);
        $response->assertSee('size: A4', false);
        $response->assertDontSee('landscape', false);
        $response->assertDontSee('max-width: 320px', false);
        $response->assertDontSee('family=Inter', false);
        $response->assertSee('pad-header', false);
        $response->assertSee('patient-band', false);
        $response->assertSee('rx-symbol', false);
        $response->assertSee('Dr. Cash', false);
        $response->assertSee('MBBS, FCPS', false);
        $response->assertSee('Fatima Rahman', false);
        $response->assertSee('Mehedibag (near Max Hospital), Chattogram', false);
        $response->assertSee('800/-', false);
        $response->assertSee('Taka Eight Hundred Only', false);
        $this->assertSame(1, $entry->fresh()->receipt_number);
    }

    public function test_fee_receipt_numbers_run_per_day(): void
    {
        $staff = $this->makeUser(User::ROLE_STAFF);
        $day = Carbon::parse('2026-08-13', OperationalReportService::TIMEZONE);
        $first = app(ChamberCashService::class)->recordPatientIncome(
            $this->makeBooking('waiting', 1),
            $staff,
            ChamberCashEntry::METHOD_CASH,
            occurredOn: $day,
        );
        $second = app(ChamberCashService::class)->recordPatientIncome(
            $this->makeBooking('waiting', 2),
            $staff,
            ChamberCashEntry::METHOD_CASH,
            occurredOn: $day,
        );

        $this->actingAs($staff);
        $this->get(tenant_web_route('fee-receipts.show', ['entry' => $first]))->assertOk();
        $this->get(tenant_web_route('fee-receipts.show', ['entry' => $second]))->assertOk();

        $this->assertSame(1, $first->fresh()->receipt_number);
        $this->assertSame(2, $second->fresh()->receipt_number);
    }

    public function test_guest_cannot_open_a_fee_receipt(): void
    {
        $entry = app(ChamberCashService::class)->recordPatientIncome(
            $this->makeBooking(),
            $this->makeUser(User::ROLE_STAFF),
            ChamberCashEntry::METHOD_CASH,
            occurredOn: Carbon::parse('2026-08-13', OperationalReportService::TIMEZONE),
        );

        $this->withHeaders(['Accept' => 'application/json'])
            ->get(tenant_web_route('fee-receipts.show', ['entry' => $entry]))
            ->assertUnauthorized();
    }

    public function test_queue_staff_cannot_open_a_fee_receipt(): void
    {
        $staff = $this->makeUser(User::ROLE_STAFF);
        $entry = app(ChamberCashService::class)->recordPatientIncome(
            $this->makeBooking(),
            $staff,
            ChamberCashEntry::METHOD_CASH,
            occurredOn: Carbon::parse('2026-08-13', OperationalReportService::TIMEZONE),
        );
        $queue = User::create([
            'name' => 'Queue only',
            'email' => 'queue@cashbook-clinic.loc',
            'password' => Hash::make('secret'),
            'role' => User::ROLE_STAFF,
            'tenant_id' => 'cashbook-clinic',
            'desk_jobs' => [StaffDeskJobs::JOB_QUEUE],
        ]);

        $this->actingAs($queue)
            ->get(tenant_web_route('fee-receipts.show', ['entry' => $entry]))
            ->assertForbidden();
    }

    public function test_rent_and_other_income_have_no_fee_receipt(): void
    {
        $admin = $this->makeUser(User::ROLE_ADMIN);
        $day = Carbon::parse('2026-08-13', OperationalReportService::TIMEZONE);
        $rent = app(ChamberCashService::class)->recordExpense(
            $admin,
            5000,
            ChamberCashEntry::CATEGORY_RENT,
            ChamberCashEntry::METHOD_CASH,
            $day,
            $this->chamber->id,
        );
        $other = app(ChamberCashService::class)->recordOtherIncome(
            $admin,
            200,
            ChamberCashEntry::CATEGORY_OTHER_INCOME,
            ChamberCashEntry::METHOD_CASH,
            $day,
            $this->chamber->id,
        );

        $this->actingAs($admin);
        $this->get(tenant_web_route('fee-receipts.show', ['entry' => $rent]))->assertNotFound();
        $this->get(tenant_web_route('fee-receipts.show', ['entry' => $other]))->assertNotFound();
    }

    public function test_branch_staff_cannot_print_another_centre_fee_receipt(): void
    {
        $uttara = Chamber::create(['name' => 'Uttara']);
        $uttaraSession = ScheduleSession::create([
            'chamber_id' => $uttara->id,
            'doctor_id' => $this->doctor->id,
            'day_of_week' => 1,
            'session_name' => 'Uttara morning',
            'start_time' => '09:00',
            'end_time' => '12:00',
            'slot_cap' => 20,
        ]);
        $booking = Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $uttaraSession->id,
            'booking_date' => '2026-08-13',
            'patient_name' => 'Uttara Patient',
            'patient_phone' => '01719999999',
            'serial_number' => 9,
            'status' => 'waiting',
        ]);
        $staff = $this->makeUser(User::ROLE_STAFF);
        $entry = app(ChamberCashService::class)->recordPatientIncome(
            $booking,
            $staff,
            ChamberCashEntry::METHOD_CASH,
            occurredOn: Carbon::parse('2026-08-13', OperationalReportService::TIMEZONE),
        );

        $mehedibagStaff = User::create([
            'name' => 'Mehedibag money',
            'email' => 'mehedibag@cashbook-clinic.loc',
            'password' => Hash::make('secret'),
            'role' => User::ROLE_STAFF,
            'tenant_id' => 'cashbook-clinic',
        ]);
        StaffDeskScope::syncChambers($mehedibagStaff, [$this->chamber->id]);

        $this->actingAs($mehedibagStaff)
            ->get(tenant_web_route('fee-receipts.show', ['entry' => $entry]))
            ->assertForbidden();
    }
}
