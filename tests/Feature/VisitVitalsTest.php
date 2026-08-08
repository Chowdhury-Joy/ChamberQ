<?php

namespace Tests\Feature;

use App\Filament\TenantAdmin\Support\VisitNotesFormSchema;
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
use App\Services\VisitRecordService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class VisitVitalsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $doctor;

    private User $staff;

    private Doctor $doctorProfile;

    private Booking $booking;

    private Patient $patient;

    private Condition $condition;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['id' => 'vitals', 'plan_tier' => 'solo']);
        Domain::create(['domain' => 'vitals.localhost', 'tenant_id' => $this->tenant->id]);

        tenancy()->initialize($this->tenant);

        $this->doctor = User::create([
            'name' => 'Dr Vitals',
            'email' => 'doc@vitals.test',
            'password' => Hash::make('secret'),
            'role' => User::ROLE_DOCTOR,
            'tenant_id' => $this->tenant->id,
        ]);

        $this->staff = User::create([
            'name' => 'Staff Vitals',
            'email' => 'staff@vitals.test',
            'password' => Hash::make('secret'),
            'role' => User::ROLE_STAFF,
            'tenant_id' => $this->tenant->id,
        ]);

        $chamber = Chamber::create([
            'name' => 'Main Chamber',
            'address' => 'SECRETCHAMBERADDRESS',
            'contact' => '0299999999',
        ]);

        $this->doctorProfile = Doctor::create([
            'name' => 'Dr Vitals',
            'qualifications' => 'MBBS',
            'registration_number' => 'A-62681',
            'user_id' => $this->doctor->id,
            'staff_may_enter_prescriptions' => true,
        ]);

        $session = ScheduleSession::create([
            'chamber_id' => $chamber->id,
            'doctor_id' => $this->doctorProfile->id,
            'day_of_week' => Carbon::today()->dayOfWeek,
            'session_name' => 'Evening',
            'start_time' => '18:00',
            'end_time' => '21:00',
            'slot_cap' => 20,
        ]);

        $this->patient = Patient::create([
            'name' => 'Mrs Gouri',
            'phone' => '01712345688',
            'age' => 52,
            'age_recorded_at' => today(),
            'sex' => 'female',
        ]);

        $this->booking = Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $session->id,
            'booking_date' => today(),
            'patient_id' => $this->patient->id,
            'patient_name' => $this->patient->name,
            'patient_phone' => $this->patient->phone,
            'serial_number' => 1,
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $this->condition = Condition::create([
            'code' => 'SLD-GY-001',
            'name' => 'SECRETDIAGNOSISFIBROID',
            'aliases' => [],
            'category' => 'Gynae',
        ]);

        tenancy()->end();
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    public function test_doctor_can_save_vitals_and_clinical_notes(): void
    {
        tenancy()->initialize($this->tenant);

        $record = app(VisitRecordService::class)->saveForCompletedBooking($this->booking->fresh(), $this->doctor, [
            'condition_id' => $this->condition->id,
            'weight_kg' => 58.5,
            'bp_systolic' => 170,
            'bp_diastolic' => 100,
            'clinical_notes' => 'Diagnosed fibroid uterus. P/A/E: NAD.',
            'tests_advised' => 'SECRETTESTSADVISED',
            'advice' => 'Refer to cardiologist',
            'prescription_items' => [
                [
                    'medicine_name' => 'Osartil',
                    'dose' => '50 mg',
                    'frequency' => '0+0+1',
                    'duration' => '1 month',
                ],
            ],
        ]);

        $this->assertNotNull($record);
        $this->assertSame(58.5, $record->weight_kg);
        $this->assertSame(170, $record->bp_systolic);
        $this->assertSame(100, $record->bp_diastolic);
        $this->assertSame('Diagnosed fibroid uterus. P/A/E: NAD.', $record->clinical_notes);
        $this->assertSame('58.5 kg', $record->weightLabel());
        $this->assertSame('170/100', $record->bloodPressureLabel());
        $this->assertTrue($record->hasClinicalContent());
    }

    public function test_weight_alone_or_clinical_notes_alone_count_as_clinical_content(): void
    {
        tenancy()->initialize($this->tenant);

        $weightOnly = app(VisitRecordService::class)->saveForCompletedBooking($this->booking->fresh(), $this->doctor, [
            'weight_kg' => 62,
        ]);

        $this->assertNotNull($weightOnly);
        $this->assertTrue($weightOnly->hasClinicalContent());
        $this->assertSame('62 kg', $weightOnly->weightLabel());
        $this->assertNull($weightOnly->bloodPressureLabel());

        $this->booking->visitRecord?->delete();

        $notesOnly = app(VisitRecordService::class)->saveForCompletedBooking($this->booking->fresh(), $this->doctor, [
            'clinical_notes' => 'C/C: irregular bleeding',
        ]);

        $this->assertNotNull($notesOnly);
        $this->assertTrue($notesOnly->hasClinicalContent());
    }

    public function test_half_filled_blood_pressure_is_rejected(): void
    {
        tenancy()->initialize($this->tenant);

        $this->expectException(ValidationException::class);

        app(VisitRecordService::class)->saveForCompletedBooking($this->booking->fresh(), $this->doctor, [
            'bp_systolic' => 170,
        ]);
    }

    public function test_absurd_blood_pressure_is_rejected(): void
    {
        tenancy()->initialize($this->tenant);

        $this->expectException(ValidationException::class);

        VisitNotesFormSchema::normalizeVitals([
            'bp_systolic' => 400,
            'bp_diastolic' => 100,
        ]);
    }

    public function test_staff_cannot_write_vitals_or_clinical_notes(): void
    {
        tenancy()->initialize($this->tenant);

        $record = app(VisitRecordService::class)->saveStaffEnteredPrescription(
            $this->booking->fresh(),
            $this->staff,
            [
                'prescription_items' => [
                    [
                        'medicine_name' => 'NAPA',
                        'dose' => '500 mg',
                        'frequency' => '1+0+1',
                        'duration' => '3 days',
                    ],
                ],
                'weight_kg' => 70,
                'bp_systolic' => 140,
                'bp_diastolic' => 90,
                'clinical_notes' => 'Snuck in',
                'condition_id' => $this->condition->id,
            ],
        );

        $this->assertNotNull($record);
        $this->assertNull($record->weight_kg);
        $this->assertNull($record->bp_systolic);
        $this->assertNull($record->bp_diastolic);
        $this->assertNull($record->clinical_notes);
        $this->assertNull($record->condition_id);
        $this->assertSame('NAPA', $record->prescription->items->first()->medicine_name);
    }

    public function test_doctor_print_shows_vitals_diagnosis_notes_and_tests(): void
    {
        tenancy()->initialize($this->tenant);

        $visit = $this->seedVisitWithPrescription();

        $url = 'http://vitals.localhost/prescriptions/'.$visit->prescription->id.'/print';

        $this->actingAs($this->doctor)->get($url)
            ->assertOk()
            ->assertSee('170/100')
            ->assertSee('58.5 kg')
            ->assertSee('SECRETDIAGNOSISFIBROID')
            ->assertSee('Diagnosed fibroid uterus. P/A/E: NAD.')
            ->assertSee('SECRETTESTSADVISED')
            ->assertSee('OSARTIL');
    }

    public function test_patient_share_shows_vitals_but_not_diagnosis_notes_or_tests(): void
    {
        tenancy()->initialize($this->tenant);

        $visit = $this->seedVisitWithPrescription();
        $url = $visit->prescription->shareUrl();
        tenancy()->end();

        $this->get($url)
            ->assertOk()
            ->assertSee('170/100')
            ->assertSee('58.5 kg')
            ->assertSee('OSARTIL')
            ->assertDontSee('SECRETDIAGNOSISFIBROID')
            ->assertDontSee('Diagnosed fibroid uterus. P/A/E: NAD.')
            ->assertDontSee('SECRETTESTSADVISED')
            ->assertDontSee('SECRETCHAMBERADDRESS');
    }

    private function seedVisitWithPrescription(): VisitRecord
    {
        $visit = VisitRecord::create([
            'tenant_id' => $this->tenant->id,
            'booking_id' => $this->booking->id,
            'patient_id' => $this->patient->id,
            'recorded_by' => $this->doctor->id,
            'condition_id' => $this->condition->id,
            'weight_kg' => 58.5,
            'bp_systolic' => 170,
            'bp_diastolic' => 100,
            'clinical_notes' => 'Diagnosed fibroid uterus. P/A/E: NAD.',
            'tests_advised' => 'SECRETTESTSADVISED',
            'advice' => 'Refer to cardiologist',
            'recorded_at' => now(),
        ]);

        $prescription = Prescription::create([
            'tenant_id' => $this->tenant->id,
            'visit_record_id' => $visit->id,
            'patient_id' => $this->patient->id,
            'prescribed_by' => $this->doctor->id,
            'advice' => 'Refer to cardiologist',
        ]);

        PrescriptionItem::create([
            'prescription_id' => $prescription->id,
            'medicine_name' => 'OSARTIL',
            'dose' => '50 mg',
            'frequency' => '0+0+1',
            'duration' => '1 month',
            'sort_order' => 0,
        ]);

        return $visit->fresh(['prescription.items', 'condition']);
    }
}
