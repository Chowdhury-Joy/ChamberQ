<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Chamber;
use App\Models\Doctor;
use App\Models\Domain;
use App\Models\Patient;
use App\Models\PatientAccount;
use App\Models\ScheduleSession;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformPatientSerialsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        tenancy()->end();
        parent::tearDown();
    }

    public function test_my_serials_shows_this_phone_across_clinics_and_hides_other_phones(): void
    {
        $skin = $this->clinic('skin-a', 'Skin A');
        $heart = $this->clinic('heart-a', 'Heart A');

        $this->waitingBooking($skin, 'Fatima', '01720000001', 3);
        $this->waitingBooking($heart, 'Fatima', '01720000001', 7);
        $this->waitingBooking($skin, 'Stranger', '01720000099', 1);

        $account = PatientAccount::create([
            'phone' => '01720000001',
            'name' => 'Fatima',
        ]);

        $this->actingAs($account, 'patient')
            ->get('http://localhost/me')
            ->assertOk()
            ->assertSee('Fatima', escape: false)
            ->assertSee('3', escape: false)
            ->assertSee('7', escape: false)
            ->assertDontSee('Stranger', escape: false);
    }

    public function test_logged_in_patient_prefills_the_tenant_book_wizard(): void
    {
        $tenant = $this->clinic('prefill-doc', 'Prefill Clinic');
        Domain::create(['domain' => 'prefill-doc.localhost', 'tenant_id' => $tenant->id]);

        $account = PatientAccount::create([
            'phone' => '01720000002',
            'name' => 'Rahim Uddin',
        ]);

        $this->actingAs($account, 'patient')
            ->get('http://localhost/prefill-doc/book')
            ->assertOk()
            ->assertSee('"phone":"01720000002"', escape: false)
            ->assertSee('"name":"Rahim Uddin"', escape: false);
    }

    private function clinic(string $id, string $name): Tenant
    {
        $tenant = Tenant::create([
            'id' => $id,
            'name' => $name,
            'plan_tier' => 'solo',
            'billing_status' => 'active',
        ]);

        tenancy()->initialize($tenant);
        $chamber = Chamber::create(['name' => 'Main']);
        $doctor = Doctor::create(['name' => 'Dr '.$name]);
        ScheduleSession::create([
            'chamber_id' => $chamber->id,
            'doctor_id' => $doctor->id,
            'day_of_week' => Carbon::today()->dayOfWeek,
            'session_name' => 'Morning',
            'start_time' => '09:00',
            'end_time' => '12:00',
            'slot_cap' => 20,
        ]);
        tenancy()->end();

        return $tenant;
    }

    private function waitingBooking(Tenant $tenant, string $name, string $phone, int $serial): Booking
    {
        tenancy()->initialize($tenant);
        $session = ScheduleSession::query()->firstOrFail();
        $patient = Patient::create(['name' => $name, 'phone' => $phone]);
        $booking = Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $session->id,
            'booking_date' => today(),
            'patient_id' => $patient->id,
            'patient_name' => $name,
            'patient_phone' => $phone,
            'serial_number' => $serial,
            'status' => 'waiting',
        ]);
        tenancy()->end();

        return $booking;
    }
}
