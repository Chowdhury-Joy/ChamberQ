<?php

namespace Tests\Feature;

use App\Filament\TenantAdmin\Support\DeskActionLayout;
use App\Models\Booking;
use App\Models\Chamber;
use App\Models\Doctor;
use App\Models\Domain;
use App\Models\Patient;
use App\Models\ScheduleSession;
use App\Models\Tenant;
use App\Services\BookingService;
use App\Services\CarePath;
use App\Services\PracticeRules;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PracticeRulesTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Chamber $chamber;

    private Doctor $doctor;

    private ScheduleSession $visit;

    private ScheduleSession $report;

    private ScheduleSession $counseling;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-08-19 13:00'));

        $this->tenant = Tenant::create([
            'id' => 'practice-rules',
            'plan_tier' => 'clinic',
            'feature_flags' => Tenant::mergeStationsFlag([], true),
        ]);
        Domain::create(['domain' => 'practice-rules.localhost', 'tenant_id' => 'practice-rules']);
        tenancy()->initialize($this->tenant);

        $this->chamber = Chamber::create(['name' => 'Main']);
        $this->doctor = Doctor::create(['name' => 'Dr Rules', 'default_fee_taka' => 1000]);
        $day = Carbon::today()->dayOfWeek;
        $this->visit = $this->sitting(ScheduleSession::KIND_VISIT, 'Visit', $day);
        $this->report = $this->sitting(ScheduleSession::KIND_REPORT, 'Report', $day);
        $this->counseling = $this->sitting(ScheduleSession::KIND_COUNSELING, 'Counseling', $day);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        tenancy()->end();

        parent::tearDown();
    }

    public function test_default_three_month_window_then_new_visit(): void
    {
        $recent = Patient::create(['name' => 'Recent', 'phone' => '01715552001']);
        $this->completedVisit($recent, Carbon::today()->subMonths(2));
        $this->assertTrue(PracticeRules::isFollowUpEligible($recent, $this->doctor));

        $old = Patient::create(['name' => 'Old', 'phone' => '01715552002']);
        $this->completedVisit($old, Carbon::today()->subMonths(4));
        $this->assertFalse(PracticeRules::isFollowUpEligible($old, $this->doctor));

        $booking = $this->bookVisit($old);
        $this->assertSame(CarePath::VISIT, $booking->care_path);
    }

    public function test_unlimited_follow_up_still_counts_years_later(): void
    {
        $this->doctor->update([
            'practice_rules' => [
                'follow_up_window' => PracticeRules::FOLLOW_UP_UNLIMITED,
            ],
        ]);

        $patient = Patient::create(['name' => 'Loyal', 'phone' => '01715552003']);
        $this->completedVisit($patient, Carbon::today()->subYears(2));

        $this->assertTrue(PracticeRules::isFollowUpEligible($patient, $this->doctor->fresh()));
        $this->assertSame(CarePath::FOLLOW_UP, $this->bookVisit($patient)->care_path);
    }

    public function test_never_follow_up_treats_a_recent_return_as_new(): void
    {
        $this->tenant->update([
            'practice_rules' => ['follow_up_window' => PracticeRules::FOLLOW_UP_NEVER],
        ]);
        tenancy()->initialize($this->tenant->fresh());

        $patient = Patient::create(['name' => 'Always new', 'phone' => '01715552004']);
        $this->completedVisit($patient, Carbon::today()->subWeeks(2));

        $this->assertFalse(PracticeRules::isFollowUpEligible($patient, $this->doctor));
        $this->assertSame(CarePath::VISIT, $this->bookVisit($patient)->care_path);
    }

    public function test_unlimited_paper_file_without_chamberq_visit_is_follow_up(): void
    {
        $this->doctor->update([
            'practice_rules' => [
                'follow_up_window' => PracticeRules::FOLLOW_UP_UNLIMITED,
            ],
        ]);

        $patient = Patient::create([
            'name' => 'Paper',
            'phone' => '01715552005',
            'seen_before_software' => true,
        ]);

        $this->assertTrue(PracticeRules::isFollowUpEligible($patient, $this->doctor->fresh()));
    }

    public function test_paper_file_alone_does_not_unlock_a_timed_follow_up(): void
    {
        $patient = Patient::create([
            'name' => 'Paper timed',
            'phone' => '01715552015',
            'seen_before_software' => true,
        ]);

        $this->assertFalse(PracticeRules::isFollowUpEligible($patient, $this->doctor));
        $this->assertSame(CarePath::VISIT, $this->bookVisit($patient)->care_path);
    }

    public function test_timed_report_is_free_inside_the_window_and_due_after(): void
    {
        $this->doctor->update([
            'practice_rules' => [
                'report_pricing' => PracticeRules::PRICING_TIMED,
                'report_free_for_months' => 3,
                'report_price_inside_taka' => 0,
                'report_price_after_taka' => 800,
            ],
        ]);

        $fresh = $this->reportBooking(Carbon::today()->subMonths(1));
        $this->assertTrue(PracticeRules::bookingIsFeeExempt($fresh));
        $this->assertTrue(DeskActionLayout::shouldHideCollectFee($fresh));

        $late = $this->reportBooking(Carbon::today()->subMonths(4));
        $this->assertFalse(PracticeRules::bookingIsFeeExempt($late));
        $this->assertFalse(DeskActionLayout::shouldHideCollectFee($late));
        $this->assertSame(800, PracticeRules::suggestedRoomFeeTaka($late));
    }

    public function test_clinic_default_applies_until_a_doctor_overrides(): void
    {
        $this->tenant->update([
            'practice_rules' => [
                'follow_up_window' => PracticeRules::FOLLOW_UP_MONTHS,
                'follow_up_months' => 1,
            ],
        ]);
        tenancy()->initialize($this->tenant->fresh());

        $patient = Patient::create(['name' => 'Six weeks', 'phone' => '01715552006']);
        $this->completedVisit($patient, Carbon::today()->subWeeks(6));
        $this->assertFalse(PracticeRules::isFollowUpEligible($patient, $this->doctor->fresh()));

        $this->doctor->update([
            'practice_rules' => [
                'follow_up_window' => PracticeRules::FOLLOW_UP_MONTHS,
                'follow_up_months' => 6,
            ],
        ]);
        $this->assertTrue(PracticeRules::isFollowUpEligible($patient, $this->doctor->fresh()));
    }

    private function sitting(string $kind, string $name, int $day): ScheduleSession
    {
        return ScheduleSession::create([
            'chamber_id' => $this->chamber->id,
            'doctor_id' => $this->doctor->id,
            'day_of_week' => $day,
            'session_name' => $name,
            'kind' => $kind,
            'start_time' => '12:00',
            'end_time' => '20:00',
            'slot_cap' => 20,
        ]);
    }

    private function completedVisit(Patient $patient, Carbon $date): void
    {
        $day = $date->dayOfWeek;
        $session = ScheduleSession::query()
            ->where('kind', ScheduleSession::KIND_VISIT)
            ->where('day_of_week', $day)
            ->first() ?? $this->sitting(ScheduleSession::KIND_VISIT, 'Past visit', $day);

        Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $session->id,
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

    private function bookVisit(Patient $patient): Booking
    {
        return app(BookingService::class)->createBookingForBookable(
            $this->visit,
            Carbon::today()->toDateString(),
            $patient->name,
            $patient->phone,
            sendSms: false,
            patientId: $patient->id,
        );
    }

    private function reportBooking(Carbon $originDate): Booking
    {
        $patient = Patient::create([
            'name' => 'Report '.$originDate->toDateString(),
            'phone' => '01715552'.substr($originDate->format('md'), 0, 3).random_int(10, 99),
        ]);

        $origin = Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $this->visit->id,
            'booking_date' => $originDate->toDateString(),
            'patient_id' => $patient->id,
            'patient_name' => $patient->name,
            'patient_phone' => $patient->phone,
            'serial_number' => 1,
            'status' => 'completed',
            'care_path' => CarePath::VISIT,
        ]);

        return Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $this->report->id,
            'booking_date' => Carbon::today()->toDateString(),
            'patient_id' => $patient->id,
            'patient_name' => $patient->name,
            'patient_phone' => $patient->phone,
            'serial_number' => 1,
            'status' => 'waiting',
            'care_path' => CarePath::VISIT,
            'care_origin_id' => $origin->id,
        ])->fresh(['bookable.doctor', 'careOrigin']);
    }
}
