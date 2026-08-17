<?php

namespace Tests\Feature;

use App\Filament\TenantAdmin\Pages\MissedProcedures;
use App\Models\Booking;
use App\Models\Chamber;
use App\Models\Doctor;
use App\Models\Domain;
use App\Models\ScheduleSession;
use App\Models\Tenant;
use App\Models\User;
use App\Services\StationsHandoffService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MissedProcedureFollowUpTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Chamber $chamber;

    private Doctor $doctor;

    private ScheduleSession $visit;

    private ScheduleSession $intervention;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'id' => 'missed-ot',
            'plan_tier' => 'clinic',
            'feature_flags' => Tenant::mergeStationsFlag([], true),
        ]);
        Domain::create(['domain' => 'missed-ot.localhost', 'tenant_id' => 'missed-ot']);
        tenancy()->initialize($this->tenant);

        $this->chamber = Chamber::create(['name' => 'Main']);
        $this->doctor = Doctor::create(['name' => 'Dr Missed']);
        $day = Carbon::today()->dayOfWeek;
        $this->intervention = ScheduleSession::create([
            'chamber_id' => $this->chamber->id,
            'doctor_id' => $this->doctor->id,
            'day_of_week' => $day,
            'session_name' => 'Intervention',
            'kind' => ScheduleSession::KIND_INTERVENTION,
            'start_time' => '10:00',
            'end_time' => '12:00',
            'slot_cap' => 10,
        ]);
        $this->visit = ScheduleSession::create([
            'chamber_id' => $this->chamber->id,
            'doctor_id' => $this->doctor->id,
            'day_of_week' => $day,
            'session_name' => 'Visit',
            'kind' => ScheduleSession::KIND_VISIT,
            'start_time' => '12:00',
            'end_time' => '14:30',
            'slot_cap' => 20,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        tenancy()->end();

        parent::tearDown();
    }

    public function test_overdue_list_contains_only_unfinished_past_intervention_rows(): void
    {
        Carbon::setTestNow(Carbon::today()->setTime(11, 0));

        $overdue = $this->procedureOn(Carbon::yesterday()->toDateString(), 'Overdue', '01716000001');
        $done = $this->procedureOn(Carbon::yesterday()->toDateString(), 'Done already', '01716000002');
        $done->update(['procedure_status' => Booking::PROCEDURE_DONE]);
        $cancelled = $this->procedureOn(Carbon::yesterday()->toDateString(), 'Cancelled', '01716000003');
        $cancelled->update(['status' => 'cancelled']);
        $future = $this->procedureOn(Carbon::today()->addWeek()->toDateString(), 'Future', '01716000004');

        $ids = app(StationsHandoffService::class)->overdueProceduresQuery()->pluck('id');

        $this->assertTrue($ids->contains($overdue->id));
        $this->assertFalse($ids->contains($done->id));
        $this->assertFalse($ids->contains($cancelled->id));
        $this->assertFalse($ids->contains($future->id));
    }

    public function test_a_moved_row_leaves_the_overdue_list(): void
    {
        Carbon::setTestNow(Carbon::today()->setTime(11, 0));

        $overdue = $this->procedureOn(Carbon::yesterday()->toDateString(), 'Move me', '01716000005');
        $handoff = app(StationsHandoffService::class);
        $this->assertTrue($handoff->overdueProceduresQuery()->pluck('id')->contains($overdue->id));

        $moved = $handoff->moveProcedure($overdue, Carbon::today()->addWeek()->toDateString());

        $this->assertFalse($handoff->overdueProceduresQuery()->pluck('id')->contains($moved->id));
        $this->assertSame('waiting', $moved->status);
    }

    public function test_the_worklist_is_stations_only_for_desk_staff(): void
    {
        $staff = User::create([
            'name' => 'Desk',
            'email' => 'desk@missed-ot.loc',
            'password' => Hash::make('secret'),
            'role' => User::ROLE_STAFF,
            'tenant_id' => 'missed-ot',
        ]);
        $this->actingAs($staff);
        $this->assertTrue(MissedProcedures::canAccess());

        $this->tenant->update([
            'feature_flags' => Tenant::mergeStationsFlag($this->tenant->feature_flags ?? [], false),
        ]);
        $this->assertFalse(MissedProcedures::canAccess());
    }

    private function procedureOn(string $date, string $name, string $phone): Booking
    {
        $when = Carbon::parse($date);
        $visit = ScheduleSession::query()->firstOrCreate(
            [
                'chamber_id' => $this->chamber->id,
                'doctor_id' => $this->doctor->id,
                'day_of_week' => $when->dayOfWeek,
                'kind' => ScheduleSession::KIND_VISIT,
            ],
            [
                'session_name' => 'Visit',
                'start_time' => '12:00',
                'end_time' => '14:30',
                'slot_cap' => 20,
            ],
        );
        ScheduleSession::query()->firstOrCreate(
            [
                'chamber_id' => $this->chamber->id,
                'doctor_id' => $this->doctor->id,
                'day_of_week' => $when->dayOfWeek,
                'kind' => ScheduleSession::KIND_INTERVENTION,
            ],
            [
                'session_name' => 'Intervention',
                'start_time' => '10:00',
                'end_time' => '12:00',
                'slot_cap' => 10,
            ],
        );

        $visitBooking = Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $visit->id,
            'booking_date' => $date,
            'patient_name' => $name,
            'patient_phone' => $phone,
            'serial_number' => Booking::query()->where('bookable_id', $visit->id)->where('booking_date', $date)->count() + 1,
            'status' => 'waiting',
        ]);

        return app(StationsHandoffService::class)->sendVisitToIntervention($visitBooking, $date);
    }
}
