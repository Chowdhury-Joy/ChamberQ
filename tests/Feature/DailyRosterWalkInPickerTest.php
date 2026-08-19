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
                'visit_type' => 'usual',
            ])
            ->assertSuccessful();
    }

    public function test_walk_in_intervention_stores_the_procedure_from_the_catalogue(): void
    {
        $this->tenant->update([
            'feature_flags' => Tenant::mergeStationsFlag(
                $this->tenant->feature_flags ?? [],
                true,
            ),
        ]);
        $this->tenant->refresh();
        tenancy()->initialize($this->tenant);

        $chamber = Chamber::query()->first();
        $doctor = Doctor::query()->first();
        $ot = ScheduleSession::create([
            'chamber_id' => $chamber->id,
            'doctor_id' => $doctor->id,
            'kind' => ScheduleSession::KIND_INTERVENTION,
            'day_of_week' => Carbon::today()->dayOfWeek,
            'session_name' => 'OT',
            'start_time' => '14:00',
            'end_time' => '16:00',
            'slot_cap' => 8,
        ]);
        $prp = \App\Models\FeeCatalogItem::create([
            'label' => 'PRP knee (single)',
            'list_price_taka' => 8000,
            'house_share_taka' => 1000,
            'sitting_kind' => ScheduleSession::KIND_INTERVENTION,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        Filament::setCurrentPanel('tenantAdmin');
        $this->actingAs($this->staff);

        Livewire::test(DailyRoster::class)
            ->mountTableAction('newWalkIn')
            ->fillForm([
                'visit_type' => 'intervention',
                'intervention_type' => (string) $prp->id,
                'bookable' => 'session:'.$ot->id,
                'patient_phone' => '01715553099',
                'patient_name' => 'Walk-in PRP',
            ])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $booking = \App\Models\Booking::query()->where('patient_phone', '01715553099')->first();
        $this->assertNotNull($booking);
        $this->assertSame($ot->id, $booking->bookable_id);
        $this->assertSame($prp->id, $booking->fee_catalog_item_id);
    }
}
