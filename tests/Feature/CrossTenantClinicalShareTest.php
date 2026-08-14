<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Chamber;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\ScheduleSession;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VisitRecord;
use App\Services\BookingService;
use App\Services\CrossTenantClinicalHistoryService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class CrossTenantClinicalShareTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    private ScheduleSession $sessionA;

    private ScheduleSession $sessionB;

    private User $doctorA;

    private User $doctorB;

    /**
     * A fixed Monday morning, safely inside the 09:00–12:00 sessions below.
     * Without this the suite books against the real clock, and
     * BookingService rejects every booking once the app timezone passes noon.
     */
    private const FROZEN_NOW = '2026-01-05 09:00';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse(self::FROZEN_NOW));

        Cache::flush();

        $this->tenantA = Tenant::create(['id' => 'share-a', 'plan_tier' => 'solo', 'name' => 'Clinic A']);
        $this->tenantB = Tenant::create(['id' => 'share-b', 'plan_tier' => 'solo', 'name' => 'Clinic B']);

        tenancy()->initialize($this->tenantA);
        $this->doctorA = User::create([
            'name' => 'Dr A',
            'email' => 'a@share.test',
            'password' => Hash::make('secret'),
            'role' => User::ROLE_DOCTOR,
            'tenant_id' => $this->tenantA->id,
        ]);
        $chamberA = Chamber::create(['name' => 'A Main', 'address' => 'Dhaka']);
        $profileA = Doctor::create(['name' => 'Dr A', 'registration_number' => 'A-1']);
        $this->sessionA = ScheduleSession::create([
            'chamber_id' => $chamberA->id,
            'doctor_id' => $profileA->id,
            'day_of_week' => Carbon::today()->dayOfWeek,
            'session_name' => 'Morning',
            'start_time' => '09:00',
            'end_time' => '12:00',
            'slot_cap' => 20,
        ]);
        tenancy()->end();

        tenancy()->initialize($this->tenantB);
        $this->doctorB = User::create([
            'name' => 'Dr B',
            'email' => 'b@share.test',
            'password' => Hash::make('secret'),
            'role' => User::ROLE_DOCTOR,
            'tenant_id' => $this->tenantB->id,
        ]);
        $chamberB = Chamber::create(['name' => 'B Main', 'address' => 'Chattogram']);
        $profileB = Doctor::create(['name' => 'Dr B', 'registration_number' => 'B-1']);
        $this->sessionB = ScheduleSession::create([
            'chamber_id' => $chamberB->id,
            'doctor_id' => $profileB->id,
            'day_of_week' => Carbon::today()->dayOfWeek,
            'session_name' => 'Morning',
            'start_time' => '09:00',
            'end_time' => '12:00',
            'slot_cap' => 20,
        ]);
        tenancy()->end();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        tenancy()->end();
        parent::tearDown();
    }

    public function test_booking_checkbox_off_sets_share_flag_false(): void
    {
        tenancy()->initialize($this->tenantA);

        $booking = app(BookingService::class)->createBookingForBookable(
            $this->sessionA,
            Carbon::today()->toDateString(),
            'Fatima Rahman',
            '01711112222',
            [],
            false,
            null,
            false,
            null,
            false,
        );

        $this->assertFalse((bool) $booking->patient->share_clinical_history);
    }

    public function test_booking_checkbox_on_sets_share_flag_true(): void
    {
        tenancy()->initialize($this->tenantA);

        $booking = app(BookingService::class)->createBookingForBookable(
            $this->sessionA,
            Carbon::today()->toDateString(),
            'Fatima Rahman',
            '01711112222',
            [],
            false,
            null,
            false,
            null,
            true,
        );

        $this->assertTrue((bool) $booking->patient->share_clinical_history);
    }

    public function test_other_chamber_sees_shared_visit_notes_and_rx_but_not_media(): void
    {
        tenancy()->initialize($this->tenantA);
        $patientA = Patient::create([
            'name' => 'Fatima Rahman',
            'phone' => '01711112222',
            'share_clinical_history' => true,
            'allergies' => 'Penicillin',
            // Same person, so the same age on both sides. Age is what separates
            // this Fatima from a relative on the same household phone.
            'age' => 34,
            'age_recorded_at' => today(),
        ]);
        $this->seedCompletedVisit($patientA, $this->sessionA, $this->doctorA, 'Diabetes follow-up', 'NAPA');
        tenancy()->end();

        tenancy()->initialize($this->tenantB);
        $patientB = Patient::create([
            'name' => 'Fatima Rahman',
            'phone' => '01711112222',
            'share_clinical_history' => true,
            'age' => 34,
            'age_recorded_at' => today(),
        ]);

        Log::spy();

        $shared = app(CrossTenantClinicalHistoryService::class)->sharedVisitsFor($patientB, $this->doctorB->id);

        $this->assertCount(1, $shared);
        $this->assertSame('Diabetes follow-up', $shared->first()->visitRecord?->clinical_notes);
        $this->assertNull($shared->first()->visitRecord?->voice_path);
        $this->assertNull($shared->first()->visitRecord?->photo_path);
        $this->assertNull($shared->first()->visitRecord?->report_photo_paths);
        $this->assertSame('NAPA', $shared->first()->medicines[0]['brand'] ?? null);
        $this->assertSame('Clinic A', $shared->first()->sourceLabel);

        $warnings = app(CrossTenantClinicalHistoryService::class)->matchingSharedPatients($patientB);
        $this->assertTrue($warnings->contains(fn (Patient $p) => $p->allergies === 'Penicillin'));
    }

    public function test_share_off_on_viewer_blocks_cross_chamber_history(): void
    {
        tenancy()->initialize($this->tenantA);
        $patientA = Patient::create([
            'name' => 'Fatima Rahman',
            'phone' => '01711112222',
            'share_clinical_history' => true,
        ]);
        $this->seedCompletedVisit($patientA, $this->sessionA, $this->doctorA, 'Secret note', 'NAPA');
        tenancy()->end();

        tenancy()->initialize($this->tenantB);
        $patientB = Patient::create([
            'name' => 'Fatima Rahman',
            'phone' => '01711112222',
            'share_clinical_history' => false,
        ]);

        $shared = app(CrossTenantClinicalHistoryService::class)->sharedVisitsFor($patientB);

        $this->assertTrue($shared->isEmpty());
    }

    public function test_same_phone_different_name_does_not_link(): void
    {
        tenancy()->initialize($this->tenantA);
        $patientA = Patient::create([
            'name' => 'Fatima Rahman',
            'phone' => '01711112222',
            'share_clinical_history' => true,
        ]);
        $this->seedCompletedVisit($patientA, $this->sessionA, $this->doctorA, 'Fatima notes', 'NAPA');
        tenancy()->end();

        tenancy()->initialize($this->tenantB);
        $husband = Patient::create([
            'name' => 'Karim Rahman',
            'phone' => '01711112222',
            'share_clinical_history' => true,
        ]);

        $shared = app(CrossTenantClinicalHistoryService::class)->sharedVisitsFor($husband);

        $this->assertTrue($shared->isEmpty());
    }

    public function test_remote_share_off_hides_that_chambers_visits(): void
    {
        tenancy()->initialize($this->tenantA);
        $patientA = Patient::create([
            'name' => 'Fatima Rahman',
            'phone' => '01711112222',
            'share_clinical_history' => false,
        ]);
        $this->seedCompletedVisit($patientA, $this->sessionA, $this->doctorA, 'Hidden', 'NAPA');
        tenancy()->end();

        tenancy()->initialize($this->tenantB);
        $patientB = Patient::create([
            'name' => 'Fatima Rahman',
            'phone' => '01711112222',
            'share_clinical_history' => true,
        ]);

        $shared = app(CrossTenantClinicalHistoryService::class)->sharedVisitsFor($patientB);

        $this->assertTrue($shared->isEmpty());
    }

    public function test_online_booking_accepts_share_clinical_history_zero(): void
    {
        tenancy()->initialize($this->tenantA);

        $response = $this->postJson('/share-a/api/bookings', [
            'bookable_type' => 'session',
            'bookable_id' => $this->sessionA->id,
            'booking_date' => Carbon::today()->toDateString(),
            'patient_name' => 'Fatima Rahman',
            'patient_phone' => '01733334444',
            'share_clinical_history' => '0',
        ]);

        $response->assertOk();
        $patient = Patient::query()->where('phone', '01733334444')->first();
        $this->assertNotNull($patient);
        $this->assertFalse((bool) $patient->share_clinical_history);
    }

    /**
     * One mobile is routinely a whole household's here — the booking wizard has
     * a household picker for exactly that reason — and names repeat inside a
     * family. Matching on phone + name alone put a relative's diagnoses in
     * front of a prescribing doctor. Age is what tells them apart.
     */
    public function test_two_relatives_on_one_phone_with_the_same_name_do_not_link(): void
    {
        tenancy()->initialize($this->tenantA);
        $mother = Patient::create([
            'name' => 'Fatima Rahman',
            'phone' => '01711112222',
            'share_clinical_history' => true,
            'age' => 62,
            'age_recorded_at' => today(),
        ]);
        $this->seedCompletedVisit($mother, $this->sessionA, $this->doctorA, 'SECRETMOTHERNOTE', 'NAPA');
        tenancy()->end();

        tenancy()->initialize($this->tenantB);
        $daughter = Patient::create([
            'name' => 'Fatima Rahman',
            'phone' => '01711112222',
            'share_clinical_history' => true,
            'age' => 28,
            'age_recorded_at' => today(),
        ]);

        $shared = app(CrossTenantClinicalHistoryService::class)->sharedVisitsFor($daughter, $this->doctorB->id);

        $this->assertTrue(
            $shared->isEmpty(),
            'A relative sharing a phone and a name is not the same patient',
        );

        $warnings = app(CrossTenantClinicalHistoryService::class)->matchingSharedPatients($daughter);
        $this->assertTrue($warnings->isEmpty(), 'Nor should her allergies leak as a warning');
    }

    /**
     * Fails closed: with no age on either side there is nothing to tell two
     * people apart, so nothing is shared. Costs history for chambers that never
     * record an age — the right direction to be wrong about a wrong-patient
     * hazard.
     */
    public function test_no_age_on_either_side_does_not_link(): void
    {
        tenancy()->initialize($this->tenantA);
        $patientA = Patient::create([
            'name' => 'Fatima Rahman',
            'phone' => '01711112222',
            'share_clinical_history' => true,
        ]);
        $this->seedCompletedVisit($patientA, $this->sessionA, $this->doctorA, 'SECRETNOTE', 'NAPA');
        tenancy()->end();

        tenancy()->initialize($this->tenantB);
        $patientB = Patient::create([
            'name' => 'Fatima Rahman',
            'phone' => '01711112222',
            'share_clinical_history' => true,
        ]);

        $shared = app(CrossTenantClinicalHistoryService::class)->sharedVisitsFor($patientB, $this->doctorB->id);

        $this->assertTrue($shared->isEmpty());
    }

    /**
     * `displayAge()` ages a stored number forward from `age_recorded_at`, so the
     * same person written down at two chambers months apart can round a year
     * apart. That must still be one person.
     */
    public function test_a_year_of_age_drift_still_links(): void
    {
        tenancy()->initialize($this->tenantA);
        $patientA = Patient::create([
            'name' => 'Fatima Rahman',
            'phone' => '01711112222',
            'share_clinical_history' => true,
            'age' => 34,
            'age_recorded_at' => today()->subMonths(11),
        ]);
        $this->seedCompletedVisit($patientA, $this->sessionA, $this->doctorA, 'Drifted note', 'NAPA');
        tenancy()->end();

        tenancy()->initialize($this->tenantB);
        $patientB = Patient::create([
            'name' => 'Fatima Rahman',
            'phone' => '01711112222',
            'share_clinical_history' => true,
            'age' => 35,
            'age_recorded_at' => today(),
        ]);

        $shared = app(CrossTenantClinicalHistoryService::class)->sharedVisitsFor($patientB, $this->doctorB->id);

        $this->assertCount(1, $shared, 'A year of drift is the same person, not a different one');
    }

    /**
     * A recorded sex that disagrees is a different person even at the same age.
     */
    public function test_conflicting_sex_does_not_link(): void
    {
        tenancy()->initialize($this->tenantA);
        $patientA = Patient::create([
            'name' => 'Shanto Islam',
            'phone' => '01711113333',
            'share_clinical_history' => true,
            'age' => 30,
            'age_recorded_at' => today(),
            'sex' => 'male',
        ]);
        $this->seedCompletedVisit($patientA, $this->sessionA, $this->doctorA, 'SECRETSEXNOTE', 'NAPA');
        tenancy()->end();

        tenancy()->initialize($this->tenantB);
        $patientB = Patient::create([
            'name' => 'Shanto Islam',
            'phone' => '01711113333',
            'share_clinical_history' => true,
            'age' => 30,
            'age_recorded_at' => today(),
            'sex' => 'female',
        ]);

        $shared = app(CrossTenantClinicalHistoryService::class)->sharedVisitsFor($patientB, $this->doctorB->id);

        $this->assertTrue($shared->isEmpty());
    }

    /**
     * The NID path is unchanged — it identifies the person on its own, without
     * needing an age.
     */
    public function test_matching_nid_still_links_without_age(): void
    {
        tenancy()->initialize($this->tenantA);
        $patientA = Patient::create([
            'name' => 'Fatima Rahman',
            'phone' => '01711114444',
            'nid' => '1234567890',
            'share_clinical_history' => true,
        ]);
        $this->seedCompletedVisit($patientA, $this->sessionA, $this->doctorA, 'NID linked note', 'NAPA');
        tenancy()->end();

        tenancy()->initialize($this->tenantB);
        $patientB = Patient::create([
            // Different phone and spelling on purpose: the NID carries it.
            'name' => 'Fatema Rahman',
            'phone' => '01799998888',
            'nid' => '1234567890',
            'share_clinical_history' => true,
        ]);

        $shared = app(CrossTenantClinicalHistoryService::class)->sharedVisitsFor($patientB, $this->doctorB->id);

        $this->assertCount(1, $shared);
        $this->assertSame('NID linked note', $shared->first()->visitRecord?->clinical_notes);
    }

    /**
     * Shared rows are another chamber's, blanked of media for display. Consult
     * Screen merges them into the same collection as this chamber's own
     * records, so a stray `->save()` would write those blanks back and destroy
     * the real voice/photo paths at source. The model refuses instead.
     */
    public function test_a_shared_visit_from_another_chamber_cannot_be_saved(): void
    {
        tenancy()->initialize($this->tenantA);
        $patientA = Patient::create([
            'name' => 'Fatima Rahman',
            'phone' => '01711112222',
            'share_clinical_history' => true,
            'age' => 34,
            'age_recorded_at' => today(),
        ]);
        $visit = $this->seedCompletedVisit($patientA, $this->sessionA, $this->doctorA, 'Note', 'NAPA');
        tenancy()->end();

        tenancy()->initialize($this->tenantB);
        $patientB = Patient::create([
            'name' => 'Fatima Rahman',
            'phone' => '01711112222',
            'share_clinical_history' => true,
            'age' => 34,
            'age_recorded_at' => today(),
        ]);

        $shared = app(CrossTenantClinicalHistoryService::class)
            ->sharedVisitRecordsFor($patientB, $this->doctorB->id);

        $this->assertCount(1, $shared);
        $foreign = $shared->first();
        $this->assertNull($foreign->voice_path, 'media is stripped for display');

        $this->expectException(\RuntimeException::class);
        $foreign->save();
    }

    /**
     * The payload is cached for 180s, so the read-only marking has to survive
     * the cache round-trip — otherwise the second doctor to open the screen
     * gets a saveable copy.
     */
    public function test_the_read_only_marking_survives_the_cache(): void
    {
        tenancy()->initialize($this->tenantA);
        $patientA = Patient::create([
            'name' => 'Fatima Rahman',
            'phone' => '01711115555',
            'share_clinical_history' => true,
            'age' => 40,
            'age_recorded_at' => today(),
        ]);
        $this->seedCompletedVisit($patientA, $this->sessionA, $this->doctorA, 'Cached note', 'NAPA');
        tenancy()->end();

        tenancy()->initialize($this->tenantB);
        $patientB = Patient::create([
            'name' => 'Fatima Rahman',
            'phone' => '01711115555',
            'share_clinical_history' => true,
            'age' => 40,
            'age_recorded_at' => today(),
        ]);

        $service = app(CrossTenantClinicalHistoryService::class);
        $service->sharedVisitRecordsFor($patientB, $this->doctorB->id);

        // Second call is served from cache.
        $cached = $service->sharedVisitRecordsFor($patientB, $this->doctorB->id);

        $this->assertCount(1, $cached);
        $this->expectException(\RuntimeException::class);
        $cached->first()->save();
    }

    /**
     * Patients who registered before the consent checkbox existed were opted
     * into cross-chamber sharing by a column default, not by an answer. The
     * backfill has to switch them off without touching anyone who has since
     * been asked.
     */
    public function test_the_backfill_only_clears_patients_who_were_never_asked(): void
    {
        tenancy()->initialize($this->tenantA);

        $neverAsked = Patient::create([
            'name' => 'Old Patient',
            'phone' => '01711116666',
            'share_clinical_history' => true,
        ]);
        // Predates the consent checkbox.
        DB::table('patients')->where('id', $neverAsked->id)
            ->update(['created_at' => '2026-08-01 09:00:00']);

        $answeredYes = Patient::create([
            'name' => 'New Patient',
            'phone' => '01711117777',
            'share_clinical_history' => true,
        ]);
        DB::table('patients')->where('id', $answeredYes->id)
            ->update(['created_at' => '2026-08-14 09:00:00']);

        $migration = require database_path(
            'migrations/2026_08_13_235900_reset_share_clinical_history_for_pre_consent_patients.php'
        );
        $migration->up();

        $this->assertFalse(
            (bool) $neverAsked->fresh()->share_clinical_history,
            'Someone who never saw the checkbox must not be sharing',
        );
        $this->assertTrue(
            (bool) $answeredYes->fresh()->share_clinical_history,
            'Someone who answered yes keeps their answer',
        );

        tenancy()->end();
    }

    private function seedCompletedVisit(
        Patient $patient,
        ScheduleSession $session,
        User $doctor,
        string $notes,
        string $brand,
    ): void {
        $booking = Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $session->id,
            'booking_date' => Carbon::yesterday()->toDateString(),
            'patient_id' => $patient->id,
            'patient_name' => $patient->name,
            'patient_phone' => $patient->phone,
            'serial_number' => 1,
            'status' => 'completed',
            'completed_at' => now()->subDay(),
        ]);

        $visit = VisitRecord::create([
            'booking_id' => $booking->id,
            'patient_id' => $patient->id,
            'recorded_by' => $doctor->id,
            'clinical_notes' => $notes,
            'voice_path' => 'visit-audio/secret.webm',
            'photo_path' => 'visit-photos/secret.jpg',
            'report_photo_paths' => ['visit-reports/secret.jpg'],
            'recorded_at' => now()->subDay(),
        ]);

        $prescription = Prescription::create([
            'visit_record_id' => $visit->id,
            'patient_id' => $patient->id,
            'prescribed_by' => $doctor->id,
        ]);

        PrescriptionItem::create([
            'prescription_id' => $prescription->id,
            'medicine_name' => $brand,
            'dose' => '500 mg',
            'frequency' => '1+0+1',
            'duration' => '5 days',
            'sort_order' => 0,
        ]);
    }
}
