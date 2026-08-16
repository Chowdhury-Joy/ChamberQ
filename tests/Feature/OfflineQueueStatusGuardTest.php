<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Chamber;
use App\Models\Doctor;
use App\Models\Domain;
use App\Models\LiveSession;
use App\Models\Patient;
use App\Models\ScheduleSession;
use App\Models\Tenant;
use App\Models\User;
use App\Services\OfflineSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Offline replay checks *which* booking is current, never *what state* it is in.
 *
 * `completeCurrentPatientWithoutAdvancing()` deliberately leaves
 * `current_booking_id` pointing at the finished booking until the runner calls
 * the next patient. So a stale offline snapshot — stale by definition, the line
 * was down — passes the id check and replays an event against a consult that
 * has already ended, or one that is happening right now.
 */
class OfflineQueueStatusGuardTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $doctor;

    private ScheduleSession $session;

    private LiveSession $liveSession;

    private string $host;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'id' => 'offline-status-guard',
            'plan_tier' => 'clinic',
            'queue_runner' => 'doctor',
        ]);
        Domain::create(['domain' => 'offline-status-guard.localhost', 'tenant_id' => $this->tenant->id]);
        $this->host = 'http://offline-status-guard.localhost';

        tenancy()->initialize($this->tenant);

        $this->doctor = User::create([
            'name' => 'Dr Queue',
            'email' => 'doc@offline-status-guard.test',
            'password' => Hash::make('secret'),
            'role' => User::ROLE_DOCTOR,
            'tenant_id' => $this->tenant->id,
        ]);

        $chamber = Chamber::create(['name' => 'Main']);
        $doctorProfile = Doctor::create([
            'name' => 'Dr Queue',
            'user_id' => $this->doctor->id,
        ]);
        $this->session = ScheduleSession::create([
            'chamber_id' => $chamber->id,
            'doctor_id' => $doctorProfile->id,
            'day_of_week' => 0,
            'session_name' => 'Morning',
            'start_time' => '09:00',
            'end_time' => '12:00',
            'slot_cap' => 10,
        ]);

        $this->liveSession = LiveSession::create([
            'schedule_session_id' => $this->session->id,
            'session_date' => today()->toDateString(),
            'status' => 'active',
            'started_at' => now(),
        ]);

        tenancy()->end();
    }

    /**
     * The bag was packed while #1 was merely `called`. The consult screen then
     * completed #1 without advancing. The line drops. The runner taps "Patient
     * arrived" against their stale snapshot — and reopens a finished visit,
     * which blocks the next call and puts #1 back on the outdoor TV.
     */
    public function test_replayed_patient_arrived_cannot_reopen_a_completed_visit(): void
    {
        tenancy()->initialize($this->tenant);

        $booking = $this->makeBooking('Rahim', '01710000041', 1, 'completed');
        $booking->update(['completed_at' => now(), 'called_at' => now()->subMinutes(10)]);
        $this->liveSession->update(['current_booking_id' => $booking->id]);

        $response = $this->actingAs($this->doctor)
            ->postJson($this->host.'/api/offline/sync', [
                'items' => [[
                    'id' => '66666666-6666-4666-8666-666666666666',
                    'type' => OfflineSyncService::TYPE_QUEUE_PATIENT_ARRIVED,
                    'live_session_id' => $this->liveSession->id,
                    // The snapshot is stale, but it names the right booking.
                    'expected_current_booking_id' => $booking->id,
                ]],
            ])->assertOk();

        $this->assertSame(
            'completed',
            $booking->fresh()->status,
            'Offline replay reopened a visit the doctor had already finished.',
        );
        $this->assertFalse(
            (bool) $response->json('results.0.ok'),
            'Offline replay accepted an event against an already-completed booking.',
        );

        tenancy()->end();
    }

    /**
     * Same stale snapshot, the skip button instead: the patient the doctor is
     * examining right now gets a skip strike and is pushed down the queue.
     */
    public function test_replayed_skip_cannot_strike_a_patient_mid_consult(): void
    {
        tenancy()->initialize($this->tenant);

        $booking = $this->makeBooking('Karim', '01710000042', 1, 'in_chamber');
        $booking->update(['in_chamber_at' => now(), 'called_at' => now()->subMinutes(5)]);
        $this->liveSession->update(['current_booking_id' => $booking->id]);

        $response = $this->actingAs($this->doctor)
            ->postJson($this->host.'/api/offline/sync', [
                'items' => [[
                    'id' => '77777777-7777-4777-8777-777777777777',
                    'type' => OfflineSyncService::TYPE_QUEUE_SKIP,
                    'live_session_id' => $this->liveSession->id,
                    'expected_current_booking_id' => $booking->id,
                ]],
            ])->assertOk();

        $fresh = $booking->fresh();

        $this->assertSame(
            'in_chamber',
            $fresh->status,
            'Offline replay skipped a patient who was with the doctor.',
        );
        $this->assertSame(0, (int) $fresh->skip_count);
        $this->assertFalse(
            (bool) $response->json('results.0.ok'),
            'Offline replay accepted a skip against a patient mid-consult.',
        );

        tenancy()->end();
    }

    /**
     * The guard must not break the case offline replay exists for: the runner
     * genuinely tapped "arrived" on a patient who was genuinely called.
     */
    public function test_replayed_patient_arrived_still_works_on_a_called_patient(): void
    {
        tenancy()->initialize($this->tenant);

        $booking = $this->makeBooking('Fatima', '01710000043', 1, 'called');
        $booking->update(['called_at' => now()]);
        $this->liveSession->update(['current_booking_id' => $booking->id]);

        $this->actingAs($this->doctor)
            ->postJson($this->host.'/api/offline/sync', [
                'items' => [[
                    'id' => '88888888-8888-4888-8888-888888888888',
                    'type' => OfflineSyncService::TYPE_QUEUE_PATIENT_ARRIVED,
                    'live_session_id' => $this->liveSession->id,
                    'expected_current_booking_id' => $booking->id,
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('results.0.ok', true);

        $this->assertSame('in_chamber', $booking->fresh()->status);

        tenancy()->end();
    }

    private function makeBooking(string $name, string $phone, int $serial, string $status): Booking
    {
        $patient = Patient::create(['name' => $name, 'phone' => $phone]);

        return Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $this->session->id,
            'booking_date' => today()->toDateString(),
            'patient_id' => $patient->id,
            'patient_name' => $patient->name,
            'patient_phone' => $phone,
            'serial_number' => $serial,
            'status' => $status,
        ]);
    }
}
