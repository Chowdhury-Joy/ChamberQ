<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Chamber;
use App\Models\Doctor;
use App\Models\Domain;
use App\Models\ScheduleSession;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketPhaseBTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private ScheduleSession $session;

    private string $monday;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'id' => 'ticket-b',
            'name' => 'Dr. Rahman Chamber',
            'whatsapp_number' => '8801712345678',
        ]);
        Domain::create(['domain' => 'ticket-b.localhost', 'tenant_id' => 'ticket-b']);

        tenancy()->initialize($this->tenant);

        $chamber = Chamber::create([
            'name' => 'Dhanmondi',
            'address' => 'House 42, Road 9/A, Dhanmondi, Dhaka 1209',
            'latitude' => '23.7461',
            'longitude' => '90.3742',
        ]);
        $doctor = Doctor::create(['name' => 'Dr. Rahman']);
        $this->session = ScheduleSession::create([
            'chamber_id' => $chamber->id,
            'doctor_id' => $doctor->id,
            'day_of_week' => 1,
            'session_name' => 'Morning',
            'start_time' => '09:00',
            'end_time' => '13:00',
            'slot_cap' => 20,
        ]);
        $this->monday = Carbon::now()->next(Carbon::MONDAY)->format('Y-m-d');

        tenancy()->end();
    }

    public function test_ahead_count_ignores_skipped_serials_ahead_of_fatima(): void
    {
        tenancy()->initialize($this->tenant);

        // Serial 1 completed, 2 skipped (already called once), 3 waiting — Fatima is 4.
        Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $this->session->id,
            'booking_date' => $this->monday,
            'patient_name' => 'Done',
            'patient_phone' => '01711111111',
            'serial_number' => 1,
            'status' => 'completed',
        ]);
        Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $this->session->id,
            'booking_date' => $this->monday,
            'patient_name' => 'Skipped',
            'patient_phone' => '01711111112',
            'serial_number' => 2,
            'status' => 'skipped',
        ]);
        Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $this->session->id,
            'booking_date' => $this->monday,
            'patient_name' => 'Waiting',
            'patient_phone' => '01711111113',
            'serial_number' => 3,
            'status' => 'waiting',
        ]);
        $fatima = Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $this->session->id,
            'booking_date' => $this->monday,
            'patient_name' => 'Fatima',
            'patient_phone' => '01712345678',
            'serial_number' => 4,
            'status' => 'waiting',
        ]);

        tenancy()->end();

        $this->getJson('http://ticket-b.localhost/api/queue/'.$fatima->id)
            ->assertOk()
            ->assertJsonPath('ahead_of_you', 1)
            ->assertJsonPath('your_serial', 4);
    }

    public function test_ticket_page_shows_reception_handoff_and_whatsapp_share(): void
    {
        tenancy()->initialize($this->tenant);
        $fatima = Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $this->session->id,
            'booking_date' => $this->monday,
            'patient_name' => 'Fatima',
            'patient_phone' => '01712345678',
            'serial_number' => 7,
            'status' => 'waiting',
        ]);
        tenancy()->end();

        $this->get('http://ticket-b.localhost/bookings/'.$fatima->id)
            ->assertOk()
            ->assertSee('Show this number at reception', false)
            ->assertSee('Share on WhatsApp', false)
            ->assertSee('Copy link', false)
            ->assertSee('Open in Google Maps', false)
            ->assertSee('https://www.google.com/maps?q=', false)
            ->assertSee('wa.me/?text=', false)
            ->assertSee('Now serving', false)
            ->assertDontSee('people ahead of you:', false);
    }

    public function test_chamber_builds_google_maps_url_from_coordinates(): void
    {
        tenancy()->initialize($this->tenant);
        $chamber = Chamber::first();
        $this->assertSame(
            'https://www.google.com/maps?q=23.7461%2C90.3742',
            $chamber->googleMapsUrl()
        );
        tenancy()->end();
    }
}
