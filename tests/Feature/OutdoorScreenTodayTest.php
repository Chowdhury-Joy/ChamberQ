<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Chamber;
use App\Models\Doctor;
use App\Models\Domain;
use App\Models\LiveSession;
use App\Models\ScheduleSession;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OutdoorScreenTodayTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private ScheduleSession $session;

    private string $today;

    private string $host;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'id' => 'tv-today',
            'name' => 'Dr. TV Today',
        ]);
        Domain::create(['domain' => 'tv-today.localhost', 'tenant_id' => 'tv-today']);
        $this->host = 'http://tv-today.localhost';

        tenancy()->initialize($this->tenant);

        $chamber = Chamber::create(['name' => 'Main']);
        $doctor = Doctor::create(['name' => 'Dr. Karim']);
        $this->session = ScheduleSession::create([
            'chamber_id' => $chamber->id,
            'doctor_id' => $doctor->id,
            'day_of_week' => Carbon::today()->dayOfWeek,
            'session_name' => 'Morning',
            'start_time' => '09:00',
            'end_time' => '12:00',
            'slot_cap' => 10,
        ]);

        $this->today = Carbon::today()->toDateString();

        tenancy()->end();
    }

    public function test_stable_screen_url_shows_todays_queue_without_date_in_path(): void
    {
        tenancy()->initialize($this->tenant);

        $live = LiveSession::create([
            'schedule_session_id' => $this->session->id,
            'session_date' => $this->today,
            'status' => 'active',
            'started_at' => now(),
        ]);

        $booking = Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $this->session->id,
            'booking_date' => $this->today,
            'patient_name' => 'Fatima',
            'patient_phone' => '01712345678',
            'serial_number' => 3,
            'status' => 'called',
            'called_at' => now(),
        ]);
        $live->update(['current_booking_id' => $booking->id]);

        tenancy()->end();

        $this->get($this->host.'/screen/'.$this->session->id)
            ->assertOk()
            ->assertSee('Morning', escape: false)
            ->assertSee('Main', escape: false);

        $this->getJson($this->host.'/api/screen/'.$this->session->id)
            ->assertOk()
            ->assertJsonPath('status', 'active')
            ->assertJsonPath('session_date', $this->today)
            ->assertJsonPath('now_serving', 3)
            ->assertJsonPath('now_serving_name', 'Fatima');
    }

    public function test_screen_api_includes_next_estimated_time_as_actual_minus_five_minutes(): void
    {
        tenancy()->initialize($this->tenant);

        // schedule_guess: 09:00–12:00 / 10 seats = 18 min each.
        // Serial 4 actual ETA = 09:00 + 3×18 = 09:54 → TV shows 09:49 (actual − 5).
        $this->tenant->update(['eta_model' => Tenant::ETA_SCHEDULE_GUESS]);

        $live = LiveSession::create([
            'schedule_session_id' => $this->session->id,
            'session_date' => $this->today,
            'status' => 'active',
            'started_at' => now(),
        ]);

        $current = Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $this->session->id,
            'booking_date' => $this->today,
            'patient_name' => 'Now',
            'patient_phone' => '01712345678',
            'serial_number' => 3,
            'status' => 'in_chamber',
            'called_at' => now(),
            'in_chamber_at' => now(),
        ]);
        $live->update(['current_booking_id' => $current->id]);

        Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $this->session->id,
            'booking_date' => $this->today,
            'patient_name' => 'Next Up',
            'patient_phone' => '01712345679',
            'serial_number' => 4,
            'status' => 'waiting',
        ]);

        tenancy()->end();

        $this->getJson($this->host.'/api/screen/'.$this->session->id)
            ->assertOk()
            ->assertJsonPath('next_booking', 4)
            ->assertJsonPath('next_estimated_time', '09:49 AM');
    }

    public function test_screen_api_omits_next_estimated_time_when_nobody_is_waiting(): void
    {
        tenancy()->initialize($this->tenant);

        $live = LiveSession::create([
            'schedule_session_id' => $this->session->id,
            'session_date' => $this->today,
            'status' => 'active',
            'started_at' => now(),
        ]);

        $current = Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $this->session->id,
            'booking_date' => $this->today,
            'patient_name' => 'Only',
            'patient_phone' => '01712345678',
            'serial_number' => 1,
            'status' => 'called',
            'called_at' => now(),
        ]);
        $live->update(['current_booking_id' => $current->id]);

        tenancy()->end();

        $this->getJson($this->host.'/api/screen/'.$this->session->id)
            ->assertOk()
            ->assertJsonPath('next_booking', null)
            ->assertJsonPath('next_estimated_time', null);
    }

    public function test_stable_screen_follows_calendar_day_not_yesterdays_live_session(): void
    {
        tenancy()->initialize($this->tenant);

        $yesterday = Carbon::yesterday()->toDateString();

        LiveSession::create([
            'schedule_session_id' => $this->session->id,
            'session_date' => $yesterday,
            'status' => 'completed',
            'started_at' => now()->subDay(),
            'ended_at' => now()->subDay()->addHours(3),
        ]);

        tenancy()->end();

        // No live session for today yet → "scheduled", not yesterday's completed queue.
        $this->getJson($this->host.'/api/screen/'.$this->session->id)
            ->assertOk()
            ->assertJsonPath('status', 'scheduled')
            ->assertJsonPath('session_date', $this->today);
    }

    public function test_dated_screen_url_still_works_for_old_bookmarks(): void
    {
        tenancy()->initialize($this->tenant);

        LiveSession::create([
            'schedule_session_id' => $this->session->id,
            'session_date' => $this->today,
            'status' => 'active',
            'started_at' => now(),
        ]);

        tenancy()->end();

        $this->get($this->host.'/screen/'.$this->session->id.'/'.$this->today)
            ->assertOk()
            ->assertSee('Morning', escape: false);

        $this->getJson($this->host.'/api/screen/'.$this->session->id.'/'.$this->today)
            ->assertOk()
            ->assertJsonPath('status', 'active')
            ->assertJsonPath('session_date', $this->today);
    }

    public function test_path_tenant_stable_screen_url(): void
    {
        // No Domain row — platform path tenancy only.
        $tenant = Tenant::create(['id' => 'path-tv', 'name' => 'Path TV']);
        tenancy()->initialize($tenant);

        $chamber = Chamber::create(['name' => 'Path Chamber']);
        $doctor = Doctor::create(['name' => 'Dr. Path']);
        $session = ScheduleSession::create([
            'chamber_id' => $chamber->id,
            'doctor_id' => $doctor->id,
            'day_of_week' => Carbon::today()->dayOfWeek,
            'session_name' => 'Evening',
            'start_time' => '17:00',
            'end_time' => '20:00',
            'slot_cap' => 5,
        ]);

        LiveSession::create([
            'schedule_session_id' => $session->id,
            'session_date' => $this->today,
            'status' => 'active',
            'started_at' => now(),
        ]);

        tenancy()->end();

        $this->get('http://localhost/path-tv/screen/'.$session->id)
            ->assertOk()
            ->assertSee('Evening', escape: false);

        $this->getJson('http://localhost/path-tv/api/screen/'.$session->id)
            ->assertOk()
            ->assertJsonPath('session_date', $this->today)
            ->assertJsonPath('status', 'active');
    }
}
