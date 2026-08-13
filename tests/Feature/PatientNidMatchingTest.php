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
use App\Support\BdNid;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PatientNidMatchingTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private ScheduleSession $session;

    /**
     * A fixed Monday morning, safely inside the 09:00–12:00 session below.
     * Without this the suite books against the real clock, and
     * BookingService rejects every booking once the app timezone passes noon.
     */
    private const FROZEN_NOW = '2026-01-05 09:00';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse(self::FROZEN_NOW));

        Cache::flush();

        $this->tenant = Tenant::create(['id' => 'nid-match', 'plan_tier' => 'solo', 'name' => 'NID Clinic']);
        tenancy()->initialize($this->tenant);

        User::create([
            'name' => 'Dr Nid',
            'email' => 'nid@test',
            'password' => Hash::make('secret'),
            'role' => User::ROLE_DOCTOR,
            'tenant_id' => $this->tenant->id,
        ]);

        $chamber = Chamber::create(['name' => 'Main', 'address' => 'Dhaka']);
        $profile = Doctor::create(['name' => 'Dr Nid', 'registration_number' => 'N-1']);
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
        Carbon::setTestNow();
        tenancy()->end();
        parent::tearDown();
    }

    public function test_bd_nid_normalizes_and_accepts_ten_or_thirteen_digits(): void
    {
        $this->assertSame('1234567890', BdNid::normalize('123-456-7890'));
        $this->assertTrue(BdNid::isValid('1234567890'));
        $this->assertTrue(BdNid::isValid('1234567890123'));
        $this->assertTrue(BdNid::isValid(null));
        $this->assertTrue(BdNid::isValid(''));
        $this->assertFalse(BdNid::isValid('12345'));
    }

    public function test_booking_without_nid_still_succeeds(): void
    {
        $booking = app(BookingService::class)->createBookingForBookable(
            $this->session,
            Carbon::today()->toDateString(),
            'Fatima Rahman',
            '01711110001',
            [],
            false,
        );

        $this->assertNull($booking->patient->nid);
        $this->assertSame('01711110001', $booking->patient_phone);
    }

    public function test_same_nid_reconnects_patient_after_phone_change(): void
    {
        $first = app(BookingService::class)->createBookingForBookable(
            $this->session,
            Carbon::today()->toDateString(),
            'Fatima Rahman',
            '01711110001',
            [],
            false,
            null,
            false,
            null,
            true,
            '1234567890',
        );

        // Cancel so the same person can book again same day/session under a new phone.
        $first->update(['status' => 'cancelled']);

        $second = app(BookingService::class)->createBookingForBookable(
            $this->session,
            Carbon::today()->toDateString(),
            'Fatima Rahman',
            '01822220002',
            [],
            false,
            null,
            false,
            null,
            true,
            '123-456-7890',
        );

        $this->assertSame($first->patient_id, $second->patient_id);
        $this->assertSame('01822220002', $second->patient->fresh()->phone);
        $this->assertSame('1234567890', $second->patient->nid);
    }

    public function test_invalid_nid_is_rejected_by_booking_api(): void
    {
        $response = $this->postJson('/nid-match/api/bookings', [
            'bookable_type' => 'session',
            'bookable_id' => $this->session->id,
            'booking_date' => Carbon::today()->toDateString(),
            'patient_name' => 'Fatima Rahman',
            'patient_phone' => '01711110003',
            'nid' => '123',
            'share_clinical_history' => '1',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['nid']);
    }

    public function test_cross_chamber_share_matches_by_nid_when_phones_differ(): void
    {
        $tenantB = Tenant::create(['id' => 'nid-match-b', 'plan_tier' => 'solo', 'name' => 'Clinic B']);

        $patientA = Patient::create([
            'name' => 'Fatima Rahman',
            'phone' => '01711110004',
            'nid' => '9876543210',
            'share_clinical_history' => true,
        ]);

        $doctor = User::query()->where('tenant_id', $this->tenant->id)->firstOrFail();
        $booking = Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $this->session->id,
            'booking_date' => Carbon::yesterday()->toDateString(),
            'patient_id' => $patientA->id,
            'patient_name' => $patientA->name,
            'patient_phone' => $patientA->phone,
            'serial_number' => 1,
            'status' => 'completed',
            'completed_at' => now()->subDay(),
        ]);
        $visit = VisitRecord::create([
            'booking_id' => $booking->id,
            'patient_id' => $patientA->id,
            'recorded_by' => $doctor->id,
            'clinical_notes' => 'Shared via NID',
            'voice_path' => 'visit-audio/secret.webm',
            'recorded_at' => now()->subDay(),
        ]);
        $rx = Prescription::create([
            'visit_record_id' => $visit->id,
            'patient_id' => $patientA->id,
            'prescribed_by' => $doctor->id,
        ]);
        PrescriptionItem::create([
            'prescription_id' => $rx->id,
            'medicine_name' => 'NAPA',
            'dose' => '500 mg',
            'sort_order' => 0,
        ]);

        tenancy()->end();
        tenancy()->initialize($tenantB);

        $patientB = Patient::create([
            'name' => 'Fatima Rahman',
            'phone' => '01899990009',
            'nid' => '9876543210',
            'share_clinical_history' => true,
        ]);

        $shared = app(CrossTenantClinicalHistoryService::class)->sharedVisitsFor($patientB);

        $this->assertCount(1, $shared);
        $this->assertSame('Shared via NID', $shared->first()->visitRecord?->clinical_notes);
        $this->assertNull($shared->first()->visitRecord?->voice_path);
    }
}
