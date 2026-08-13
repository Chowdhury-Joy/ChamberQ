<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Chamber;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\ScheduleSession;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VisitRecord;
use App\Services\PatientService;
use App\Services\VisitRecordService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Staff correcting a duplicated patient must never cost the doctor the
 * clinical history attached to that person.
 *
 * `visit_records.patient_id` and `prescriptions.patient_id` are nullOnDelete
 * foreign keys, so a merge that only moves bookings and then deletes the
 * duplicate silently NULLs both — and nothing in the UI can put them back.
 */
class PatientMergeHistoryTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $doctor;

    private ScheduleSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['id' => 'merge-history', 'plan_tier' => 'solo']);
        tenancy()->initialize($this->tenant);

        $this->doctor = User::create([
            'name' => 'Dr Merge',
            'email' => 'doc@merge.test',
            'password' => Hash::make('secret'),
            'role' => User::ROLE_DOCTOR,
            'tenant_id' => $this->tenant->id,
        ]);

        $chamber = Chamber::create(['name' => 'Main', 'address' => 'Dhaka']);
        $profile = Doctor::create(['name' => 'Dr Merge', 'registration_number' => 'B-4242']);

        $this->session = ScheduleSession::create([
            'chamber_id' => $chamber->id,
            'doctor_id' => $profile->id,
            'day_of_week' => Carbon::today()->dayOfWeek,
            'session_name' => 'Morning',
            'start_time' => '09:00',
            'end_time' => '12:00',
            'slot_cap' => 20,
        ]);
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    private function completedVisitFor(Patient $patient, int $serial): array
    {
        $booking = Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $this->session->id,
            'booking_date' => Carbon::today()->toDateString(),
            'patient_id' => $patient->id,
            'patient_name' => $patient->name,
            'patient_phone' => $patient->phone,
            'serial_number' => $serial,
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $visit = VisitRecord::create([
            'booking_id' => $booking->id,
            'patient_id' => $patient->id,
            'recorded_by' => $this->doctor->id,
            'clinical_notes' => 'Penicillin allergy noted',
            'recorded_at' => now(),
        ]);

        $prescription = Prescription::create([
            'visit_record_id' => $visit->id,
            'patient_id' => $patient->id,
            'prescribed_by' => $this->doctor->id,
            'advice' => 'Rest and fluids',
        ]);

        return [$booking, $visit, $prescription];
    }

    public function test_merging_a_duplicate_keeps_visit_records_and_prescriptions(): void
    {
        $keep = Patient::create(['name' => 'Fatima Rahman', 'phone' => '01711111111']);
        $duplicate = Patient::create(['name' => 'Fatima Rehman', 'phone' => '01711111111']);

        [$booking, $visit, $prescription] = $this->completedVisitFor($duplicate, 1);

        app(PatientService::class)->mergePatients($keep, $duplicate);

        $this->assertSame($keep->id, $booking->fresh()->patient_id);
        $this->assertSame(
            $keep->id,
            $visit->fresh()->patient_id,
            'The visit record must follow the patient it belongs to',
        );
        $this->assertSame(
            $keep->id,
            $prescription->fresh()->patient_id,
            'The prescription must follow the patient it belongs to',
        );
    }

    public function test_the_doctor_still_sees_history_after_a_merge(): void
    {
        $keep = Patient::create(['name' => 'Fatima Rahman', 'phone' => '01711111111']);
        $duplicate = Patient::create(['name' => 'Fatima Rehman', 'phone' => '01711111111']);

        $this->completedVisitFor($duplicate, 1);

        app(PatientService::class)->mergePatients($keep, $duplicate);

        $lastVisit = app(VisitRecordService::class)->lastRecordedVisitForPatient($keep->fresh());

        $this->assertNotNull(
            $lastVisit,
            'Consult Screen reads history by patient_id — a merge must not blank it',
        );
        $this->assertSame('Penicillin allergy noted', $lastVisit->clinical_notes);
        $this->assertTrue(app(VisitRecordService::class)->patientHasRecordedNotes($keep->fresh()));
    }

    /**
     * Chambers that already ran a merge before the fix have visit records and
     * prescriptions sitting with a NULL patient_id. The link is recoverable
     * from the booking, which the old merge did move correctly.
     */
    public function test_the_backfill_migration_relinks_already_orphaned_history(): void
    {
        $patient = Patient::create(['name' => 'Fatima Rahman', 'phone' => '01711111111']);

        [$booking, $visit, $prescription] = $this->completedVisitFor($patient, 3);

        // Reproduce exactly what the old merge left behind: booking still
        // points at the surviving patient, the clinical rows do not.
        DB::table('visit_records')->where('id', $visit->id)->update(['patient_id' => null]);
        DB::table('prescriptions')->where('id', $prescription->id)->update(['patient_id' => null]);

        $this->assertNull($visit->fresh()->patient_id, 'precondition: orphaned');
        $this->assertNull($prescription->fresh()->patient_id, 'precondition: orphaned');

        require_once database_path(
            'migrations/2026_08_11_130000_relink_orphaned_visit_records_and_prescriptions.php'
        );
        $migration = require database_path(
            'migrations/2026_08_11_130000_relink_orphaned_visit_records_and_prescriptions.php'
        );
        $migration->up();

        $this->assertSame(
            $patient->id,
            $visit->fresh()->patient_id,
            'The backfill must re-link the visit record from its booking',
        );
        $this->assertSame(
            $patient->id,
            $prescription->fresh()->patient_id,
            'The backfill must re-link the prescription from its visit record',
        );

        $this->assertNotNull(
            app(VisitRecordService::class)->lastRecordedVisitForPatient($patient->fresh()),
            'History should be visible to the doctor again',
        );
    }

    public function test_moving_a_booking_moves_its_visit_record_and_prescription(): void
    {
        $wrong = Patient::create(['name' => 'Wrong Person', 'phone' => '01722222222']);
        $right = Patient::create(['name' => 'Right Person', 'phone' => '01733333333']);

        [$booking, $visit, $prescription] = $this->completedVisitFor($wrong, 2);

        app(PatientService::class)->moveBookingToPatient($booking, $right);

        $this->assertSame($right->id, $booking->fresh()->patient_id);
        $this->assertSame(
            $right->id,
            $visit->fresh()->patient_id,
            'A mis-filed visit must not stay under the wrong patient',
        );
        $this->assertSame($right->id, $prescription->fresh()->patient_id);
    }
}
