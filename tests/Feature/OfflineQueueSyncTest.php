<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Chamber;
use App\Models\Doctor;
use App\Models\Domain;
use App\Models\LiveSession;
use App\Models\OfflineQueueEvent;
use App\Models\Patient;
use App\Models\ScheduleSession;
use App\Models\Tenant;
use App\Models\User;
use App\Services\OfflineSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OfflineQueueSyncTest extends TestCase
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
            'id' => 'offline-queue',
            'plan_tier' => 'clinic',
            'queue_runner' => 'doctor',
        ]);
        Domain::create(['domain' => 'offline-queue.localhost', 'tenant_id' => $this->tenant->id]);
        $this->host = 'http://offline-queue.localhost';

        tenancy()->initialize($this->tenant);

        $this->doctor = User::create([
            'name' => 'Dr Queue',
            'email' => 'doc@offline-queue.test',
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

    public function test_queue_runner_can_download_offline_queue_snapshot(): void
    {
        tenancy()->initialize($this->tenant);

        $patient = Patient::create(['name' => 'Rahim', 'phone' => '01710000011']);
        Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $this->session->id,
            'booking_date' => today()->toDateString(),
            'patient_id' => $patient->id,
            'patient_name' => $patient->name,
            'patient_phone' => $patient->phone,
            'serial_number' => 1,
            'status' => 'waiting',
        ]);

        $this->actingAs($this->doctor)
            ->getJson($this->host.'/api/offline/queue/'.$this->session->id)
            ->assertOk()
            ->assertJsonPath('live_session_id', $this->liveSession->id)
            ->assertJsonPath('screen.status', 'active')
            ->assertJsonStructure(['bookings', 'screen', 'packed_at']);

        tenancy()->end();
    }

    public function test_replayed_call_next_advances_the_live_queue(): void
    {
        tenancy()->initialize($this->tenant);

        $first = Patient::create(['name' => 'First', 'phone' => '01710000021']);
        $second = Patient::create(['name' => 'Second', 'phone' => '01710000022']);
        $b1 = Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $this->session->id,
            'booking_date' => today()->toDateString(),
            'patient_id' => $first->id,
            'patient_name' => $first->name,
            'patient_phone' => $first->phone,
            'serial_number' => 1,
            'status' => 'waiting',
        ]);
        $b2 = Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $this->session->id,
            'booking_date' => today()->toDateString(),
            'patient_id' => $second->id,
            'patient_name' => $second->name,
            'patient_phone' => $second->phone,
            'serial_number' => 2,
            'status' => 'waiting',
        ]);

        $syncId = '33333333-3333-4333-8333-333333333333';

        $this->actingAs($this->doctor)
            ->postJson($this->host.'/api/offline/sync', [
                'items' => [[
                    'id' => $syncId,
                    'type' => OfflineSyncService::TYPE_QUEUE_CALL_NEXT,
                    'live_session_id' => $this->liveSession->id,
                    'expected_current_booking_id' => null,
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('results.0.ok', true);

        $b1->refresh();
        $this->liveSession->refresh();

        $this->assertSame('called', $b1->status);
        $this->assertSame($b1->id, $this->liveSession->current_booking_id);
        $this->assertTrue(OfflineQueueEvent::query()->whereKey($syncId)->exists());

        $syncId2 = '44444444-4444-4444-8444-444444444444';

        $this->actingAs($this->doctor)
            ->postJson($this->host.'/api/offline/sync', [
                'items' => [[
                    'id' => $syncId2,
                    'type' => OfflineSyncService::TYPE_QUEUE_CALL_NEXT,
                    'live_session_id' => $this->liveSession->id,
                    'expected_current_booking_id' => $b1->id,
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('results.0.ok', true);

        $b2->refresh();
        $this->liveSession->refresh();
        $this->assertSame('called', $b2->status);
        $this->assertSame($b2->id, $this->liveSession->current_booking_id);

        tenancy()->end();
    }

    public function test_queue_conflict_halts_replay_when_the_line_moved_elsewhere(): void
    {
        tenancy()->initialize($this->tenant);

        $patient = Patient::create(['name' => 'Solo', 'phone' => '01710000031']);
        $booking = Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $this->session->id,
            'booking_date' => today()->toDateString(),
            'patient_id' => $patient->id,
            'patient_name' => $patient->name,
            'patient_phone' => $patient->phone,
            'serial_number' => 1,
            'status' => 'called',
            'called_at' => now(),
        ]);
        $this->liveSession->update(['current_booking_id' => $booking->id]);

        $syncId = '55555555-5555-4555-8555-555555555555';

        $this->actingAs($this->doctor)
            ->postJson($this->host.'/api/offline/sync', [
                'items' => [[
                    'id' => $syncId,
                    'type' => OfflineSyncService::TYPE_QUEUE_CALL_NEXT,
                    'live_session_id' => $this->liveSession->id,
                    'expected_current_booking_id' => null,
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('results.0.ok', false)
            ->assertJsonPath('results.0.conflict', true)
            ->assertJsonPath('results.0.halt', true);

        $this->assertFalse(OfflineQueueEvent::query()->whereKey($syncId)->exists());

        tenancy()->end();
    }

    public function test_screen_page_self_hosts_fonts_and_offline_chip(): void
    {
        tenancy()->initialize($this->tenant);

        $this->get($this->host.'/screen/'.$this->session->id)
            ->assertOk()
            ->assertSee('chamberq-screen-fonts.css', escape: false)
            ->assertSee('id="offlineChip"', escape: false)
            ->assertDontSee('fonts.googleapis.com', escape: false);

        tenancy()->end();
    }
}
