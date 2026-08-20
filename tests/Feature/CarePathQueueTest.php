<?php

namespace Tests\Feature;

use App\Exceptions\BookingUnavailableException;
use App\Models\Booking;
use App\Models\Chamber;
use App\Models\Doctor;
use App\Models\Domain;
use App\Models\Patient;
use App\Models\ScheduleSession;
use App\Models\Tenant;
use App\Services\BookingService;
use App\Services\CarePath;
use App\Services\StationsHandoffService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CarePathQueueTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Chamber $chamber;

    private Doctor $doctor;

    private ScheduleSession $visit;

    private ScheduleSession $msk;

    private ScheduleSession $intervention;

    private ScheduleSession $report;

    private ScheduleSession $counseling;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::today()->setTime(13, 0));

        $this->tenant = Tenant::create([
            'id' => 'care-path',
            'plan_tier' => 'clinic',
            'feature_flags' => Tenant::mergeStationsFlag([], true),
        ]);
        Domain::create(['domain' => 'care-path.localhost', 'tenant_id' => 'care-path']);
        tenancy()->initialize($this->tenant);

        $this->chamber = Chamber::create(['name' => 'Panchlaish']);
        $this->doctor = Doctor::create(['name' => 'Dr Moin', 'default_fee_taka' => 1000]);
        $day = Carbon::today()->dayOfWeek;

        $this->intervention = $this->sitting('Intervention', ScheduleSession::KIND_INTERVENTION, '10:00', '12:00', $day);
        $this->msk = $this->sitting('MSK', ScheduleSession::KIND_MSK, '12:00', '14:30', $day);
        $this->visit = $this->sitting('Visit', ScheduleSession::KIND_VISIT, '12:00', '14:30', $day);
        $this->report = $this->sitting('Report', ScheduleSession::KIND_REPORT, '10:00', '14:30', $day);
        $this->counseling = $this->sitting('Counseling', ScheduleSession::KIND_COUNSELING, '10:00', '14:30', $day);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        tenancy()->end();

        parent::tearDown();
    }

    public function test_a_new_visit_offers_lab_and_intervention_then_the_type(): void
    {
        $visit = $this->bookVisit('Jamal Uddin', '01715550009');
        $handoff = app(StationsHandoffService::class);

        $this->assertSame(CarePath::VISIT, $visit->care_path);
        $this->assertTrue($handoff->canSendToLab($visit));
        $this->assertTrue($handoff->canSendVisit($visit));
        $this->assertSame(
            [ScheduleSession::KIND_MSK => 'MSK scan'],
            $handoff->labTypeOptions($visit),
        );

        $msk = $handoff->sendToLab($visit->fresh(['bookable']), ScheduleSession::KIND_MSK);
        $this->assertSame($this->msk->id, $msk->bookable_id);
        $this->assertTrue($handoff->canSendVisit($visit->fresh(['bookable'])));
        $this->assertFalse($handoff->canSendToLab($visit->fresh(['bookable'])));
    }

    public function test_a_new_visit_can_skip_lab_and_go_straight_to_intervention(): void
    {
        $visit = $this->bookVisit('Skip Lab', '01715550010');
        $handoff = app(StationsHandoffService::class);

        $procedure = $handoff->sendVisitToIntervention(
            $visit->fresh(['bookable']),
            Carbon::today()->toDateString(),
        );
        $procedure->update(['procedure_status' => Booking::PROCEDURE_DONE]);

        $this->assertTrue($handoff->canSendToReport($procedure->fresh(['bookable'])));
        $this->assertFalse($handoff->canSendToCounseling($procedure->fresh(['bookable'])));
    }

    public function test_send_to_lab_refuses_an_unknown_type(): void
    {
        $visit = $this->bookVisit('Bad Type', '01715550011');
        $handoff = app(StationsHandoffService::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Choose a lab type.');
        $handoff->sendToLab($visit->fresh(['bookable']), 'not-a-lab');
    }

    public function test_a_new_visit_must_go_msk_then_intervention_then_report_then_counseling(): void
    {
        $visit = $this->bookVisit('Fatima New', '01715550001');
        $handoff = app(StationsHandoffService::class);

        $this->assertSame(CarePath::VISIT, $visit->care_path);
        $this->assertTrue($handoff->canSendToLab($visit));
        $this->assertTrue($handoff->canSendVisit($visit));

        $msk = $handoff->sendToLab($visit->fresh(['bookable']), ScheduleSession::KIND_MSK);
        $this->assertSame($this->msk->id, $msk->bookable_id);
        $this->assertSame(CarePath::VISIT, $msk->care_path);
        $this->assertTrue($handoff->canSendVisit($msk->fresh(['bookable'])));
        $this->assertFalse($handoff->canSendToReport($msk->fresh(['bookable'])));

        $procedure = $handoff->sendVisitToIntervention(
            $msk->fresh(['bookable']),
            Carbon::today()->toDateString(),
        );
        $procedure->update(['procedure_status' => Booking::PROCEDURE_DONE]);

        $this->assertFalse($handoff->canSendToCounseling($procedure->fresh(['bookable'])));
        $this->assertTrue($handoff->canSendToReport($procedure->fresh(['bookable'])));

        $report = $handoff->sendToReport($procedure->fresh(['bookable']));
        $this->assertSame($this->report->id, $report->bookable_id);
        $this->assertTrue($handoff->canSendToCounseling($report->fresh(['bookable'])));

        $counseling = $handoff->sendToCounseling($report->fresh(['bookable']));
        $this->assertSame($this->counseling->id, $counseling->bookable_id);
    }

    public function test_follow_up_within_three_months_forks_to_msk_or_intervention(): void
    {
        $patient = Patient::create(['name' => 'Rafiq Return', 'phone' => '01715550002']);
        $this->seedCompletedVisit($patient, Carbon::today()->subMonths(2));

        $visit = $this->bookVisit($patient->name, $patient->phone, $patient->id);
        $handoff = app(StationsHandoffService::class);

        $this->assertSame(CarePath::FOLLOW_UP, $visit->fresh()->care_path);
        $this->assertTrue($handoff->canSendToLab($visit->fresh(['bookable'])));
        $this->assertTrue($handoff->canSendVisit($visit->fresh(['bookable'])));

        $msk = $handoff->sendToLab($visit->fresh(['bookable']), ScheduleSession::KIND_MSK);
        $this->assertSame(CarePath::BRANCH_MSK, $msk->care_branch);
        $this->assertFalse($handoff->canSendVisit($msk->fresh(['bookable'])));
        $this->assertTrue($handoff->canSendToReport($msk->fresh(['bookable'])));

        $report = $handoff->sendToReport($msk->fresh(['bookable']));
        $this->assertFalse($handoff->canSendToCounseling($report->fresh(['bookable'])));
    }

    public function test_follow_up_intervention_branch_skips_msk_and_report(): void
    {
        $patient = Patient::create(['name' => 'Nusrat Return', 'phone' => '01715550003']);
        $this->seedCompletedVisit($patient, Carbon::today()->subWeeks(3));

        $visit = $this->bookVisit($patient->name, $patient->phone, $patient->id);
        $handoff = app(StationsHandoffService::class);

        $procedure = $handoff->sendVisitToIntervention(
            $visit->fresh(['bookable']),
            Carbon::today()->toDateString(),
        );
        $this->assertSame(CarePath::BRANCH_INTERVENTION, $procedure->care_branch);
        $procedure->update(['procedure_status' => Booking::PROCEDURE_DONE]);

        $this->assertFalse($handoff->canSendToReport($procedure->fresh(['bookable'])));
        $this->assertTrue($handoff->canSendToCounseling($procedure->fresh(['bookable'])));
    }

    public function test_a_return_after_three_months_is_a_new_visit_path(): void
    {
        $patient = Patient::create(['name' => 'Old File', 'phone' => '01715550004']);
        $this->seedCompletedVisit($patient, Carbon::today()->subMonths(4));

        $visit = $this->bookVisit($patient->name, $patient->phone, $patient->id);

        $this->assertSame(CarePath::VISIT, $visit->fresh()->care_path);
        $this->assertFalse(CarePath::isFollowUpEligible($patient));
    }

    public function test_direct_intervention_goes_to_counseling_and_skips_report(): void
    {
        $procedure = app(BookingService::class)->createBookingForBookable(
            $this->intervention,
            Carbon::today()->toDateString(),
            'Walk-in OT',
            '01715550005',
            sendSms: false,
            allowOverflow: true,
            allowEndedToday: true,
        );

        $this->assertSame(CarePath::INTERVENTION, $procedure->fresh()->care_path);

        $handoff = app(StationsHandoffService::class);
        $procedure->update(['procedure_status' => Booking::PROCEDURE_DONE]);

        $this->assertFalse($handoff->canSendToReport($procedure->fresh(['bookable'])));
        $this->assertTrue($handoff->canSendToCounseling($procedure->fresh(['bookable'])));
    }

    public function test_walk_in_cannot_take_an_msk_or_report_seat(): void
    {
        $booking = app(BookingService::class);

        try {
            $booking->createBookingForBookable(
                $this->msk,
                Carbon::today()->toDateString(),
                'Walk-in',
                '01715550006',
                sendSms: false,
                allowOverflow: true,
                allowEndedToday: true,
            );
            $this->fail('MSK must refuse a walk-in.');
        } catch (BookingUnavailableException $e) {
            $this->assertSame('staff_handoff', $e->errorCode);
        }

        $this->expectException(BookingUnavailableException::class);
        $booking->createBookingForBookable(
            $this->report,
            Carbon::today()->toDateString(),
            'Walk-in',
            '01715550007',
            sendSms: false,
            allowOverflow: true,
            allowEndedToday: true,
        );
    }

    public function test_without_msk_and_report_rooms_the_short_path_still_works(): void
    {
        $this->msk->delete();
        $this->report->delete();

        $visit = $this->bookVisit('Short Path', '01715550008');
        $handoff = app(StationsHandoffService::class);

        $this->assertFalse($handoff->canSendToLab($visit->fresh(['bookable'])));
        $this->assertTrue($handoff->canSendVisit($visit->fresh(['bookable'])));

        $procedure = $handoff->sendVisitToIntervention(
            $visit->fresh(['bookable']),
            Carbon::today()->toDateString(),
        );
        $procedure->update(['procedure_status' => Booking::PROCEDURE_DONE]);

        $this->assertFalse($handoff->canSendToReport($procedure->fresh(['bookable'])));
        $this->assertTrue($handoff->canSendToCounseling($procedure->fresh(['bookable'])));
    }

    private function sitting(string $name, string $kind, string $start, string $end, int $day): ScheduleSession
    {
        return ScheduleSession::create([
            'chamber_id' => $this->chamber->id,
            'doctor_id' => $this->doctor->id,
            'day_of_week' => $day,
            'session_name' => $name,
            'kind' => $kind,
            'start_time' => $start,
            'end_time' => $end,
            'slot_cap' => 20,
        ]);
    }

    private function bookVisit(string $name, string $phone, ?string $patientId = null): Booking
    {
        return app(BookingService::class)->createBookingForBookable(
            $this->visit,
            Carbon::today()->toDateString(),
            $name,
            $phone,
            sendSms: false,
            patientId: $patientId,
        );
    }

    private function seedCompletedVisit(Patient $patient, Carbon $date): void
    {
        $pastDay = $date->dayOfWeek;
        $pastVisit = ScheduleSession::query()
            ->where('kind', ScheduleSession::KIND_VISIT)
            ->where('day_of_week', $pastDay)
            ->first();

        if (! $pastVisit) {
            $pastVisit = $this->sitting('Past visit', ScheduleSession::KIND_VISIT, '12:00', '14:30', $pastDay);
        }

        Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $pastVisit->id,
            'booking_date' => $date->toDateString(),
            'patient_id' => $patient->id,
            'patient_name' => $patient->name,
            'patient_phone' => $patient->phone,
            'serial_number' => 1,
            'status' => 'completed',
            'completed_at' => $date->copy()->setTime(13, 0),
            'care_path' => CarePath::VISIT,
        ]);
    }
}
