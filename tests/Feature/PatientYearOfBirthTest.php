<?php

namespace Tests\Feature;

use App\Models\Chamber;
use App\Models\Doctor;
use App\Models\Domain;
use App\Models\Patient;
use App\Models\ScheduleSession;
use App\Models\Tenant;
use App\Services\PatientService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientYearOfBirthTest extends TestCase
{
    use RefreshDatabase;

    private const FROZEN_NOW = '2026-08-18 10:00';

    private Tenant $tenant;

    private ScheduleSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse(self::FROZEN_NOW));

        $this->tenant = Tenant::create([
            'id' => 'yob-chamber',
            'name' => 'YoB Chamber',
            'plan_tier' => 'solo',
        ]);
        Domain::create(['domain' => 'yob-chamber.localhost', 'tenant_id' => 'yob-chamber']);

        tenancy()->initialize($this->tenant);
        $chamber = Chamber::create(['name' => 'Main']);
        $doctor = Doctor::create(['name' => 'Dr YoB']);
        $this->session = ScheduleSession::create([
            'chamber_id' => $chamber->id,
            'doctor_id' => $doctor->id,
            'day_of_week' => Carbon::today()->dayOfWeek,
            'session_name' => 'Morning',
            'start_time' => '09:00',
            'end_time' => '13:00',
            'slot_cap' => 10,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        tenancy()->end();
        parent::tearDown();
    }

    public function test_year_of_birth_does_not_tick_when_the_calendar_year_changes(): void
    {
        $patient = Patient::create([
            'name' => 'Fatima Rahman',
            'phone' => '01712345999',
            'year_of_birth' => 1984,
        ]);

        $this->assertSame(1984, $patient->year_of_birth);
        $this->assertSame(42, $patient->displayAge());

        Carbon::setTestNow(Carbon::parse('2027-08-18 10:00'));
        $patient->refresh();

        $this->assertSame(1984, $patient->year_of_birth);
        $this->assertSame(43, $patient->displayAge());
    }

    public function test_booking_stores_year_of_birth_not_a_ticking_age(): void
    {
        $this->postJson('http://yob-chamber.localhost/api/bookings', [
            'bookable_type' => 'session',
            'bookable_id' => $this->session->id,
            'booking_date' => Carbon::today()->toDateString(),
            'patient_name' => 'Karim Hossain',
            'patient_phone' => '01712345888',
            'year_of_birth' => 1990,
        ])->assertOk()->assertJsonPath('success', true);

        $patient = Patient::query()->where('phone', '01712345888')->first();
        $this->assertNotNull($patient);
        $this->assertSame(1990, $patient->year_of_birth);
        $this->assertNull($patient->age);
        $this->assertSame(36, $patient->displayAge());
    }

    public function test_legacy_age_post_is_converted_to_year_of_birth(): void
    {
        $this->postJson('http://yob-chamber.localhost/api/bookings', [
            'bookable_type' => 'session',
            'bookable_id' => $this->session->id,
            'booking_date' => Carbon::today()->toDateString(),
            'patient_name' => 'Legacy Age',
            'patient_phone' => '01712345777',
            'age' => 40,
        ])->assertOk();

        $patient = Patient::query()->where('phone', '01712345777')->first();
        $this->assertSame(1986, $patient?->year_of_birth);
        $this->assertSame(40, $patient?->displayAge());
    }

    public function test_booking_does_not_overwrite_a_year_already_on_file(): void
    {
        $existing = Patient::create([
            'name' => 'On File',
            'phone' => '01712345666',
            'year_of_birth' => 1975,
        ]);

        app(PatientService::class)->resolveForBooking(
            '01712345666',
            'On File',
            $existing->id,
            yearOfBirth: 1980,
        );

        $this->assertSame(1975, $existing->fresh()?->year_of_birth);
    }

    public function test_book_page_asks_for_year_of_birth(): void
    {
        $this->get('http://yob-chamber.localhost/book')
            ->assertOk()
            ->assertSee('Year of birth (optional)', false)
            ->assertDontSee('Age in years (optional)', false);
    }
}
