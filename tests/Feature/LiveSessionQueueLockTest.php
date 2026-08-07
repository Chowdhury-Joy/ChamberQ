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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LiveSessionQueueLockTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private ScheduleSession $session;

    private LiveSession $liveSession;

    private LiveSessionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['id' => 'queue-lock']);
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

    public function test_call_next_sets_a_single_current_booking(): void
    {
        $first = $this->makeWaitingBooking('Patient One', 1);
        $this->makeWaitingBooking('Patient Two', 2);

        $called = $this->service->callNextPatient($this->liveSession);

        $this->assertNotNull($called);
        $this->assertSame($first->id, $called->id);
        $this->liveSession->refresh();
        $this->assertSame($first->id, $this->liveSession->current_booking_id);
        $this->assertSame('called', $first->fresh()->status);
    }

    public function test_complete_current_advances_without_losing_queue_state(): void
    {
        $first = $this->makeWaitingBooking('Patient One', 1);
        $second = $this->makeWaitingBooking('Patient Two', 2);

        $this->service->callNextPatient($this->liveSession);
        $this->service->completeCurrentPatient($this->liveSession);

        $this->liveSession->refresh();
        $this->assertSame($second->id, $this->liveSession->current_booking_id);
        $this->assertSame('completed', $first->fresh()->status);
        $this->assertSame('called', $second->fresh()->status);
    }

    public function test_complete_without_advancing_holds_the_room_until_call_next(): void
    {
        $first = $this->makeWaitingBooking('Patient One', 1);
        $second = $this->makeWaitingBooking('Patient Two', 2);

        $this->service->callNextPatient($this->liveSession);
        $this->service->completeCurrentPatientWithoutAdvancing($this->liveSession);

        // The consult is closed, but the doctor still has the room: the queue
        // must not have moved on while the prescription is being handed over.
        $this->liveSession->refresh();
        $this->assertSame($first->id, $this->liveSession->current_booking_id);
        $this->assertSame('completed', $first->fresh()->status);
        $this->assertNotNull($first->fresh()->completed_at);
        $this->assertSame('waiting', $second->fresh()->status);

        $this->service->callNextPatient($this->liveSession);

        $this->liveSession->refresh();
        $this->assertSame($second->id, $this->liveSession->current_booking_id);
        $this->assertSame('called', $second->fresh()->status);
    }

    public function test_concurrent_call_next_serializes_on_live_session_row_lock(): void
    {
        $first = $this->makeWaitingBooking('Patient One', 1);
        $this->makeWaitingBooking('Patient Two', 2);

        $outerResult = null;

        DB::transaction(function () use (&$outerResult, $first) {
            LiveSession::where('id', $this->liveSession->id)->lockForUpdate()->first();

            $outerResult = $this->service->callNextPatient($this->liveSession);

            $this->liveSession->refresh();
            $this->assertSame($first->id, $this->liveSession->current_booking_id);
        });

        $this->assertSame($first->id, $outerResult?->id);
        $this->liveSession->refresh();
        $this->assertSame($first->id, $this->liveSession->current_booking_id);
    }

    /**
     * The roster's "Call to Chamber" must obey the same rule as the queue's
     * "Call now": a consult already in progress is never interrupted.
     */
    public function test_roster_cannot_pull_a_patient_in_while_someone_is_mid_consult(): void
    {
        $inChamber = $this->makeWaitingBooking('Patient One', 1);
        $waiting = $this->makeWaitingBooking('Patient Two', 2);

        $this->service->callNextPatient($this->liveSession);
        $this->service->patientArrived($this->liveSession);

        $this->assertSame('in_chamber', $inChamber->fresh()->status);

        $moved = $this->service->bringBookingToChamber($waiting);

        $this->assertFalse($moved, 'Roster took the doctor off a patient mid-consult.');
        $this->liveSession->refresh();
        $this->assertSame($inChamber->id, $this->liveSession->current_booking_id);
        $this->assertSame('in_chamber', $inChamber->fresh()->status);
        $this->assertSame('waiting', $waiting->fresh()->status);
    }

    public function test_roster_may_pull_a_patient_in_when_the_room_is_free(): void
    {
        $called = $this->makeWaitingBooking('Patient One', 1);
        $waiting = $this->makeWaitingBooking('Patient Two', 2);

        // Called but not yet arrived — the room is free, so this is allowed and
        // the jumped patient keeps their place with no skip strike.
        $this->service->callNextPatient($this->liveSession);
        $this->assertSame('called', $called->fresh()->status);

        $moved = $this->service->bringBookingToChamber($waiting);

        $this->assertTrue($moved);
        $this->liveSession->refresh();
        $this->assertSame($waiting->id, $this->liveSession->current_booking_id);
        $this->assertSame('in_chamber', $waiting->fresh()->status);
        $this->assertSame('waiting', $called->fresh()->status);
        $this->assertSame(0, $called->fresh()->skip_count);
    }

    public function test_resume_recovers_a_paused_session_with_no_paused_at(): void
    {
        // A paused row missing its timestamp used to make Resume a no-op, which
        // left the queue permanently stuck with no way back from the UI.
        $this->liveSession->update([
            'status' => 'paused',
            'paused_at' => null,
            'pause_reason' => 'Prayer break',
        ]);

        $this->service->resumeSession($this->liveSession);

        $this->liveSession->refresh();
        $this->assertSame('active', $this->liveSession->status);
        $this->assertNull($this->liveSession->pause_reason);
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
