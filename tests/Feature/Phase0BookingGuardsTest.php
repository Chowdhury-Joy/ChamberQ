<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Chamber;
use App\Models\Doctor;
use App\Models\Domain;
use App\Models\ScheduleSession;
use App\Models\Tenant;
use App\Services\BookingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase0BookingGuardsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private ScheduleSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['id' => 'phase0']);
        Domain::create(['domain' => 'phase0.localhost', 'tenant_id' => 'phase0']);

        tenancy()->initialize($this->tenant);

        $chamber = Chamber::create(['name' => 'Main']);
        $doctor = Doctor::create(['name' => 'Dr. Demo']);
        $this->session = ScheduleSession::create([
            'chamber_id' => $chamber->id,
            'doctor_id' => $doctor->id,
            'day_of_week' => 1,
            'session_name' => 'Morning',
            'start_time' => '09:00',
            'end_time' => '12:00',
            'slot_cap' => 10,
        ]);

        tenancy()->end();
    }

    public function test_booking_rejects_a_past_date(): void
    {
        $pastMonday = Carbon::now()->previous(Carbon::MONDAY)->subWeek()->format('Y-m-d');

        $this->postJson('http://phase0.localhost/api/bookings', [
            'bookable_type' => 'session',
            'bookable_id' => $this->session->id,
            'booking_date' => $pastMonday,
            'patient_name' => 'Past Patient',
            'patient_phone' => '01712345678',
        ])->assertStatus(422)->assertJsonValidationErrors('booking_date');
    }

    public function test_booking_rejects_a_date_more_than_60_days_out(): void
    {
        $far = Carbon::now()->addDays(90);
        while ($far->dayOfWeek !== 1) {
            $far->addDay();
        }

        $this->postJson('http://phase0.localhost/api/bookings', [
            'bookable_type' => 'session',
            'bookable_id' => $this->session->id,
            'booking_date' => $far->format('Y-m-d'),
            'patient_name' => 'Far Patient',
            'patient_phone' => '01712345678',
        ])->assertStatus(422)->assertJsonValidationErrors('booking_date');
    }

    public function test_portal_requires_an_exact_phone_match(): void
    {
        tenancy()->initialize($this->tenant);
        app(BookingService::class)->createBookingForBookable(
            $this->session,
            Carbon::now()->next(1)->format('Y-m-d'),
            'Exact Match',
            '01712345678'
        );
        tenancy()->end();

        // Partial substring must not leak other patients.
        $this->get('http://phase0.localhost/portal?phone=1')
            ->assertOk()
            ->assertSee('Please enter a valid Bangladeshi mobile number', false)
            ->assertDontSee('Exact Match');

        $this->get('http://phase0.localhost/portal?phone=01712345678')
            ->assertOk()
            ->assertSee('Exact Match');
    }

    public function test_portal_matches_common_phone_variants(): void
    {
        tenancy()->initialize($this->tenant);
        app(BookingService::class)->createBookingForBookable(
            $this->session,
            Carbon::now()->next(1)->format('Y-m-d'),
            'Variant Patient',
            '01799887766'
        );
        tenancy()->end();

        $this->get('http://phase0.localhost/portal?phone=%2B8801799887766')
            ->assertOk()
            ->assertSee('Variant Patient');
    }

    public function test_booking_rejects_a_session_that_already_ended_today(): void
    {
        // Fixed mid-day "now" — a session ending an hour ago cannot wrap past
        // midnight and flip past/future depending on when the suite happens to run.
        Carbon::setTestNow(Carbon::parse('2026-07-28 11:30:00'));

        tenancy()->initialize($this->tenant);
        $chamber = Chamber::first();
        $doctor = Doctor::first();
        $endedSession = ScheduleSession::create([
            'chamber_id' => $chamber->id,
            'doctor_id' => $doctor->id,
            'day_of_week' => Carbon::now()->dayOfWeek,
            'session_name' => 'Already finished',
            'start_time' => '08:00',
            'end_time' => '10:30',
            'slot_cap' => 10,
        ]);
        tenancy()->end();

        $this->postJson('http://phase0.localhost/api/bookings', [
            'bookable_type' => 'session',
            'bookable_id' => $endedSession->id,
            'booking_date' => Carbon::now()->toDateString(),
            'patient_name' => 'Too Late Patient',
            'patient_phone' => '01712345678',
        ])->assertStatus(422)->assertJson(['success' => false, 'code' => 'blocked']);

        $this->assertDatabaseMissing('bookings', ['patient_name' => 'Too Late Patient']);

        Carbon::setTestNow();
    }

    public function test_booking_allows_a_session_still_running_today(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-28 11:30:00'));

        tenancy()->initialize($this->tenant);
        $chamber = Chamber::first();
        $doctor = Doctor::first();
        $runningSession = ScheduleSession::create([
            'chamber_id' => $chamber->id,
            'doctor_id' => $doctor->id,
            'day_of_week' => Carbon::now()->dayOfWeek,
            'session_name' => 'Still open',
            'start_time' => '10:00',
            'end_time' => '13:00',
            'slot_cap' => 10,
        ]);
        tenancy()->end();

        $this->postJson('http://phase0.localhost/api/bookings', [
            'bookable_type' => 'session',
            'bookable_id' => $runningSession->id,
            'booking_date' => Carbon::now()->toDateString(),
            'patient_name' => 'On Time Patient',
            'patient_phone' => '01712345678',
        ])->assertOk()->assertJson(['success' => true]);

        Carbon::setTestNow();
    }
}
