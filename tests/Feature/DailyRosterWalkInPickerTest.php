<?php

namespace Tests\Feature;

use App\Filament\TenantAdmin\Pages\DailyRoster;
use App\Models\Chamber;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\ScheduleSession;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Daily Roster lives in App\Filament\TenantAdmin\Pages. A missing
 * `use App\Services\PatientService` makes PatientService::class resolve to a
 * non-existent Filament page, and the walk-in household picker 500s.
 */
class DailyRosterWalkInPickerTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['id' => 'roster-walkin', 'plan_tier' => 'solo']);
        tenancy()->initialize($this->tenant);

        $chamber = Chamber::create(['name' => 'Main']);
        $doctor = Doctor::create(['name' => 'Dr Walk In']);

        ScheduleSession::create([
            'chamber_id' => $chamber->id,
            'doctor_id' => $doctor->id,
            'day_of_week' => Carbon::today()->dayOfWeek,
            'session_name' => 'Morning',
            'start_time' => '09:00',
            'end_time' => '12:00',
            'slot_cap' => 10,
        ]);

        $this->staff = User::create([
            'name' => 'Desk',
            'email' => 'staff@roster-walkin.test',
            'password' => Hash::make('secret'),
            'role' => User::ROLE_STAFF,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    public function test_walk_in_household_picker_resolves_the_application_patient_service(): void
    {
        Patient::create([
            'name' => 'Fatima Rahman',
            'phone' => '01711112222',
        ]);

        Filament::setCurrentPanel('tenantAdmin');
        $this->actingAs($this->staff);

        Livewire::test(DailyRoster::class)
            ->mountTableAction('newWalkIn')
            ->fillForm([
                'patient_phone' => '01711112222',
            ])
            ->assertSuccessful();
    }
}
