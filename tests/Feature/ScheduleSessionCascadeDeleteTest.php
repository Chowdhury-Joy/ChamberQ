<?php

namespace Tests\Feature;

use App\Models\Chamber;
use App\Models\Doctor;
use App\Models\Domain;
use App\Models\LiveSession;
use App\Models\OfflineQueueEvent;
use App\Models\ScheduleSession;
use App\Models\ScheduleSessionOverride;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ScheduleSessionCascadeDeleteTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private ScheduleSession $session;

    private LiveSession $liveSession;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'id' => 'cascade-test',
            'name' => 'Dr. Cascade Test',
        ]);
        Domain::create(['domain' => 'cascade-test.localhost', 'tenant_id' => 'cascade-test']);

        tenancy()->initialize($this->tenant);

        $chamber = Chamber::create(['name' => 'Main Room']);
        $doctor = Doctor::create(['name' => 'Dr. Test']);

        $this->session = ScheduleSession::create([
            'chamber_id' => $chamber->id,
            'doctor_id' => $doctor->id,
            'day_of_week' => Carbon::today()->dayOfWeek,
            'session_name' => 'Morning',
            'start_time' => '09:00',
            'end_time' => '13:00',
            'slot_cap' => 10,
        ]);

        $this->liveSession = LiveSession::create([
            'schedule_session_id' => $this->session->id,
            'session_date' => Carbon::today()->toDateString(),
            'status' => 'scheduled',
        ]);

        tenancy()->end();
    }

    public function test_schedule_session_has_live_sessions_relationship(): void
    {
        tenancy()->initialize($this->tenant);

        $this->assertTrue($this->session->liveSessions()->exists());
        $this->assertEquals($this->liveSession->id, $this->session->liveSessions->first()->id);

        tenancy()->end();
    }

    public function test_deleting_schedule_session_cascades_and_removes_live_sessions(): void
    {
        tenancy()->initialize($this->tenant);

        $override = ScheduleSessionOverride::create([
            'schedule_session_id' => $this->session->id,
            'override_date' => Carbon::tomorrow()->toDateString(),
            'start_time' => '10:00',
            'end_time' => '14:00',
            'slot_cap' => 8,
        ]);

        $offlineEvent = OfflineQueueEvent::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'live_session_id' => $this->liveSession->id,
            'event_type' => 'call_next',
            'applied_at' => now(),
        ]);

        $sessionId = $this->session->id;
        $liveSessionId = $this->liveSession->id;
        $overrideId = $override->id;
        $offlineEventId = $offlineEvent->id;

        $this->session->delete();

        $this->assertDatabaseMissing('schedule_sessions', ['id' => $sessionId]);
        $this->assertDatabaseMissing('live_sessions', ['id' => $liveSessionId]);
        $this->assertDatabaseMissing('schedule_session_overrides', ['id' => $overrideId]);
        $this->assertDatabaseMissing('offline_queue_events', ['id' => $offlineEventId]);

        tenancy()->end();
    }
}
