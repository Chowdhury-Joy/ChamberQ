<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Chamber;
use App\Models\Doctor;
use App\Models\LiveSession;
use App\Models\ScheduleSession;
use App\Models\Tenant;
use App\Services\LiveSessionService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The queue may only advance when the room is actually free.
 *
 * `callSpecificPatient()` and `bringBookingToChamber()` both already refuse to
 * step over a patient who has not finished; `callNextPatient()` does not, and
 * only the Blade template hides the button. A template is not a guard — the
 * Livewire action, the offline replay endpoint and any future API all reach
 * the service directly.
 */
class QueueAdvanceGuardTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private ScheduleSession $session;

    private LiveSession $liveSession;

    private LiveSessionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['id' => 'queue-advance-guard']);
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

    /**
     * The double-tap. The first tap feels slow because the approach pushes go
     * out `afterResponse()`, so staff press again — or a second member of staff
     * on the same chamber presses at the same moment.
     */
    public function test_repeated_call_next_does_not_step_over_the_patient_just_called(): void
    {
        $first = $this->makeWaitingBooking('Patient One', 1);
        $second = $this->makeWaitingBooking('Patient Two', 2);

        $this->service->callNextPatient($this->liveSession);
        $this->assertSame('called', $first->fresh()->status);

        // #1 has been called and has not arrived, been skipped, or completed.
        // The room is not free, so this must do nothing.
        $this->service->callNextPatient($this->liveSession);

        $this->liveSession->refresh();
        $this->assertSame(
            $first->id,
            $this->liveSession->current_booking_id,
            'A repeated Call next advanced past a patient who was still being called.',
        );
        $this->assertSame('called', $first->fresh()->status);
        $this->assertSame('waiting', $second->fresh()->status);
    }

    /**
     * The consequence, tested independently of how the state is reached: a
     * booking left in `called` that is no longer the current one is invisible
     * to every staff control. "Call now" takes only waiting/skipped, and the
     * roster's "Call to chamber" takes only waiting — so the one patient whose
     * phone has already buzzed is the one nobody can serve.
     */
    public function test_a_called_patient_who_is_no_longer_current_can_still_be_called_again(): void
    {
        $stranded = $this->makeWaitingBooking('Patient One', 1);
        $current = $this->makeWaitingBooking('Patient Two', 2);

        // The exact state a stepped-over patient is left in, built directly so
        // this test stands on its own if the advance guard changes.
        $stranded->update(['status' => 'called', 'called_at' => now()]);
        $current->update(['status' => 'called', 'called_at' => now()]);
        $this->liveSession->update([
            'current_booking_id' => $current->id,
            'current_called_at' => now(),
        ]);

        $recalled = $this->service->callSpecificPatient($this->liveSession, $stranded->fresh());

        $this->assertNotNull(
            $recalled,
            'A patient left in "called" but no longer current cannot be called again from the queue screen.',
        );
        $this->assertSame($stranded->id, $this->liveSession->fresh()->current_booking_id);
    }

    public function test_call_next_still_advances_once_the_room_is_free(): void
    {
        $first = $this->makeWaitingBooking('Patient One', 1);
        $second = $this->makeWaitingBooking('Patient Two', 2);

        $this->service->callNextPatient($this->liveSession);
        $this->service->completeCurrentPatientWithoutAdvancing($this->liveSession);

        // The consult is closed and the patient has left — this is the tap the
        // whole complete/call-next split exists to serve. It must still work.
        $this->service->callNextPatient($this->liveSession);

        $this->liveSession->refresh();
        $this->assertSame($second->id, $this->liveSession->current_booking_id);
        $this->assertSame('called', $second->fresh()->status);
        $this->assertSame('completed', $first->fresh()->status);
    }

    /**
     * `startSession()` runs `firstOrCreate()` before it takes any lock, so two
     * staff pressing Start together both reach the INSERT and the second trips
     * the unique index on (tenant_id, schedule_session_id, session_date).
     *
     * A real race needs two connections, which RefreshDatabase's wrapping
     * transaction makes impossible. Instead this injects a competing row at
     * exactly the vulnerable moment — between the SELECT that misses and the
     * INSERT that follows — which is the same sequence the loser of the race
     * executes.
     */
    public function test_start_session_survives_a_row_created_between_its_select_and_insert(): void
    {
        LiveSession::where('id', $this->liveSession->id)->delete();

        $today = Carbon::today()->toDateString();
        $injected = false;

        LiveSession::creating(function () use (&$injected, $today) {
            if ($injected) {
                return;
            }
            $injected = true;

            // The other request got there first and committed.
            DB::table('live_sessions')->insert([
                'tenant_id' => tenant('id'),
                'schedule_session_id' => $this->session->id,
                'session_date' => $today,
                'status' => 'active',
                'started_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        try {
            $live = $this->service->startSession($this->session);
        } catch (QueryException $e) {
            $this->fail(
                'Two staff pressing Start together produced an unhandled database error: '
                .$e->getMessage(),
            );
        }

        $this->assertSame('active', $live->status);
        $this->assertSame(
            1,
            LiveSession::where('schedule_session_id', $this->session->id)
                ->where('session_date', $today)
                ->count(),
            'Start session created a duplicate live session for the same day.',
        );
    }

    private function makeWaitingBooking(string $name, int $serial): Booking
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
