<?php

namespace Tests\Feature;

use App\Http\Controllers\BookingController;
use App\Models\Booking;
use App\Models\Chamber;
use App\Models\Condition;
use App\Models\Doctor;
use App\Models\Domain;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\ScheduleSession;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VisitRecord;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Portal phone lookup lists up to two full prescriptions as a backup when
 * staff forget to send the SMS/WhatsApp link.
 */
class PortalPrescriptionTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $doctor;

    private string $phone = '01712345699';

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['id' => 'portal-rx', 'plan_tier' => 'solo']);
        Domain::create(['domain' => 'portal-rx.localhost', 'tenant_id' => $this->tenant->id]);

        tenancy()->initialize($this->tenant);

        $this->doctor = User::create([
            'name' => 'Dr Portal',
            'email' => 'doc@portalrx.test',
            'password' => Hash::make('secret'),
            'role' => User::ROLE_DOCTOR,
            'tenant_id' => $this->tenant->id,
        ]);

        tenancy()->end();
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    private function makePrescription(string $medicine, ?string $diagnosis = null, ?Carbon $createdAt = null): Prescription
    {
        tenancy()->initialize($this->tenant);

        $chamber = Chamber::query()->first() ?? Chamber::create([
            'name' => 'Portal Chamber',
            'address' => '12 Test Road',
            'contact' => '01811111111',
        ]);
        $doctorProfile = Doctor::query()->first() ?? Doctor::create([
            'name' => 'Dr Portal',
            'qualifications' => 'MBBS',
            'registration_number' => 'A-100',
        ]);
        $session = ScheduleSession::query()->first() ?? ScheduleSession::create([
            'chamber_id' => $chamber->id,
            'doctor_id' => $doctorProfile->id,
            'day_of_week' => Carbon::today()->dayOfWeek,
            'session_name' => 'Morning',
            'start_time' => '09:00',
            'end_time' => '12:00',
            'slot_cap' => 20,
        ]);

        $patient = Patient::query()->where('phone', $this->phone)->first() ?? Patient::create([
            'name' => 'Portal Patient',
            'phone' => $this->phone,
            'age' => 40,
            'age_recorded_at' => today(),
            'sex' => 'female',
        ]);

        $booking = Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $session->id,
            'booking_date' => today(),
            'patient_id' => $patient->id,
            'patient_name' => $patient->name,
            'patient_phone' => $patient->phone,
            'serial_number' => Booking::query()->count() + 1,
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $conditionId = null;
        if ($diagnosis !== null) {
            $condition = Condition::create([
                'code' => 'PRX-'.substr(md5($diagnosis), 0, 8),
                'name' => $diagnosis,
                'aliases' => [],
                'category' => 'Test',
            ]);
            $conditionId = $condition->id;
        }

        $visit = VisitRecord::create([
            'tenant_id' => $this->tenant->id,
            'booking_id' => $booking->id,
            'patient_id' => $patient->id,
            'recorded_by' => $this->doctor->id,
            'condition_id' => $conditionId,
            'chief_complaint' => 'SECRETCHIEFCOMPLAINT',
            'voice_path' => 'visit-audio/portal-rx/secret.webm',
            'voice_transcript' => 'Secret transcript must stay off',
            'photo_path' => 'visit-photos/portal-rx/secret.jpg',
            'recorded_at' => now(),
        ]);

        $prescription = Prescription::create([
            'tenant_id' => $this->tenant->id,
            'visit_record_id' => $visit->id,
            'patient_id' => $patient->id,
            'prescribed_by' => $this->doctor->id,
            'advice' => 'Take with food',
        ]);

        if ($createdAt !== null) {
            $prescription->forceFill([
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ])->save();
        }

        PrescriptionItem::create([
            'prescription_id' => $prescription->id,
            'medicine_name' => $medicine,
            'generic_name' => 'Test Generic',
            'dose' => '10 mg',
            'frequency' => '0+0+1',
            'duration' => '7 days',
            'sort_order' => 1,
        ]);

        tenancy()->end();

        return $prescription->fresh();
    }

    public function test_portal_lists_at_most_two_recent_prescriptions(): void
    {
        $oldest = $this->makePrescription('OLDEST', 'Old Diagnosis', now()->subDays(3));
        $second = $this->makePrescription('SECOND', 'Second Diagnosis', now()->subDays(2));
        $newest = $this->makePrescription('NEWEST', 'Newest Diagnosis', now()->subDay());

        $this->get('http://portal-rx.localhost/portal?phone='.$this->phone)
            ->assertOk()
            ->assertSee('Your prescriptions', false)
            ->assertSee('View prescription', false)
            ->assertSee('/portal/prescriptions/'.$newest->id, false)
            ->assertSee('/portal/prescriptions/'.$second->id, false)
            ->assertDontSee('/portal/prescriptions/'.$oldest->id, false);

        $this->assertSame(2, BookingController::PORTAL_PRESCRIPTION_LIMIT);
    }

    public function test_portal_prescription_view_requires_matching_phone(): void
    {
        $prescription = $this->makePrescription('NAPA', 'SECRETDIAGNOSISNAME');

        $this->get('http://portal-rx.localhost/portal/prescriptions/'.$prescription->id.'?phone=01700000000')
            ->assertNotFound();

        $this->get('http://portal-rx.localhost/portal/prescriptions/'.$prescription->id)
            ->assertNotFound();

        $this->get('http://portal-rx.localhost/portal/prescriptions/'.$prescription->id.'?phone='.$this->phone)
            ->assertOk()
            ->assertSee('NAPA')
            ->assertSee('SECRETDIAGNOSISNAME')
            ->assertSee('SECRETCHIEFCOMPLAINT')
            ->assertSee('Portal Chamber')
            ->assertSee('12 Test Road')
            ->assertDontSee('Secret transcript must stay off')
            ->assertDontSee('visit-audio')
            ->assertDontSee('visit-photos');
    }

    public function test_portal_list_does_not_expose_visit_media_paths(): void
    {
        Storage::disk('local')->put('visit-audio/portal-rx/secret.webm', 'secret');
        $this->makePrescription('SERGEL', 'Gastritis');

        $this->get('http://portal-rx.localhost/portal?phone='.$this->phone)
            ->assertOk()
            ->assertDontSee('Secret transcript must stay off')
            ->assertDontSee('visit-audio')
            ->assertDontSee('visit-photos');
    }
}
