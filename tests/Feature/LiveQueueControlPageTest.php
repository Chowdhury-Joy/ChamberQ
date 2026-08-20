<?php

namespace Tests\Feature;

use App\Filament\TenantAdmin\Pages\LiveQueueControl;
use App\Jobs\SendDoctorLateNotices;
use App\Models\Booking;
use App\Models\Chamber;
use App\Models\Doctor;
use App\Models\LiveSession;
use App\Models\ScheduleSession;
use App\Models\Tenant;
use App\Models\User;
use App\Services\LiveSessionService;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Covers the Live Queue Control UX pass: queue ordering, out-of-turn calls,
 * and the summary figures staff read off the top of the page.
 */
class LiveQueueControlPageTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected ScheduleSession $session;

    protected LiveSession $liveSession;

    protected LiveSessionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['id' => 'lqc-page', 'plan_tier' => 'solo']);
        tenancy()->initialize($this->tenant);

        $chamber = Chamber::create(['name' => 'Main']);
        $doctor = Doctor::create(['name' => 'Dr Queue']);

        $this->session = ScheduleSession::create([
            'chamber_id' => $chamber->id,
            'doctor_id' => $doctor->id,
            'day_of_week' => Carbon::today()->dayOfWeek,
            'session_name' => 'Morning',
            'start_time' => '09:00',
            'end_time' => '12:00',
            'slot_cap' => 10,
        ]);

        $this->liveSession = LiveSession::create([
            'schedule_session_id' => $this->session->id,
            'session_date' => Carbon::today(),
            'status' => 'active',
            'started_at' => now(),
        ]);

        $this->service = app(LiveSessionService::class);
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    public function test_calling_a_patient_out_of_turn_returns_the_jumped_patient_to_waiting(): void
    {
        $first = $this->makeWaitingBooking('Patient One', 1);
        $third = $this->makeWaitingBooking('Patient Three', 3);
        $this->makeWaitingBooking('Patient Two', 2);

        $this->service->callNextPatient($this->liveSession);
        $this->assertSame('called', $first->fresh()->status);

        $called = $this->service->callSpecificPatient($this->liveSession, $third);

        $this->assertSame($third->id, $called?->id);
        $this->liveSession->refresh();
        $this->assertSame($third->id, $this->liveSession->current_booking_id);
        $this->assertSame('called', $third->fresh()->status);

        // Being jumped is not the patient's fault — no skip strike, keeps their place.
        $first->refresh();
        $this->assertSame('waiting', $first->status);
        $this->assertSame(0, (int) $first->skip_count);
        $this->assertNull($first->called_at);
    }

    public function test_out_of_turn_call_never_interrupts_a_consult_in_progress(): void
    {
        $first = $this->makeWaitingBooking('Patient One', 1);
        $second = $this->makeWaitingBooking('Patient Two', 2);

        $this->service->callNextPatient($this->liveSession);
        $this->service->patientArrived($this->liveSession);
        $this->assertSame('in_chamber', $first->fresh()->status);

        $this->assertNull($this->service->callSpecificPatient($this->liveSession, $second));

        $this->liveSession->refresh();
        $this->assertSame($first->id, $this->liveSession->current_booking_id);
        $this->assertSame('waiting', $second->fresh()->status);
    }

    public function test_calling_a_patient_clears_their_consumed_retry_position(): void
    {
        $first = $this->makeWaitingBooking('Patient One', 1);
        $this->makeWaitingBooking('Patient Two', 2);

        $this->service->callNextPatient($this->liveSession);
        $this->service->skipPatient($this->liveSession);

        $first->refresh();
        $this->assertSame('skipped', $first->status);
        $this->assertNotNull($first->retry_queue_position);

        $this->service->callSpecificPatient($this->liveSession, $first);

        $first->refresh();
        $this->assertSame('called', $first->status);
        $this->assertNull($first->retry_queue_position, 'A called patient must not keep a stale retry position.');
    }

    public function test_the_called_patient_is_listed_first_not_below_cancelled_ones(): void
    {
        $this->makeWaitingBooking('Cancelled One', 1)->update(['status' => 'cancelled']);
        $this->makeWaitingBooking('Completed One', 2)->update(['status' => 'completed']);
        $called = $this->makeWaitingBooking('Called One', 3);
        $this->makeWaitingBooking('Waiting One', 4);

        $this->service->callSpecificPatient($this->liveSession, $called);

        $records = $this->queuePage()->instance()->getTableRecords();

        $this->assertSame($called->id, $records->first()->id);
    }

    public function test_header_walk_in_adds_the_patient_to_the_live_queue(): void
    {
        $this->queuePage()
            ->mountAction('newWalkIn')
            ->fillForm([
                'patient_phone' => '01711112222',
                'patient_name' => 'Joy Walk-In',
                'visit_type' => 'usual',
                'share_clinical_history' => true,
                'remarks' => 'Came with mother',
            ])
            ->callMountedAction()
            ->assertHasNoErrors()
            ->assertSuccessful();

        $this->assertDatabaseHas('bookings', [
            'patient_name' => 'Joy Walk-In',
            'patient_phone' => '01711112222',
            'bookable_id' => $this->session->id,
            'status' => 'waiting',
            'remarks' => 'Came with mother',
        ]);
    }

    public function test_summary_counts_waiting_seen_and_measured_pace(): void
    {
        $this->makeWaitingBooking('Seen One', 1)->update([
            'status' => 'completed',
            'in_chamber_at' => now()->subMinutes(20),
            'completed_at' => now()->subMinutes(10),
        ]);
        $this->makeWaitingBooking('Waiting One', 2);
        $this->makeWaitingBooking('Waiting Two', 3);
        $this->makeWaitingBooking('Gone One', 4)->update(['status' => 'no_show']);

        $stats = $this->queuePage()->instance()->queueStats;

        $this->assertSame(2, $stats['waiting']);
        $this->assertSame(1, $stats['done']);
        $this->assertSame(1, $stats['no_show']);
        $this->assertSame(10, $stats['avg_minutes']);
        $this->assertTrue($stats['avg_is_observed']);
        $this->assertNotNull($stats['finishes_at']);
    }

    public function test_a_paused_session_shows_the_reason_and_when_it_resumes(): void
    {
        $this->makeWaitingBooking('Waiting One', 1);
        $this->service->callNextPatient($this->liveSession);
        $this->service->pauseSession($this->liveSession, 'Prayer break', 15);

        $this->queuePage()
            ->assertSee('Prayer break')
            ->assertSee($this->liveSession->fresh()->pauseEndsAt()->format('g:i a'))
            ->assertSee("He's back");
    }

    public function test_an_empty_queue_offers_no_call_button_to_press(): void
    {
        $this->makeWaitingBooking('Seen One', 1)->update(['status' => 'completed']);

        $this->queuePage()
            ->assertSee('No one waiting')
            ->assertDontSee('Call #');
    }

    public function test_the_only_session_of_the_day_is_selected_without_a_dropdown_step(): void
    {
        $this->liveSession->delete();

        $this->queuePage()->assertSet('selectedSessionId', $this->session->id);
    }

    /**
     * Mark Late and Cancel Session both resolve today's live session with
     * `firstOrCreate`, whose key array is the WHERE clause as well as the
     * insert payload. `session_date` is a date-only column (App\Casts\DateOnly),
     * so binding a Carbon there produces 'Y-m-d H:i:s', misses the row that
     * already exists, and turns the lookup into an INSERT that trips the
     * (tenant_id, schedule_session_id, session_date) unique index — a 500 on
     * the queue runner's screen mid-session.
     *
     * Both actions run here against an already-existing live session, which is
     * the only state in which the bug can fire.
     *
     * @dataProvider sessionLifecycleActions
     */
    public function test_session_lifecycle_actions_reuse_todays_live_session(
        string $action,
        array $data,
        string $startingStatus,
        string $expectedStatus,
    ): void {
        $this->makeWaitingBooking('Patient One', 1);

        // Each action is only offered in certain session states; put the
        // existing row into one where the action is reachable.
        $this->liveSession->update(['status' => $startingStatus]);

        $this->assertSame(1, LiveSession::count());

        $this->queuePage()->callAction($action, $data)->assertHasNoActionErrors();

        $this->assertSame(
            1,
            LiveSession::count(),
            "{$action} created a second live_session for today instead of reusing the existing row."
        );
        $this->assertSame($expectedStatus, $this->liveSession->fresh()->status);
    }

    /** @return array<string, array{0: string, 1: array<string, mixed>, 2: string, 3: string}> */
    public static function sessionLifecycleActions(): array
    {
        return [
            'mark late' => ['markLate', ['delay_minutes' => 30], 'scheduled', 'delayed'],
            'cancel session' => ['markAbsent', [], 'active', 'cancelled'],
        ];
    }

    /**
     * Marking late texts every waiting patient, and each send waits on the
     * gateway. Thirty people waiting must not mean a frozen queue screen, so
     * the sending is handed to `SendDoctorLateNotices` and runs once the
     * response is out.
     */
    public function test_mark_late_does_not_send_inside_the_request(): void
    {
        $doctor = Doctor::first();
        $doctor->update([
            'notify_channels' => array_replace_recursive(
                Doctor::defaultNotifyChannels(),
                [Doctor::NOTIFY_DOCTOR_LATE => ['sms' => true, 'whatsapp' => false]],
            ),
        ]);

        $this->makeWaitingBooking('Patient One', 1);
        $this->makeWaitingBooking('Patient Two', 2);
        $this->liveSession->update(['status' => 'scheduled']);

        Bus::fake();

        $this->queuePage()->callAction('markLate', ['delay_minutes' => 30])->assertHasNoActionErrors();

        Bus::assertDispatchedAfterResponse(
            SendDoctorLateNotices::class,
            fn (SendDoctorLateNotices $job) => count($job->bookingIds) === 2 && $job->delayMinutes === 30,
        );
    }

    /** Nothing to hand off when this doctor has late SMS switched off. */
    public function test_mark_late_dispatches_nothing_when_late_sms_is_off(): void
    {
        $this->makeWaitingBooking('Patient One', 1);
        $this->liveSession->update(['status' => 'scheduled']);

        Bus::fake();

        $this->queuePage()->callAction('markLate', ['delay_minutes' => 15])->assertHasNoActionErrors();

        Bus::assertNotDispatchedAfterResponse(SendDoctorLateNotices::class);
    }

    /**
     * Spending a credit per waiting patient should never be silent — End
     * Session already names what it is about to do.
     */
    public function test_mark_late_warns_what_it_will_cost(): void
    {
        Doctor::first()->update([
            'notify_channels' => array_replace_recursive(
                Doctor::defaultNotifyChannels(),
                [Doctor::NOTIFY_DOCTOR_LATE => ['sms' => true, 'whatsapp' => false]],
            ),
        ]);
        $this->tenant->update(['sms_balance' => 10]);

        $this->makeWaitingBooking('Patient One', 1);
        $this->makeWaitingBooking('Patient Two', 2);
        $this->liveSession->update(['status' => 'scheduled']);

        // Read off the mounted action, not the helper directly, so this fails
        // if the warning is ever unwired from the modal.
        $warning = $this->mountedMarkLateDescription();

        $this->assertStringContainsString('2 waiting patient', $warning);
        $this->assertStringContainsString('2 SMS credit', $warning);
        $this->assertStringContainsString('Balance after: 8', $warning);
    }

    /** Running out mid-blast is worth saying out loud, not discovering later. */
    public function test_mark_late_warns_when_the_wallet_cannot_cover_everyone(): void
    {
        Doctor::first()->update([
            'notify_channels' => array_replace_recursive(
                Doctor::defaultNotifyChannels(),
                [Doctor::NOTIFY_DOCTOR_LATE => ['sms' => true, 'whatsapp' => false]],
            ),
        ]);
        $this->tenant->update(['sms_balance' => 1]);

        $this->makeWaitingBooking('Patient One', 1);
        $this->makeWaitingBooking('Patient Two', 2);
        $this->makeWaitingBooking('Patient Three', 3);
        $this->liveSession->update(['status' => 'scheduled']);

        $warning = $this->mountedMarkLateDescription();

        $this->assertStringContainsString('Only 1 credit', $warning);
        $this->assertStringContainsString('last 2 patient', $warning);
    }

    /** Silent when the doctor has late SMS off — there is no cost to warn about. */
    public function test_mark_late_shows_no_cost_warning_when_sms_is_off(): void
    {
        $this->makeWaitingBooking('Patient One', 1);
        $this->liveSession->update(['status' => 'scheduled']);

        $this->assertSame('', $this->mountedMarkLateDescription());
    }

    public function test_pocket_buzz_card_is_readable_in_dark_mode(): void
    {
        $blade = file_get_contents(resource_path('views/filament/tenant-admin/components/staff-buzz-card.blade.php'));
        $this->assertStringNotContainsString(
            'background:rgb(250 250 250)',
            $blade,
            'Inline light fill wins over the theme and leaves Filament’s dark-mode type white-on-white.',
        );

        $css = file_get_contents(resource_path('css/filament/tenantAdmin/theme.css'));
        $this->assertMatchesRegularExpression(
            '/html\.dark\s+\.staff-buzz-card\s*\{[^}]*background:/',
            $css,
            'Dark mode must paint a dark card surface, not inherit page type onto a white box.',
        );
        $this->assertMatchesRegularExpression(
            '/html\.dark\s+\.staff-buzz-card\s*\{[^}]*color:/',
            $css,
        );

        $html = $this->queuePage()->assertSuccessful()->html();
        $this->assertStringContainsString('staff-buzz-card', $html);
        $this->assertStringContainsString('Buzz this phone when a sitting needs you', $html);
    }

    /** The cost warning as staff actually see it, resolved off the mounted action. */
    protected function mountedMarkLateDescription(): string
    {
        $action = $this->queuePage()->mountAction('markLate')->instance()->getMountedAction();

        return (string) $action?->getModalDescription();
    }

    protected function queuePage(): \Livewire\Features\SupportTesting\Testable
    {
        $doctor = User::firstOrCreate(
            ['email' => 'doctor@lqc-page.test'],
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
