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
        ]);
        $this->seedCompletedVisit($patientA, $this->sessionA, $this->doctorA, 'Diabetes follow-up', 'NAPA');
        tenancy()->end();

        tenancy()->initialize($this->tenantB);
        $patientB = Patient::create([
            'name' => 'Fatima Rahman',
            'phone' => '01711112222',
            'share_clinical_history' => true,
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
