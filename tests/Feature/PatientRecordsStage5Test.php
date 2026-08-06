<?php

namespace Tests\Feature;

use App\Models\BillingPayment;
use App\Models\Booking;
use App\Models\Chamber;
use App\Models\Doctor;
use App\Models\LiveSession;
use App\Models\ScheduleSession;
use App\Models\Tenant;
use App\Models\WebPage;
use App\Services\SellerOverviewService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientRecordsStage5Test extends TestCase
{
    use RefreshDatabase;

    private SellerOverviewService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(SellerOverviewService::class);
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    private function seedTenant(string $id, array $overrides = []): Tenant
    {
        return Tenant::create(array_merge([
            'id' => $id,
            'name' => ucfirst($id).' Clinic',
            'plan_tier' => 'solo',
            'billing_status' => 'active',
            'sms_balance' => 100,
            'created_at' => now()->subDays(30),
        ], $overrides));
    }

    public function test_quiet_clients_ranks_by_days_since_last_live_session(): void
    {
        Carbon::setTestNow('2026-08-06 10:00:00');

        $quiet = $this->seedTenant('quiet-doc');
        $active = $this->seedTenant('active-doc');

        tenancy()->initialize($quiet);
        $session = $this->makeScheduleSession();
        LiveSession::create([
            'schedule_session_id' => $session->id,
            'session_date' => '2026-07-15',
            'status' => 'completed',
            'started_at' => '2026-07-15 09:00:00',
            'completed_at' => '2026-07-15 12:00:00',
        ]);
        tenancy()->end();

        tenancy()->initialize($active);
        $activeSession = $this->makeScheduleSession();
        LiveSession::create([
            'schedule_session_id' => $activeSession->id,
            'session_date' => '2026-08-05',
            'status' => 'completed',
            'started_at' => '2026-08-05 09:00:00',
            'completed_at' => '2026-08-05 12:00:00',
        ]);
        tenancy()->end();

        $rows = $this->service->quietClients(Carbon::parse('2026-08-06'));

        $this->assertCount(1, $rows);
        $this->assertSame('quiet-doc', $rows->first()['tenant_id']);
        $this->assertSame(22, $rows->first()['days_since_last_session']);

        Carbon::setTestNow();
    }

    public function test_quiet_clients_flags_schedule_never_started(): void
    {
        Carbon::setTestNow('2026-08-06 10:00:00');

        $tenant = $this->seedTenant('stalled-doc', [
            'created_at' => now()->subDays(14),
        ]);

        tenancy()->initialize($tenant);
        $this->makeScheduleSession();
        tenancy()->end();

        $rows = $this->service->quietClients();

        $this->assertCount(1, $rows);
        $this->assertTrue($rows->first()['scheduled_never_started']);
        $this->assertNull($rows->first()['days_since_last_session']);

        Carbon::setTestNow();
    }

    public function test_quiet_clients_detects_booking_drop_against_baseline(): void
    {
        Carbon::setTestNow('2026-08-06 10:00:00'); // Thursday

        $tenant = $this->seedTenant('dropping-doc');
        tenancy()->initialize($tenant);
        $session = $this->makeScheduleSession();

        for ($week = 1; $week <= 4; $week++) {
            for ($i = 0; $i < 10; $i++) {
                $this->makeBooking($session, now()->subWeeks($week)->startOfWeek()->addDays(1), $week * 10 + $i);
            }
        }

        $this->makeBooking($session, now()->startOfWeek(), 99);
        tenancy()->end();

        tenancy()->initialize($tenant);
        LiveSession::create([
            'schedule_session_id' => $session->id,
            'session_date' => now()->subDay()->toDateString(),
            'status' => 'completed',
            'started_at' => now()->subDay(),
            'completed_at' => now()->subDay()->addHours(3),
        ]);
        tenancy()->end();

        $rows = $this->service->quietClients();

        $this->assertCount(1, $rows);
        $this->assertSame(1, $rows->first()['bookings_this_week']);
        $this->assertSame(10.0, $rows->first()['bookings_baseline_weekly']);
        $this->assertGreaterThanOrEqual(80, $rows->first()['booking_drop_percent']);

        Carbon::setTestNow();
    }

    public function test_go_live_funnel_reports_stall_step(): void
    {
        Carbon::setTestNow('2026-08-06 10:00:00');

        $tenant = $this->seedTenant('funnel-doc', [
            'created_at' => now()->subDays(10),
        ]);

        tenancy()->initialize($tenant);
        Chamber::create(['name' => 'Main chamber']);
        tenancy()->end();

        $rows = $this->service->goLiveFunnel(90);

        $match = $rows->firstWhere('tenant_id', 'funnel-doc');
        $this->assertNotNull($match);
        $this->assertTrue($match['steps']['account_made']);
        $this->assertTrue($match['steps']['chambers_added']);
        $this->assertFalse($match['steps']['schedule_set']);
        $this->assertSame('schedule_set', $match['stall_step']);
        $this->assertFalse($match['is_live']);

        Carbon::setTestNow();
    }

    public function test_go_live_funnel_marks_live_when_first_session_started(): void
    {
        Carbon::setTestNow('2026-08-06 10:00:00');

        $tenant = $this->seedTenant('live-doc', [
            'created_at' => now()->subDays(5),
        ]);

        tenancy()->initialize($tenant);
        $chamber = Chamber::create(['name' => 'Main']);
        $doctor = Doctor::create(['name' => 'Dr Live']);
        $session = ScheduleSession::create([
            'chamber_id' => $chamber->id,
            'doctor_id' => $doctor->id,
            'day_of_week' => 3,
            'session_name' => 'Evening',
            'start_time' => '18:00',
            'end_time' => '21:00',
            'slot_cap' => 10,
        ]);
        WebPage::create([
            'title' => 'Home',
            'slug' => '/',
            'content' => [],
            'is_published' => true,
        ]);
        $this->makeBooking($session, now()->subDay());
        LiveSession::create([
            'schedule_session_id' => $session->id,
            'session_date' => now()->subDay()->toDateString(),
            'status' => 'completed',
            'started_at' => now()->subDay(),
            'completed_at' => now()->subDay()->addHours(2),
        ]);
        tenancy()->end();

        $match = $this->service->goLiveFunnel(90)->firstWhere('tenant_id', 'live-doc');

        $this->assertNotNull($match);
        $this->assertTrue($match['is_live']);
        $this->assertNull($match['stall_step']);
        $this->assertTrue($match['steps']['first_live_session']);

        Carbon::setTestNow();
    }

    public function test_sms_credit_warnings_lists_low_and_zero_balances(): void
    {
        $this->seedTenant('sms-empty', ['sms_balance' => 0]);
        $this->seedTenant('sms-low', ['sms_balance' => 3]);
        $this->seedTenant('sms-ok', ['sms_balance' => 50]);

        $rows = $this->service->smsCreditWarnings();

        $this->assertCount(2, $rows);
        $this->assertSame('sms-empty', $rows->first()['tenant_id']);
        $this->assertTrue($rows->first()['is_empty']);
        $this->assertSame('sms-low', $rows->last()['tenant_id']);
        $this->assertFalse($rows->last()['is_empty']);
    }

    public function test_overdue_payments_lists_past_due_tenants_with_days(): void
    {
        Carbon::setTestNow('2026-08-06 10:00:00');

        $tenant = $this->seedTenant('past-due-doc', [
            'billing_status' => 'past_due',
            'setup_paid_at' => '2026-05-01 00:00:00',
        ]);

        BillingPayment::create([
            'tenant_id' => $tenant->id,
            'type' => BillingPayment::TYPE_MONTHLY,
            'period' => '2026-06',
            'list_amount' => 1000,
            'discount_amount' => 0,
            'amount_paid' => 1000,
            'confirmed_at' => '2026-06-05 10:00:00',
        ]);

        $rows = $this->service->overduePayments();

        $match = $rows->firstWhere('tenant_id', 'past-due-doc');
        $this->assertNotNull($match);
        $this->assertSame('billing_past_due', $match['reason']);
        $this->assertGreaterThan(30, $match['days_overdue']);

        Carbon::setTestNow();
    }

    public function test_overdue_payments_includes_unpaid_setup(): void
    {
        Carbon::setTestNow('2026-08-06 10:00:00');

        $tenant = $this->seedTenant('setup-due-doc', [
            'billing_status' => 'active',
            'created_at' => now()->subDays(20),
        ]);

        $rows = $this->service->overduePayments();
        $match = $rows->firstWhere('tenant_id', 'setup-due-doc');

        $this->assertNotNull($match);
        $this->assertSame('setup_unpaid', $match['reason']);
        $this->assertSame(20, $match['days_overdue']);

        Carbon::setTestNow();
    }

    private function makeScheduleSession(): ScheduleSession
    {
        $chamber = Chamber::create(['name' => 'Main']);
        $doctor = Doctor::create(['name' => 'Dr Test']);

        return ScheduleSession::create([
            'chamber_id' => $chamber->id,
            'doctor_id' => $doctor->id,
            'day_of_week' => Carbon::today()->dayOfWeek,
            'session_name' => 'Morning',
            'start_time' => '09:00',
            'end_time' => '12:00',
            'slot_cap' => 10,
        ]);
    }

    private function makeBooking(ScheduleSession $session, Carbon $date, int $serial = 1): Booking
    {
        return Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $session->id,
            'booking_date' => $date->toDateString(),
            'patient_name' => 'Test Patient',
            'patient_phone' => '01812345678',
            'serial_number' => $serial,
            'status' => 'completed',
        ]);
    }
}
