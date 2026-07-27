<?php

namespace Tests\Feature;

use App\Exceptions\BookingUnavailableException;
use App\Models\Booking;
use App\Models\Chamber;
use App\Models\Doctor;
use App\Models\Domain;
use App\Models\ScheduleSession;
use App\Models\SlotBlock;
use App\Models\Tenant;
use App\Services\BookingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingPhaseATest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private ScheduleSession $session;

    private string $monday;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['id' => 'phase-a', 'slot_cap_mode' => 'per_session']);
        Domain::create(['domain' => 'phase-a.localhost', 'tenant_id' => 'phase-a']);

        tenancy()->initialize($this->tenant);

        $chamber = Chamber::create(['name' => 'Dhanmondi']);
        $doctor = Doctor::create(['name' => 'Dr. Rahman']);
        $this->session = ScheduleSession::create([
            'chamber_id' => $chamber->id,
            'doctor_id' => $doctor->id,
            'day_of_week' => 1,
            'session_name' => 'Morning',
            'start_time' => '09:00',
            'end_time' => '13:00',
            'slot_cap' => 2,
        ]);
        $this->monday = Carbon::now()->next(Carbon::MONDAY)->format('Y-m-d');

        tenancy()->end();
    }

    public function test_availability_shows_remaining_seats_for_fatimas_monday(): void
    {
        tenancy()->initialize($this->tenant);
        app(BookingService::class)->createBookingForBookable(
            $this->session,
            $this->monday,
            'Earlier Patient',
            '01711111111'
        );
        tenancy()->end();

        $this->getJson('http://phase-a.localhost/api/bookings/availability?' . http_build_query([
            'bookable_type' => 'session',
            'bookable_ids' => [$this->session->id],
            'booking_date' => $this->monday,
        ]))
            ->assertOk()
            ->assertJsonPath('items.'.$this->session->id.'.cap', 2)
            ->assertJsonPath('items.'.$this->session->id.'.booked', 1)
            ->assertJsonPath('items.'.$this->session->id.'.remaining', 1)
            ->assertJsonPath('items.'.$this->session->id.'.available', true)
            ->assertJsonPath('items.'.$this->session->id.'.blocked', false);
    }

    public function test_availability_marks_blocked_dates_closed(): void
    {
        tenancy()->initialize($this->tenant);
        SlotBlock::create([
            'date' => $this->monday,
            'reason' => 'Eid holiday',
        ]);
        tenancy()->end();

        $this->getJson('http://phase-a.localhost/api/bookings/availability?' . http_build_query([
            'bookable_type' => 'session',
            'bookable_ids' => [$this->session->id],
            'booking_date' => $this->monday,
        ]))
            ->assertOk()
            ->assertJsonPath('items.'.$this->session->id.'.blocked', true)
            ->assertJsonPath('items.'.$this->session->id.'.available', false);
    }

    public function test_last_seat_second_patient_gets_capacity_code(): void
    {
        // Fatima takes seat 1, Rahim takes seat 2; next patient loses like a race loser.
        $this->postJson('http://phase-a.localhost/api/bookings', [
            'bookable_type' => 'session',
            'bookable_id' => $this->session->id,
            'booking_date' => $this->monday,
            'patient_name' => 'Fatima',
            'patient_phone' => '01712345678',
        ])->assertOk();

        $this->postJson('http://phase-a.localhost/api/bookings', [
            'bookable_type' => 'session',
            'bookable_id' => $this->session->id,
            'booking_date' => $this->monday,
            'patient_name' => 'Rahim',
            'patient_phone' => '01712345679',
        ])->assertOk();

        $this->postJson('http://phase-a.localhost/api/bookings', [
            'bookable_type' => 'session',
            'bookable_id' => $this->session->id,
            'booking_date' => $this->monday,
            'patient_name' => 'Late Patient',
            'patient_phone' => '01712345680',
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'capacity')
            ->assertJsonFragment(['message' => 'This session just filled up. Please pick another session or date.']);
    }

    public function test_booking_stores_normalized_bd_phone(): void
    {
        $this->postJson('http://phase-a.localhost/api/bookings', [
            'bookable_type' => 'session',
            'bookable_id' => $this->session->id,
            'booking_date' => $this->monday,
            'patient_name' => 'Fatima',
            'patient_phone' => '+8801712345678',
        ])->assertOk();

        tenancy()->initialize($this->tenant);
        $this->assertSame('01712345678', Booking::first()->patient_phone);
        tenancy()->end();
    }

    public function test_missing_bookable_under_lock_is_polite_unavailable(): void
    {
        tenancy()->initialize($this->tenant);
        $ghost = new ScheduleSession([
            'id' => 99999,
            'chamber_id' => $this->session->chamber_id,
            'doctor_id' => $this->session->doctor_id,
            'day_of_week' => 1,
            'session_name' => 'Ghost',
            'start_time' => '09:00',
            'end_time' => '13:00',
            'slot_cap' => 2,
        ]);
        $ghost->id = 99999;
        $ghost->exists = false;

        $this->expectException(BookingUnavailableException::class);
        $this->expectExceptionMessage('no longer available');

        try {
            app(BookingService::class)->createBookingForBookable(
                $ghost,
                $this->monday,
                'Fatima',
                '01712345678'
            );
        } finally {
            tenancy()->end();
        }
    }
}
