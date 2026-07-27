<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Chamber;
use App\Models\Doctor;
use App\Models\Domain;
use App\Models\ScheduleSession;
use App\Models\SlotBlock;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingHappyPathTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private ScheduleSession $session;

    private string $monday;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'id' => 'happy-path',
            'name' => 'Dr. Rahman Chamber',
            'plan_tier' => 'solo',
            'slot_cap_mode' => 'per_session',
        ]);
        Domain::create(['domain' => 'happy-path.localhost', 'tenant_id' => 'happy-path']);

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

    public function test_fatima_books_and_gets_ticket_url(): void
    {
        $response = $this->postJson('http://happy-path.localhost/api/bookings', [
            'bookable_type' => 'session',
            'bookable_id' => $this->session->id,
            'booking_date' => $this->monday,
            'patient_name' => 'Fatima',
            'patient_phone' => '01712345678',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('booking.serial_number', 1);

        $ticketUrl = $response->json('booking.ticket_url');
        $this->assertNotEmpty($ticketUrl);

        $this->get($ticketUrl)
            ->assertOk()
            ->assertSee('Fatima', false)
            ->assertSee('Show this number at reception', false);
    }

    public function test_full_morning_rejects_third_patient(): void
    {
        foreach ([['Fatima', '01712345678'], ['Rahim', '01712345679']] as [$name, $phone]) {
            $this->postJson('http://happy-path.localhost/api/bookings', [
                'bookable_type' => 'session',
                'bookable_id' => $this->session->id,
                'booking_date' => $this->monday,
                'patient_name' => $name,
                'patient_phone' => $phone,
            ])->assertOk();
        }

        $this->postJson('http://happy-path.localhost/api/bookings', [
            'bookable_type' => 'session',
            'bookable_id' => $this->session->id,
            'booking_date' => $this->monday,
            'patient_name' => 'Late',
            'patient_phone' => '01712345680',
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'capacity');
    }

    public function test_blocked_holiday_rejects_booking(): void
    {
        tenancy()->initialize($this->tenant);
        SlotBlock::create([
            'date' => $this->monday,
            'reason' => 'Eid',
        ]);
        tenancy()->end();

        $this->postJson('http://happy-path.localhost/api/bookings', [
            'bookable_type' => 'session',
            'bookable_id' => $this->session->id,
            'booking_date' => $this->monday,
            'patient_name' => 'Fatima',
            'patient_phone' => '01712345678',
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'blocked');
    }

    public function test_book_page_deep_link_doctor_is_present_in_page(): void
    {
        $this->get('http://happy-path.localhost/book?doctor='.$this->session->doctor_id)
            ->assertOk()
            ->assertSee('doctorIds', false)
            ->assertSee((string) $this->session->doctor_id, false);
    }

    public function test_legacy_per_day_cap_mode_aliases_to_per_doctor_chamber(): void
    {
        tenancy()->initialize($this->tenant);
        try {
            $this->tenant->update(['slot_cap_mode' => 'per_day']);

            $evening = ScheduleSession::create([
                'chamber_id' => $this->session->chamber_id,
                'doctor_id' => $this->session->doctor_id,
                'day_of_week' => 1,
                'session_name' => 'Evening',
                'start_time' => '17:00',
                'end_time' => '21:00',
                'slot_cap' => 2,
            ]);

            // Cap 2 shared across morning+evening under per_doctor_chamber alias.
            app(\App\Services\BookingService::class)->createBookingForBookable(
                $this->session, $this->monday, 'A', '01711111111'
            );
            app(\App\Services\BookingService::class)->createBookingForBookable(
                $evening, $this->monday, 'B', '01711111112'
            );

            $this->expectException(\App\Exceptions\BookingUnavailableException::class);
            app(\App\Services\BookingService::class)->createBookingForBookable(
                $this->session, $this->monday, 'C', '01711111113'
            );
        } finally {
            tenancy()->end();
        }
    }
}
