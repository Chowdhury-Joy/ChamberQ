<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Chamber;
use App\Models\ChamberCashEntry;
use App\Models\Doctor;
use App\Models\Domain;
use App\Models\ScheduleSession;
use App\Models\Tenant;
use App\Services\StationsHandoffService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class CounselingRoomTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Chamber $chamber;

    private Doctor $doctor;

    private ScheduleSession $visit;

    private ScheduleSession $intervention;

    private ScheduleSession $counseling;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'id' => 'counsel-room',
            'plan_tier' => 'clinic',
            'feature_flags' => Tenant::mergeStationsFlag([], true),
        ]);
        Domain::create(['domain' => 'counsel-room.localhost', 'tenant_id' => 'counsel-room']);
        tenancy()->initialize($this->tenant);

        $this->chamber = Chamber::create(['name' => 'Main']);
        $this->doctor = Doctor::create(['name' => 'Dr Counsel', 'default_fee_taka' => 1000]);
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
        $this->counseling = ScheduleSession::create([
            'chamber_id' => $this->chamber->id,
            'doctor_id' => $this->doctor->id,
            'day_of_week' => $day,
            'session_name' => 'Counseling',
            'kind' => ScheduleSession::KIND_COUNSELING,
            'start_time' => '10:00',
            'end_time' => '14:30',
            'slot_cap' => 40,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        tenancy()->end();

        parent::tearDown();
    }

    public function test_send_to_counseling_only_from_a_done_procedure(): void
    {
        Carbon::setTestNow(Carbon::today()->setTime(11, 0));

        $procedure = $this->makeProcedure();
        $handoff = app(StationsHandoffService::class);

        $this->assertFalse($handoff->canSendToCounseling($procedure));

        try {
            $handoff->sendToCounseling($procedure);
            $this->fail('Send to counseling must refuse a procedure that is not done.');
        } catch (InvalidArgumentException $e) {
            $this->assertNotEmpty($e->getMessage());
        }

        $procedure->update(['procedure_status' => Booking::PROCEDURE_DONE]);
        $this->assertTrue($handoff->canSendToCounseling($procedure->fresh(['bookable'])));

        $row = $handoff->sendToCounseling($procedure->fresh(['bookable']));

        $this->assertSame($this->counseling->id, $row->bookable_id);
        $this->assertSame($procedure->id, $row->related_booking_id);
        $this->assertNull($row->voucher_number);
        $this->assertTrue($row->bookable->isFreeKind());
        $this->assertNull(ChamberCashEntry::query()->where('booking_id', $row->id)->first());
    }

    public function test_the_same_patient_cannot_be_sent_to_counseling_twice_in_a_day(): void
    {
        Carbon::setTestNow(Carbon::today()->setTime(11, 0));

        $procedure = $this->makeProcedure();
        $procedure->update(['procedure_status' => Booking::PROCEDURE_DONE]);
        $handoff = app(StationsHandoffService::class);
        $handoff->sendToCounseling($procedure->fresh(['bookable']));

        $this->assertFalse($handoff->canSendToCounseling($procedure->fresh(['bookable'])));

        $this->expectException(InvalidArgumentException::class);
        $handoff->sendToCounseling($procedure->fresh(['bookable']));
    }

    public function test_collect_fee_is_hidden_on_a_counseling_row(): void
    {
        Carbon::setTestNow(Carbon::today()->setTime(11, 0));

        $procedure = $this->makeProcedure();
        $procedure->update(['procedure_status' => Booking::PROCEDURE_DONE]);
        $row = app(StationsHandoffService::class)->sendToCounseling($procedure->fresh(['bookable']));

        $this->assertTrue($row->bookable->isFreeKind());
        $this->assertNull($row->voucher_number);
        $this->assertNull(ChamberCashEntry::query()->where('booking_id', $row->id)->first());
    }

    public function test_module_gating_and_tenant_isolation(): void
    {
        Carbon::setTestNow(Carbon::today()->setTime(11, 0));

        $procedure = $this->makeProcedure();
        $procedure->update(['procedure_status' => Booking::PROCEDURE_DONE]);
        $handoff = app(StationsHandoffService::class);
        $this->assertTrue($handoff->canSendToCounseling($procedure->fresh(['bookable'])));

        $this->tenant->update([
            'feature_flags' => Tenant::mergeStationsFlag($this->tenant->feature_flags ?? [], false),
        ]);
        $this->assertFalse($handoff->canSendToCounseling($procedure->fresh(['bookable'])));

        $id = $procedure->id;
        tenancy()->end();

        $other = Tenant::create([
            'id' => 'counsel-other',
            'feature_flags' => Tenant::mergeStationsFlag([], true),
        ]);
        tenancy()->initialize($other);

        $this->assertNull(Booking::query()->find($id));
    }

    private function makeProcedure(): Booking
    {
        $visitBooking = Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $this->visit->id,
            'booking_date' => Carbon::today()->toDateString(),
            'patient_name' => 'Fatima',
            'patient_phone' => '01715555551',
            'serial_number' => 1,
            'status' => 'waiting',
        ]);

        return app(StationsHandoffService::class)->sendVisitToIntervention(
            $visitBooking,
            Carbon::today()->toDateString(),
        );
    }
}
