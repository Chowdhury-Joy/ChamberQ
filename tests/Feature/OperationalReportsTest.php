<?php

namespace Tests\Feature;

use App\Filament\TenantAdmin\Pages\OperationalReports;
use App\Models\Booking;
use App\Models\Chamber;
use App\Models\Doctor;
use App\Models\Domain;
use App\Models\ScheduleSession;
use App\Models\Tenant;
use App\Models\User;
use App\Services\OperationalReportService;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class OperationalReportsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private ScheduleSession $schedule;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['id' => 'ops-reports', 'plan_tier' => 'solo']);
        Domain::create(['domain' => 'ops-reports.localhost', 'tenant_id' => 'ops-reports']);
        tenancy()->initialize($this->tenant);

        $chamber = Chamber::create(['name' => 'Main']);
        $doctor = Doctor::create(['name' => 'Dr. Solo']);
        $this->schedule = ScheduleSession::create([
            'chamber_id' => $chamber->id,
            'doctor_id' => $doctor->id,
            'day_of_week' => Carbon::parse('2026-07-28', OperationalReportService::TIMEZONE)->dayOfWeek,
            'session_name' => 'Morning',
            'start_time' => '09:00',
            'end_time' => '12:00',
            'slot_cap' => 40,
        ]);
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    private function makeBooking(string $date, string $status, int $serial, string $name = 'Patient'): Booking
    {
        return Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $this->schedule->id,
            'booking_date' => $date,
            'patient_name' => $name,
            'patient_phone' => '0171'.str_pad((string) $serial, 7, '0', STR_PAD_LEFT),
            'serial_number' => $serial,
            'status' => $status,
        ]);
    }

    private function makeUser(string $role): User
    {
        return User::create([
            'name' => ucfirst($role),
            'email' => $role.'@ops-reports.loc',
            'password' => Hash::make('secret'),
            'role' => $role,
            'tenant_id' => 'ops-reports',
        ]);
    }

    public function test_day_report_aggregates_status_counts(): void
    {
        $today = '2026-07-28';
        $this->makeBooking($today, 'completed', 1);
        $this->makeBooking($today, 'completed', 2);
        $this->makeBooking($today, 'waiting', 3);
        $this->makeBooking($today, 'called', 4);
        $this->makeBooking($today, 'in_chamber', 5);
        $this->makeBooking($today, 'skipped', 6);
        $this->makeBooking($today, 'no_show', 7);
        $this->makeBooking($today, 'cancelled', 8);
        $this->makeBooking('2026-07-27', 'completed', 9); // other day

        $service = app(OperationalReportService::class);
        $counts = $service->countsForDate(Carbon::parse($today, OperationalReportService::TIMEZONE));

        $this->assertSame(8, $counts['total']);
        $this->assertSame(2, $counts['completed']);
        $this->assertSame(1, $counts['waiting']);
        $this->assertSame(1, $counts['called']);
        $this->assertSame(1, $counts['in_chamber']);
        $this->assertSame(1, $counts['skipped']);
        $this->assertSame(1, $counts['no_show']);
        $this->assertSame(1, $counts['cancelled']);
    }

    public function test_week_report_rolls_up_by_day_with_week_totals(): void
    {
        // Week of Sun 26 Jul – Sat 1 Aug 2026 (Asia/Dhaka)
        $this->makeBooking('2026-07-26', 'completed', 1);
        $this->makeBooking('2026-07-26', 'waiting', 2);
        $this->makeBooking('2026-07-28', 'completed', 3);
        $this->makeBooking('2026-07-28', 'cancelled', 4);
        $this->makeBooking('2026-08-01', 'no_show', 5);
        $this->makeBooking('2026-07-25', 'completed', 6); // prior Saturday — outside week
        $this->makeBooking('2026-08-02', 'waiting', 7); // next Sunday — outside week

        $service = app(OperationalReportService::class);
        $anchor = Carbon::parse('2026-07-28', OperationalReportService::TIMEZONE);
        [$start, $end] = $service->weekRange($anchor);

        $this->assertSame('2026-07-26', $start->toDateString());
        $this->assertSame('2026-08-01', $end->toDateString());

        $totals = $service->countsBetween($start, $end);
        $this->assertSame(5, $totals['total']);
        $this->assertSame(2, $totals['completed']);
        $this->assertSame(1, $totals['waiting']);
        $this->assertSame(1, $totals['cancelled']);
        $this->assertSame(1, $totals['no_show']);

        $byDay = $service->dailyBreakdown($start, $end);
        $this->assertCount(7, $byDay);
        $this->assertSame(2, $byDay['2026-07-26']['total']);
        $this->assertSame(2, $byDay['2026-07-28']['total']);
        $this->assertSame(1, $byDay['2026-08-01']['total']);
        $this->assertSame(0, $byDay['2026-07-27']['total']);
    }

    public function test_month_report_aggregates_by_week_with_month_totals(): void
    {
        $this->makeBooking('2026-07-01', 'completed', 1); // Wed
        $this->makeBooking('2026-07-05', 'waiting', 2); // Sun — new week
        $this->makeBooking('2026-07-05', 'cancelled', 3);
        $this->makeBooking('2026-07-28', 'completed', 4);
        $this->makeBooking('2026-07-28', 'no_show', 5);
        $this->makeBooking('2026-06-30', 'completed', 6); // outside July
        $this->makeBooking('2026-08-01', 'waiting', 7); // outside July

        $service = app(OperationalReportService::class);
        $anchor = Carbon::parse('2026-07-15', OperationalReportService::TIMEZONE);
        [$start, $end] = $service->monthRange($anchor);

        $this->assertSame('2026-07-01', $start->toDateString());
        $this->assertSame('2026-07-31', $end->toDateString());

        $totals = $service->countsBetween($start, $end);
        $this->assertSame(5, $totals['total']);
        $this->assertSame(2, $totals['completed']);
        $this->assertSame(1, $totals['waiting']);
        $this->assertSame(1, $totals['cancelled']);
        $this->assertSame(1, $totals['no_show']);

        $weeks = $service->weeklyBreakdownInMonth($anchor);
        $this->assertNotEmpty($weeks);

        $weekTotals = array_sum(array_column($weeks, 'total'));
        $this->assertSame(5, $weekTotals);

        $firstWeek = collect($weeks)->firstWhere('week_start', '2026-06-28');
        $this->assertNotNull($firstWeek);
        $this->assertSame(1, $firstWeek['completed']);
        $this->assertSame(1, $firstWeek['total']);
    }

    public function test_day_report_page_renders_headline_numbers(): void
    {
        $today = '2026-07-28';
        $this->makeBooking($today, 'completed', 1);
        $this->makeBooking($today, 'completed', 2);
        $this->makeBooking($today, 'waiting', 3);
        $this->makeBooking($today, 'called', 4);
        $this->makeBooking($today, 'no_show', 5);

        $this->actingAs($this->makeUser(User::ROLE_ADMIN));
        Filament::setCurrentPanel(Filament::getPanel('tenantAdmin'));

        $component = Livewire::test(OperationalReports::class)
            ->set('anchorDate', $today)
            ->assertOk()
            ->assertSee('Total bookings')
            ->assertSee('Still in queue')
            ->assertSee('Needs attention')
            ->assertSee('Status breakdown');

        $page = $component->instance();

        $this->assertSame(5, $page->getTotals()['total']);
        $this->assertSame(2, $page->getQueueCount());
        $this->assertSame(1, $page->getProblemCount());
        $this->assertSame(40, $page->getCompletionRate());
    }

    public function test_week_and_month_periods_render_breakdown_tables(): void
    {
        $this->makeBooking('2026-07-26', 'completed', 1);
        $this->makeBooking('2026-07-28', 'cancelled', 2);

        $this->actingAs($this->makeUser(User::ROLE_ADMIN));
        Filament::setCurrentPanel(Filament::getPanel('tenantAdmin'));

        Livewire::test(OperationalReports::class)
            ->set('anchorDate', '2026-07-28')
            ->set('period', 'week')
            ->assertOk()
            ->assertSee('Daily breakdown')
            ->assertSee('Week total')
            ->set('period', 'month')
            ->assertOk()
            ->assertSee('Weekly breakdown')
            ->assertSee('Month total');
    }

    public function test_completion_rate_is_null_when_no_bookings(): void
    {
        $this->actingAs($this->makeUser(User::ROLE_ADMIN));
        Filament::setCurrentPanel(Filament::getPanel('tenantAdmin'));

        $page = Livewire::test(OperationalReports::class)
            ->set('anchorDate', '2026-07-28')
            ->assertOk()
            ->assertSee('No bookings recorded for this period yet.')
            ->instance();

        $this->assertNull($page->getCompletionRate());
        $this->assertSame(0, $page->getQueueCount());
        $this->assertSame(0, $page->getProblemCount());
    }

    public function test_admin_and_doctor_can_access_reports_but_staff_cannot(): void
    {
        $admin = $this->makeUser(User::ROLE_ADMIN);
        $doctor = $this->makeUser(User::ROLE_DOCTOR);
        $staff = $this->makeUser(User::ROLE_STAFF);

        $this->actingAs($admin);
        $this->assertTrue(OperationalReports::canAccess());

        $this->actingAs($doctor);
        $this->assertTrue(OperationalReports::canAccess());

        $this->actingAs($staff);
        $this->assertFalse(OperationalReports::canAccess());
    }
}
