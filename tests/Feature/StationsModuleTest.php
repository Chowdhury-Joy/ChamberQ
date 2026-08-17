<?php

namespace Tests\Feature;

use App\Models\Chamber;
use App\Models\Doctor;
use App\Models\Domain;
use App\Models\ScheduleSession;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StationsModuleTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'id' => 'stations-clinic',
            'plan_tier' => 'clinic',
            'feature_flags' => Tenant::mergeStationsFlag([], true),
        ]);
        Domain::create(['domain' => 'stations-clinic.localhost', 'tenant_id' => 'stations-clinic']);
    }

    public function test_stations_defaults_off_without_flag(): void
    {
        $solo = Tenant::create(['id' => 'solo-no-stations', 'plan_tier' => 'solo']);

        $this->assertFalse($solo->hasStations());
    }

    public function test_stations_flag_is_explicit_opt_in(): void
    {
        $this->assertTrue($this->tenant->hasStations());

        $off = Tenant::create([
            'id' => 'stations-off',
            'feature_flags' => [Tenant::MODULE_STATIONS => false],
        ]);

        $this->assertFalse($off->hasStations());
    }

    public function test_consult_kind_is_detected_on_session(): void
    {
        tenancy()->initialize($this->tenant);

        $chamber = Chamber::create(['name' => 'Main']);
        $doctor = Doctor::create(['name' => 'Dr S', 'default_fee_taka' => 1000]);
        $consult = ScheduleSession::create([
            'chamber_id' => $chamber->id,
            'doctor_id' => $doctor->id,
            'day_of_week' => Carbon::today()->dayOfWeek,
            'session_name' => 'Talk',
            'kind' => ScheduleSession::KIND_CONSULT,
            'start_time' => '09:00',
            'end_time' => '10:00',
            'slot_cap' => 10,
        ]);

        $this->assertTrue($consult->isConsultKind());
        $this->assertTrue($consult->isFreeKind());
        $this->assertTrue($consult->isPubliclyBookable());

        $counseling = ScheduleSession::create([
            'chamber_id' => $chamber->id,
            'doctor_id' => $doctor->id,
            'day_of_week' => Carbon::today()->dayOfWeek,
            'session_name' => 'Counseling',
            'kind' => ScheduleSession::KIND_COUNSELING,
            'start_time' => '10:00',
            'end_time' => '14:30',
            'slot_cap' => 10,
        ]);
        $this->assertTrue($counseling->isFreeKind());
        $this->assertFalse($counseling->isPubliclyBookable());

        tenancy()->end();
    }
}
