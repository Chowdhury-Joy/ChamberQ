<?php

namespace Tests\Feature;

use App\Filament\TenantAdmin\Pages\DailyRoster;
use App\Models\Booking;
use App\Models\Chamber;
use App\Models\Doctor;
use App\Models\ScheduleSession;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class DailyRosterDateFilterTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $staff;

    private ScheduleSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-08-19 10:00'));

        $this->tenant = Tenant::create(['id' => 'roster-date', 'plan_tier' => 'solo']);
        tenancy()->initialize($this->tenant);

        $chamber = Chamber::create(['name' => 'Main']);
        $doctor = Doctor::create(['name' => 'Dr Date']);

        $this->session = ScheduleSession::create([
            'chamber_id' => $chamber->id,
            'doctor_id' => $doctor->id,
            'day_of_week' => Carbon::parse('2026-08-22')->dayOfWeek,
            'session_name' => 'Morning',
            'start_time' => '09:00',
            'end_time' => '12:00',
            'slot_cap' => 10,
        ]);

        $this->staff = User::create([
            'name' => 'Desk',
            'email' => 'staff@roster-date.test',
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

    public function test_staff_can_switch_the_roster_to_another_date(): void
    {
        $today = $this->makeBooking('Today Patient', Carbon::today()->toDateString(), 1);
        $saturday = $this->makeBooking('Saturday Patient', '2026-08-22', 1);

        Filament::setCurrentPanel('tenantAdmin');
        $this->actingAs($this->staff);

        Livewire::test(DailyRoster::class)
            ->assertSet('rosterDate', '2026-08-19')
            ->assertCanSeeTableRecords([$today])
            ->assertCanNotSeeTableRecords([$saturday])
            ->set('rosterDate', '2026-08-22')
            ->assertCanSeeTableRecords([$saturday])
            ->assertCanNotSeeTableRecords([$today]);
    }

    public function test_walk_in_and_mark_late_stay_on_today_when_viewing_another_date(): void
    {
        Filament::setCurrentPanel('tenantAdmin');
        $this->actingAs($this->staff);

        Livewire::test(DailyRoster::class)
            ->set('rosterDate', '2026-08-22')
            ->assertTableActionHidden('newWalkIn')
            ->assertTableActionHidden('markLate');
    }

    private function makeBooking(string $name, string $date, int $serial): Booking
    {
        return Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $this->session->id,
            'booking_date' => $date,
            'patient_name' => $name,
            'patient_phone' => '017100000'.str_pad((string) $serial, 2, '0', STR_PAD_LEFT),
            'serial_number' => $serial,
            'status' => 'waiting',
        ]);
    }
}
