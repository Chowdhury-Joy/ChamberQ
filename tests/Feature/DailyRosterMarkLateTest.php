<?php

namespace Tests\Feature;

use App\Filament\TenantAdmin\Pages\DailyRoster;
use App\Jobs\SendDoctorLateNotices;
use App\Models\Booking;
use App\Models\Chamber;
use App\Models\Doctor;
use App\Models\LiveSession;
use App\Models\ScheduleSession;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Mark Late from Daily Roster — same LiveSessionService path as Live Queue
 * Control, so staff can warn waiting patients before opening the queue screen.
 */
class DailyRosterMarkLateTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected ScheduleSession $session;

    protected Doctor $doctor;

    protected User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['id' => 'roster-late', 'plan_tier' => 'solo']);
        tenancy()->initialize($this->tenant);

        $chamber = Chamber::create(['name' => 'Main']);
        $this->doctor = Doctor::create(['name' => 'Dr Late']);

        $this->session = ScheduleSession::create([
            'chamber_id' => $chamber->id,
            'doctor_id' => $this->doctor->id,
            'day_of_week' => Carbon::today()->dayOfWeek,
            'session_name' => 'Morning',
            'start_time' => '09:00',
            'end_time' => '12:00',
            'slot_cap' => 10,
        ]);

        $this->staff = User::create([
            'name' => 'Desk',
            'email' => 'staff@roster-late.test',
            'password' => Hash::make('secret'),
            'role' => User::ROLE_STAFF,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    public function test_mark_late_creates_a_delayed_live_session_without_starting(): void
    {
        $this->makeWaitingBooking('Patient One', 1);

        $this->assertSame(0, LiveSession::count());

        $this->rosterPage()
            ->callTableAction('markLate', data: [
                'schedule_session_id' => $this->session->id,
                'delay_minutes' => 30,
            ])
            ->assertHasNoTableActionErrors();

        $live = LiveSession::first();
        $this->assertNotNull($live);
        $this->assertSame('delayed', $live->status);
        $this->assertSame(30, (int) $live->delay_minutes);
        $this->assertNull($live->started_at);
    }

    public function test_mark_late_reuses_todays_scheduled_live_session(): void
    {
        $this->makeWaitingBooking('Patient One', 1);

        $existing = LiveSession::create([
            'schedule_session_id' => $this->session->id,
            'session_date' => Carbon::today()->toDateString(),
            'status' => 'scheduled',
        ]);

        $this->rosterPage()
            ->callTableAction('markLate', data: [
                'schedule_session_id' => $this->session->id,
                'delay_minutes' => 45,
            ])
            ->assertHasNoTableActionErrors();

        $this->assertSame(1, LiveSession::count());
        $this->assertSame('delayed', $existing->fresh()->status);
        $this->assertSame(45, (int) $existing->fresh()->delay_minutes);
    }

    public function test_mark_late_is_hidden_once_the_session_is_active(): void
    {
        LiveSession::create([
            'schedule_session_id' => $this->session->id,
            'session_date' => Carbon::today()->toDateString(),
            'status' => 'active',
            'started_at' => now(),
        ]);

        $this->rosterPage()->assertTableActionHidden('markLate');
    }

    public function test_mark_late_dispatches_sms_notices_after_the_response(): void
    {
        $this->doctor->update([
            'notify_channels' => array_replace_recursive(
                Doctor::defaultNotifyChannels(),
                [Doctor::NOTIFY_DOCTOR_LATE => ['sms' => true, 'whatsapp' => false]],
            ),
        ]);

        $this->makeWaitingBooking('Patient One', 1);
        $this->makeWaitingBooking('Patient Two', 2);

        Bus::fake();

        $this->rosterPage()
            ->callTableAction('markLate', data: [
                'schedule_session_id' => $this->session->id,
                'delay_minutes' => 30,
            ])
            ->assertHasNoTableActionErrors();

        Bus::assertDispatchedAfterResponse(
            SendDoctorLateNotices::class,
            fn (SendDoctorLateNotices $job) => count($job->bookingIds) === 2 && $job->delayMinutes === 30,
        );
    }

    public function test_mark_late_offers_whatsapp_hand_off_when_late_whatsapp_is_on(): void
    {
        $this->doctor->update([
            'notify_channels' => array_replace_recursive(
                Doctor::defaultNotifyChannels(),
                [Doctor::NOTIFY_DOCTOR_LATE => ['sms' => false, 'whatsapp' => true]],
            ),
        ]);

        $waiting = $this->makeWaitingBooking('Patient One', 1);

        $this->rosterPage()
            ->callTableAction('markLate', data: [
                'schedule_session_id' => $this->session->id,
                'delay_minutes' => 15,
            ])
            ->assertHasNoTableActionErrors()
            ->assertSet('delayedNotifyBookingIds', [$waiting->id])
            ->assertSet('delayedNotifyMinutes', 15)
            ->assertTableActionVisible('notifyDelayed');
    }

    protected function rosterPage(): \Livewire\Features\SupportTesting\Testable
    {
        Filament::setCurrentPanel('tenantAdmin');
        $this->actingAs($this->staff);

        return Livewire::test(DailyRoster::class);
    }

    protected function makeWaitingBooking(string $name, int $serial): Booking
    {
        return Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $this->session->id,
            'booking_date' => Carbon::today()->toDateString(),
            'patient_name' => $name,
            'patient_phone' => '0171234567'.str_pad((string) $serial, 2, '0', STR_PAD_LEFT),
            'serial_number' => $serial,
            'status' => 'waiting',
        ]);
    }
}
