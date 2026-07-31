<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Chamber;
use App\Models\Doctor;
use App\Models\Domain;
use App\Models\LiveSession;
use App\Models\ScheduleSession;
use App\Models\Tenant;
use App\Services\LiveSessionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WaitingTimeEstimateTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private ScheduleSession $session;

    private LiveSession $liveSession;

    private string $today;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'id' => 'eta-test',
            'name' => 'Dr. ETA',
            'eta_model' => Tenant::ETA_LIVE_AVERAGE,
        ]);
        Domain::create(['domain' => 'eta-test.localhost', 'tenant_id' => 'eta-test']);

        tenancy()->initialize($this->tenant);

        $chamber = Chamber::create(['name' => 'Main']);
        $doctor = Doctor::create(['name' => 'Dr. Karim']);
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

        $this->liveSession = LiveSession::create([
            'schedule_session_id' => $this->session->id,
            'session_date' => $this->today,
            'status' => 'active',
            'started_at' => now(),
        ]);

        tenancy()->end();
    }

    public function test_schedule_session_screen_label_includes_name_and_times(): void
    {
        $this->assertStringContainsString('Evening', $this->session->screenLabel());
        $this->assertStringContainsString('5:00 PM', $this->session->screenLabel());
        $this->assertStringContainsString('8:00 PM', $this->session->screenLabel());
    }

    public function test_outdoor_screen_shows_session_label(): void
    {
        $this->get('http://eta-test.localhost/screen/'.$this->session->id.'/'.$this->today)
            ->assertOk()
            ->assertSee('Evening', escape: false)
            ->assertSee('5:00 PM', escape: false);
    }

    public function test_live_average_uses_completed_consult_pace_and_queue_position(): void
    {
        tenancy()->initialize($this->tenant);

        $this->seedCompletedConsult(1, 120);
        $this->seedCompletedConsult(2, 20);
        $this->seedCompletedConsult(3, 40);

        Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $this->session->id,
            'booking_date' => $this->today,
            'patient_name' => 'Waiting',
            'patient_phone' => '01711111114',
            'serial_number' => 4,
            'status' => 'waiting',
        ]);

        $target = Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $this->session->id,
            'booking_date' => $this->today,
            'patient_name' => 'Patient Four',
            'patient_phone' => '01711111115',
            'serial_number' => 5,
            'status' => 'waiting',
        ]);

        tenancy()->end();

        Carbon::setTestNow(Carbon::parse('2026-07-31 19:00:00'));
        tenancy()->initialize($this->tenant);

        $estimate = app(LiveSessionService::class)->estimatedTimeForBooking($target->fresh());
        tenancy()->end();

        $this->assertNotNull($estimate);

        // Average of 120, 20, 40 = 60 min. One person ahead (serial 4) → ~60 min from now.
        $expected = now()->addMinutes(60);
        $this->assertTrue(
            $estimate['actual_estimate']->diffInMinutes($expected) <= 1,
            'Expected ~60 minutes ahead, got '.$estimate['actual_estimate']->toDateTimeString()
        );

        Carbon::setTestNow();
    }

    public function test_live_steady_drops_longest_and_shortest(): void
    {
        $this->tenant->update(['eta_model' => Tenant::ETA_LIVE_STEADY]);
        tenancy()->initialize($this->tenant);

        $this->seedCompletedConsult(1, 120);
        $this->seedCompletedConsult(2, 20);
        $this->seedCompletedConsult(3, 40);

        Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $this->session->id,
            'booking_date' => $this->today,
            'patient_name' => 'Ahead',
            'patient_phone' => '01711111117',
            'serial_number' => 5,
            'status' => 'waiting',
        ]);

        $target = Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $this->session->id,
            'booking_date' => $this->today,
            'patient_name' => 'Next Up',
            'patient_phone' => '01711111116',
            'serial_number' => 6,
            'status' => 'waiting',
        ]);

        tenancy()->end();

        Carbon::setTestNow(Carbon::parse('2026-07-31 19:00:00'));
        tenancy()->initialize($this->tenant);

        $estimate = app(LiveSessionService::class)->estimatedTimeForBooking($target->fresh());
        tenancy()->end();

        $this->assertNotNull($estimate);
        $expected = now()->addMinutes(40);
        $this->assertTrue(
            $estimate['actual_estimate']->diffInMinutes($expected) <= 1,
            'Expected ~40 minutes, got '.$estimate['actual_estimate']->toDateTimeString()
        );

        Carbon::setTestNow();
    }

    private function seedCompletedConsult(int $serial, int $minutes): void
    {
        $completedAt = Carbon::parse('2026-07-31 19:00:00');

        Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $this->session->id,
            'booking_date' => $this->today,
            'patient_name' => 'Patient '.$serial,
            'patient_phone' => '0171111111'.str_pad((string) $serial, 2, '0', STR_PAD_LEFT),
            'serial_number' => $serial,
            'status' => 'completed',
            'in_chamber_at' => $completedAt->copy()->subMinutes($minutes),
            'completed_at' => $completedAt->copy(),
        ]);
    }
}
