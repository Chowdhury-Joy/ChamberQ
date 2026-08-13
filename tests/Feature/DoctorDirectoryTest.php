<?php

namespace Tests\Feature;

use App\Models\Chamber;
use App\Models\Doctor;
use App\Models\ScheduleSession;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DoctorDirectoryTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        tenancy()->end();
        parent::tearDown();
    }

    public function test_find_lists_front_door_doctors_and_hides_closed_or_unbookable_clinics(): void
    {
        $listed = Tenant::create([
            'id' => 'drkarim',
            'name' => 'Karim Skin',
            'plan_tier' => 'solo',
            'billing_status' => 'active',
        ]);
        $noFrontDoor = Tenant::create([
            'id' => 'hiddenrx',
            'name' => 'Hidden Rx Only',
            'plan_tier' => 'solo',
            'billing_status' => 'active',
            'feature_flags' => Tenant::featureFlagsWithModules([], [
                Tenant::MODULE_PRESCRIPTION,
            ]),
        ]);
        $pastDue = Tenant::create([
            'id' => 'pastdue',
            'name' => 'Past Due Clinic',
            'plan_tier' => 'solo',
            'billing_status' => 'past_due',
        ]);

        $this->seedDoctor($listed, 'Dr Karim Skin', Doctor::PRACTICE_DERMATOLOGIST, 'Dhanmondi');
        $this->seedDoctor($noFrontDoor, 'Dr Hidden Only', Doctor::PRACTICE_GENERAL, 'Gulshan');
        $this->seedDoctor($pastDue, 'Dr Past Due', Doctor::PRACTICE_CARDIOLOGIST, 'Uttara');

        $this->get('http://localhost/find')
            ->assertOk()
            ->assertSee('Dr Karim Skin', escape: false)
            ->assertSee('Dhanmondi', escape: false)
            ->assertSee('/drkarim/book?doctor=', escape: false)
            ->assertDontSee('Dr Hidden Only', escape: false)
            ->assertDontSee('Dr Past Due', escape: false);
    }

    public function test_find_search_filters_by_name_and_specialty(): void
    {
        $skin = Tenant::create(['id' => 'skin', 'name' => 'Skin Chamber', 'plan_tier' => 'solo', 'billing_status' => 'active']);
        $heart = Tenant::create(['id' => 'heart', 'name' => 'Heart Chamber', 'plan_tier' => 'solo', 'billing_status' => 'active']);

        $this->seedDoctor($skin, 'Dr Nusrat', Doctor::PRACTICE_DERMATOLOGIST, 'Banani');
        $this->seedDoctor($heart, 'Dr Rahman', Doctor::PRACTICE_CARDIOLOGIST, 'Mirpur');

        $this->get('http://localhost/find?q=Nusrat')
            ->assertOk()
            ->assertSee('Dr Nusrat', escape: false)
            ->assertDontSee('Dr Rahman', escape: false);

        $this->get('http://localhost/find?specialty='.Doctor::PRACTICE_CARDIOLOGIST)
            ->assertOk()
            ->assertSee('Dr Rahman', escape: false)
            ->assertDontSee('Dr Nusrat', escape: false);
    }

    public function test_find_is_not_treated_as_a_tenant_slug(): void
    {
        $this->get('http://localhost/find')->assertOk();
        $this->get('http://localhost/me/login')->assertOk();
    }

    private function seedDoctor(Tenant $tenant, string $name, string $practiceType, string $area): void
    {
        tenancy()->initialize($tenant);
        $chamber = Chamber::create(['name' => 'Main', 'address' => $area]);
        $doctor = Doctor::create([
            'name' => $name,
            'practice_type' => $practiceType,
            'qualifications' => 'MBBS',
            'default_fee_taka' => 800,
        ]);
        ScheduleSession::create([
            'chamber_id' => $chamber->id,
            'doctor_id' => $doctor->id,
            'day_of_week' => Carbon::today()->dayOfWeek,
            'session_name' => 'Evening',
            'start_time' => '18:00',
            'end_time' => '21:00',
            'slot_cap' => 20,
        ]);
        tenancy()->end();
    }
}
