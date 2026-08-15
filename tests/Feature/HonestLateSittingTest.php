<?php

namespace Tests\Feature;

use App\Filament\TenantAdmin\Pages\DailyRoster;
use App\Filament\TenantAdmin\Pages\LiveQueueControl;
use App\Models\Booking;
use App\Models\Chamber;
use App\Models\Doctor;
use App\Models\LiveSession;
use App\Models\ScheduleSession;
use App\Models\Tenant;
use App\Models\User;
use App\Services\LiveSessionService;
use App\Services\SittingPrompt;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Honest late sitting — ticket follows the chair.
 *
 * @see decisions.md (Business_Logic: ticket follows the chair)
 */
class HonestLateSittingTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected ScheduleSession $session;

    protected LiveSessionService $service;

    protected string $today;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'id' => 'honest-late',
            'plan_tier' => 'solo',
            'eta_model' => Tenant::ETA_SCHEDULE_GUESS,
        ]);
        tenancy()->initialize($this->tenant);

        $chamber = Chamber::create(['name' => 'Main']);
        $doctor = Doctor::create(['name' => 'Dr Honest']);

        $this->session = ScheduleSession::create([
            'chamber_id' => $chamber->id,
            'doctor_id' => $doctor->id,
            'day_of_week' => Carbon::today()->dayOfWeek,
            'session_name' => 'Evening',
            'start_time' => '17:00',
            'end_time' => '20:00',
            'slot_cap' => 10,
        ]);

        $this->today = Carbon::today()->toDateString();
        $this->service = app(LiveSessionService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        tenancy()->end();

        parent::tearDown();
    }

    public function test_delayed_then_start_early_uses_actual_start_not_announced_delay(): void
    {
        $live = $this->delayedSession(30);
        $booking = $this->waitingBooking(1);

        // 5:20 — twenty minutes after sitting, ten before announced 5:30.
        Carbon::setTestNow(Carbon::parse($this->today.' 17:20:00'));
        $this->service->startSession($this->session);

        $live->refresh();
        $this->assertSame('active', $live->status);
        $this->assertSame(30, (int) $live->delay_minutes);

        $estimate = $this->service->estimatedTimeForBooking($booking->fresh());
        $this->assertNotNull($estimate);

        $expected = Carbon::parse($this->today.' 17:20:00');
        $this->assertTrue(
            $estimate['actual_estimate']->diffInMinutes($expected) <= 1,
            'Expected estimate from 5:20, got '.$estimate['actual_estimate']->toDateTimeString()
        );
    }

    public function test_delayed_then_start_on_announced_time_uses_announced_time(): void
    {
        $live = $this->delayedSession(30);
        $booking = $this->waitingBooking(1);

        Carbon::setTestNow(Carbon::parse($this->today.' 17:30:00'));
        $this->service->startSession($this->session);

        $live->refresh();
        $this->assertSame('active', $live->status);

        $estimate = $this->service->estimatedTimeForBooking($booking->fresh());
        $expected = Carbon::parse($this->today.' 17:30:00');
        $this->assertTrue(
            $estimate['actual_estimate']->diffInMinutes($expected) <= 1,
            'Expected estimate from 5:30, got '.$estimate['actual_estimate']->toDateTimeString()
        );
    }

    public function test_start_before_sitting_time_does_not_pull_patients_in_early(): void
    {
        LiveSession::create([
            'schedule_session_id' => $this->session->id,
            'session_date' => $this->today,
            'status' => 'scheduled',
        ]);
        $booking = $this->waitingBooking(1);

        Carbon::setTestNow(Carbon::parse($this->today.' 16:58:00'));
        $this->service->startSession($this->session);

        $estimate = $this->service->estimatedTimeForBooking($booking->fresh());
        $expected = Carbon::parse($this->today.' 17:00:00');
        $this->assertTrue(
            $estimate['actual_estimate']->diffInMinutes($expected) <= 1,
            'Expected estimate from 5:00, got '.$estimate['actual_estimate']->toDateTimeString()
        );
    }

    public function test_start_late_without_mark_late_uses_actual_start(): void
    {
        LiveSession::create([
            'schedule_session_id' => $this->session->id,
            'session_date' => $this->today,
            'status' => 'scheduled',
        ]);
        $booking = $this->waitingBooking(1);

        Carbon::setTestNow(Carbon::parse($this->today.' 18:00:00'));
        $this->service->startSession($this->session);

        $live = LiveSession::first();
        $this->assertSame('active', $live->status);
        $this->assertSame(0, (int) $live->delay_minutes);

        $estimate = $this->service->estimatedTimeForBooking($booking->fresh());
        $expected = Carbon::parse($this->today.' 18:00:00');
        $this->assertTrue(
            $estimate['actual_estimate']->diffInMinutes($expected) <= 1,
            'Expected estimate from 6:00, got '.$estimate['actual_estimate']->toDateTimeString()
        );
    }

    public function test_not_started_delayed_clock_uses_sitting_plus_delay(): void
    {
        $live = $this->delayedSession(30);

        Carbon::setTestNow(Carbon::parse($this->today.' 17:15:00'));

        $effective = $live->effectiveStartTime();
        $expected = Carbon::parse($this->today.' 17:30:00');
        $this->assertTrue($effective->equalTo($expected));
    }

    public function test_second_mark_late_must_be_larger_than_current(): void
    {
        $this->delayedSession(30);
        $this->waitingBooking(1);

        $this->queuePage()
            ->assertActionVisible('markLate')
            ->callAction('markLate', ['delay_minutes' => 60])
            ->assertHasNoActionErrors();

        $this->assertSame(60, (int) LiveSession::first()->fresh()->delay_minutes);

        $this->queuePage()
            ->callAction('markLate', ['delay_minutes' => 15])
            ->assertHasActionErrors(['delay_minutes']);
    }

    public function test_mark_delay_service_rejects_a_smaller_or_equal_total(): void
    {
        $live = $this->delayedSession(30);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('longer');

        $this->service->markDelay($live, 15);
    }

    public function test_start_after_sitting_time_asks_before_starting(): void
    {
        LiveSession::create([
            'schedule_session_id' => $this->session->id,
            'session_date' => $this->today,
            'status' => 'scheduled',
        ]);
        $this->waitingBooking(1);

        Carbon::setTestNow(Carbon::parse($this->today.' 17:20:00'));

        $this->queuePage()
            ->call('mountStartSessionOrRun')
            ->assertActionMounted('startSession');

        $this->assertSame('scheduled', LiveSession::first()->fresh()->status);
    }

    public function test_just_start_after_sitting_time_uses_now_not_sitting_start(): void
    {
        LiveSession::create([
            'schedule_session_id' => $this->session->id,
            'session_date' => $this->today,
            'status' => 'scheduled',
        ]);
        $booking = $this->waitingBooking(1);

        Carbon::setTestNow(Carbon::parse($this->today.' 17:20:00'));

        $this->queuePage()
            ->call('mountStartSessionOrRun')
            ->call('doStartSession');

        $live = LiveSession::first()->fresh();
        $this->assertSame('active', $live->status);

        $estimate = $this->service->estimatedTimeForBooking($booking->fresh());
        $expected = Carbon::parse($this->today.' 17:20:00');
        $this->assertTrue(
            $estimate['actual_estimate']->diffInMinutes($expected) <= 1,
            'Expected estimate from 5:20, got '.$estimate['actual_estimate']->toDateTimeString()
        );
    }

    public function test_start_before_sitting_time_starts_without_asking(): void
    {
        LiveSession::create([
            'schedule_session_id' => $this->session->id,
            'session_date' => $this->today,
            'status' => 'scheduled',
        ]);

        Carbon::setTestNow(Carbon::parse($this->today.' 16:50:00'));

        $this->queuePage()
            ->call('mountStartSessionOrRun')
            ->assertActionNotMounted('startSession');

        $this->assertSame('active', LiveSession::first()->fresh()->status);
    }

    public function test_start_inside_announced_delay_asks_before_starting(): void
    {
        $this->delayedSession(30);
        $this->waitingBooking(1);

        Carbon::setTestNow(Carbon::parse($this->today.' 17:20:00'));

        $this->queuePage()
            ->call('mountStartSessionOrRun')
            ->assertActionMounted('startSession');

        $this->assertSame('delayed', LiveSession::first()->fresh()->status);
    }

    public function test_mark_late_hidden_once_session_is_active(): void
    {
        LiveSession::create([
            'schedule_session_id' => $this->session->id,
            'session_date' => $this->today,
            'status' => 'active',
            'started_at' => now(),
        ]);

        $this->queuePage()->assertActionHidden('markLate');
    }

    public function test_overdue_prompt_silent_during_grace_period(): void
    {
        $this->waitingBooking(1);

        Carbon::setTestNow(Carbon::parse($this->today.' 17:05:00'));

        $prompts = app(SittingPrompt::class)->promptsForToday();
        $this->assertEmpty($prompts);
    }

    public function test_overdue_prompt_fires_after_grace_with_waiting_patients(): void
    {
        $this->waitingBooking(1);
        $this->waitingBooking(2);

        Carbon::setTestNow(Carbon::parse($this->today.' 17:18:00'));

        $prompts = app(SittingPrompt::class)->promptsForToday();
        $this->assertCount(1, $prompts);
        $this->assertSame('overdue', $prompts->first()['kind']);
        $this->assertSame(18, $prompts->first()['minutes_late']);
        $this->assertSame(2, $prompts->first()['waiting_count']);
    }

    public function test_overdue_prompt_silent_with_no_bookings(): void
    {
        Carbon::setTestNow(Carbon::parse($this->today.' 17:18:00'));

        $prompts = app(SittingPrompt::class)->promptsForToday();
        $this->assertEmpty($prompts);
    }

    public function test_overdue_prompt_silent_once_session_started(): void
    {
        $this->waitingBooking(1);

        LiveSession::create([
            'schedule_session_id' => $this->session->id,
            'session_date' => $this->today,
            'status' => 'active',
            'started_at' => Carbon::parse($this->today.' 17:05:00'),
        ]);

        Carbon::setTestNow(Carbon::parse($this->today.' 17:18:00'));

        $prompts = app(SittingPrompt::class)->promptsForToday();
        $this->assertCount(1, $prompts);
        $this->assertSame('idle_after_start', $prompts->first()['kind']);
    }

    public function test_in_delay_prompt_before_announced_time(): void
    {
        $this->delayedSession(30);
        $this->waitingBooking(1);

        Carbon::setTestNow(Carbon::parse($this->today.' 17:22:00'));

        $prompts = app(SittingPrompt::class)->promptsForToday();
        $this->assertCount(1, $prompts);
        $this->assertSame('in_delay', $prompts->first()['kind']);
        $this->assertSame(8, $prompts->first()['minutes_until_announced']);
    }

    public function test_delay_expired_prompt_after_announced_time(): void
    {
        $this->delayedSession(30);
        $this->waitingBooking(1);

        Carbon::setTestNow(Carbon::parse($this->today.' 17:40:00'));

        $prompts = app(SittingPrompt::class)->promptsForToday();
        $this->assertCount(1, $prompts);
        $this->assertSame('delay_expired', $prompts->first()['kind']);
        $this->assertSame(10, $prompts->first()['minutes_past_announced']);
    }

    public function test_roster_mark_late_on_delayed_session_extends_delay(): void
    {
        $this->delayedSession(30);
        $this->waitingBooking(1);

        $staff = User::create([
            'name' => 'Desk',
            'email' => 'desk@honest-late.test',
            'password' => Hash::make('secret'),
            'role' => User::ROLE_STAFF,
            'tenant_id' => $this->tenant->id,
        ]);

        Filament::setCurrentPanel('tenantAdmin');
        $this->actingAs($staff);

        Livewire::test(DailyRoster::class)
            ->callTableAction('markLate', data: [
                'schedule_session_id' => $this->session->id,
                'delay_minutes' => 60,
            ])
            ->assertHasNoTableActionErrors();

        $this->assertSame(60, (int) LiveSession::first()->fresh()->delay_minutes);
    }

    protected function delayedSession(int $minutes): LiveSession
    {
        return LiveSession::create([
            'schedule_session_id' => $this->session->id,
            'session_date' => $this->today,
            'status' => 'delayed',
            'delay_minutes' => $minutes,
        ]);
    }

    protected function waitingBooking(int $serial): Booking
    {
        return Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $this->session->id,
            'booking_date' => $this->today,
            'patient_name' => 'Patient '.$serial,
            'patient_phone' => '0171234567'.str_pad((string) $serial, 2, '0', STR_PAD_LEFT),
            'serial_number' => $serial,
            'status' => 'waiting',
        ]);
    }

    protected function queuePage(): \Livewire\Features\SupportTesting\Testable
    {
        $doctor = User::firstOrCreate(
            ['email' => 'doctor@honest-late.test'],
            [
                'name' => 'Doctor',
                'password' => Hash::make('secret'),
                'role' => User::ROLE_DOCTOR,
                'tenant_id' => $this->tenant->id,
            ],
        );

        Filament::setCurrentPanel('tenantAdmin');
        $this->actingAs($doctor);

        return Livewire::test(LiveQueueControl::class)
            ->set('selectedSessionId', $this->session->id);
    }
}
