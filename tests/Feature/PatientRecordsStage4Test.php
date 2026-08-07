<?php

namespace Tests\Feature;

use App\Filament\TenantAdmin\Pages\ConsultScreen;
use App\Models\Booking;
use App\Models\Chamber;
use App\Models\Condition;
use App\Models\Doctor;
use App\Models\Domain;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\ScheduleSession;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VisitRecord;
use App\Models\LiveSession;
use App\Services\LiveSessionService;
use App\Services\VisitRecordService;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class PatientRecordsStage4Test extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $doctor;

    private User $staff;

    private Patient $patient;

    private Booking $booking;

    private Condition $condition;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['id' => 'stage4-visits', 'plan_tier' => 'solo']);
        Domain::create(['domain' => 'stage4-visits.localhost', 'tenant_id' => $this->tenant->id]);

        tenancy()->initialize($this->tenant);

        $this->doctor = User::create([
            'name' => 'Dr Visit',
            'email' => 'doc@stage4.test',
            'password' => Hash::make('secret'),
            'role' => User::ROLE_DOCTOR,
            'tenant_id' => $this->tenant->id,
        ]);

        $this->staff = User::create([
            'name' => 'Staff',
            'email' => 'staff@stage4.test',
            'password' => Hash::make('secret'),
            'role' => User::ROLE_STAFF,
            'tenant_id' => $this->tenant->id,
        ]);

        $chamber = Chamber::create(['name' => 'Main', 'address' => 'Dhaka']);
        $doctorProfile = Doctor::create([
            'name' => 'Dr Visit',
            'qualifications' => 'MBBS',
            'registration_number' => 'A-12345',
        ]);
        $session = ScheduleSession::create([
            'chamber_id' => $chamber->id,
            'doctor_id' => $doctorProfile->id,
            'day_of_week' => Carbon::today()->dayOfWeek,
            'session_name' => 'Evening',
            'start_time' => '18:00',
            'end_time' => '21:00',
            'slot_cap' => 20,
        ]);

        $this->patient = Patient::create([
            'name' => 'Karim Uddin',
            'phone' => '01712345678',
            'age' => 42,
            'age_recorded_at' => today(),
            'sex' => 'male',
        ]);

        $this->booking = Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $session->id,
            'booking_date' => today(),
            'patient_id' => $this->patient->id,
            'patient_name' => $this->patient->name,
            'patient_phone' => $this->patient->phone,
            'serial_number' => 1,
            'status' => 'in_chamber',
            'in_chamber_at' => now(),
        ]);

        $this->condition = Condition::create([
            'code' => 'SLD-GI-001',
            'name' => 'Gastritis / Acid peptic disease',
            'aliases' => ['gastric'],
            'category' => 'Gastrointestinal',
        ]);

        tenancy()->end();
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    public function test_visit_record_saves_coded_diagnosis(): void
    {
        tenancy()->initialize($this->tenant);

        $this->booking->update(['status' => 'completed', 'completed_at' => now()]);

        $record = app(VisitRecordService::class)->saveForCompletedBooking($this->booking->fresh(), $this->doctor, [
            'condition_id' => $this->condition->id,
            'advice' => 'Avoid spicy food',
        ]);

        $this->assertNotNull($record);
        $this->assertSame($this->condition->id, $record->condition_id);
        $this->assertNull($record->diagnosis_uncoded);
        $this->assertSame('Avoid spicy food', $record->advice);
        $this->assertDatabaseHas('condition_usages', [
            'user_id' => $this->doctor->id,
            'condition_id' => $this->condition->id,
        ]);
    }

    public function test_visit_record_saves_uncoded_diagnosis(): void
    {
        tenancy()->initialize($this->tenant);

        $record = app(VisitRecordService::class)->saveForCompletedBooking($this->booking, $this->doctor, [
            'diagnosis_free_text' => 'Rare skin rash',
            'tests_advised' => 'CBC',
        ]);

        $this->assertNotNull($record);
        $this->assertNull($record->condition_id);
        $this->assertSame('Rare skin rash', $record->diagnosis_uncoded);
        $this->assertSame('CBC', $record->tests_advised);
    }

    public function test_mark_completed_without_notes_still_works(): void
    {
        tenancy()->initialize($this->tenant);

        app(LiveSessionService::class)->completeBooking($this->booking->fresh());

        $this->booking->refresh();
        $this->assertSame('completed', $this->booking->status);
        $this->assertNull(VisitRecord::query()->where('booking_id', $this->booking->id)->first());
    }

    public function test_doctor_can_view_prescription_print_staff_cannot(): void
    {
        tenancy()->initialize($this->tenant);

        $visitRecord = VisitRecord::create([
            'tenant_id' => $this->tenant->id,
            'booking_id' => $this->booking->id,
            'patient_id' => $this->patient->id,
            'recorded_by' => $this->doctor->id,
            'condition_id' => $this->condition->id,
            'recorded_at' => now(),
        ]);

        $prescription = Prescription::create([
            'tenant_id' => $this->tenant->id,
            'visit_record_id' => $visitRecord->id,
            'patient_id' => $this->patient->id,
            'prescribed_by' => $this->doctor->id,
            'advice' => 'Rest well',
        ]);

        $url = 'http://stage4-visits.localhost/prescriptions/' . $prescription->id . '/print';

        $this->actingAs($this->doctor)
            ->get($url)
            ->assertOk()
            ->assertSee('Karim Uddin')
            ->assertSee('Rest well')
            ->assertSee('A-12345');

        $this->actingAs($this->staff)->get($url)->assertForbidden();

        $this->get($url)->assertForbidden();
    }

    public function test_public_ticket_does_not_expose_visit_notes(): void
    {
        tenancy()->initialize($this->tenant);

        $this->booking->update(['status' => 'completed', 'completed_at' => now()]);

        VisitRecord::create([
            'tenant_id' => $this->tenant->id,
            'booking_id' => $this->booking->id,
            'patient_id' => $this->patient->id,
            'recorded_by' => $this->doctor->id,
            'diagnosis_uncoded' => 'Secret diagnosis',
            'advice' => 'Secret advice',
            'recorded_at' => now(),
        ]);

        $ticketUrl = 'http://stage4-visits.localhost/bookings/' . $this->booking->id;

        $this->get($ticketUrl)
            ->assertOk()
            ->assertDontSee('Secret diagnosis')
            ->assertDontSee('Secret advice');
    }

    public function test_consult_screen_shows_notes_exist_state(): void
    {
        tenancy()->initialize($this->tenant);

        $priorBooking = Booking::create([
            'bookable_type' => $this->booking->bookable_type,
            'bookable_id' => $this->booking->bookable_id,
            'booking_date' => today()->subWeek(),
            'patient_id' => $this->patient->id,
            'patient_name' => $this->patient->name,
            'patient_phone' => $this->patient->phone,
            'serial_number' => 9,
            'status' => 'completed',
            'completed_at' => now()->subWeek(),
        ]);

        VisitRecord::create([
            'tenant_id' => $this->tenant->id,
            'booking_id' => $priorBooking->id,
            'patient_id' => $this->patient->id,
            'recorded_by' => $this->doctor->id,
            'condition_id' => $this->condition->id,
            'advice' => 'Take omeprazole before meals',
            'recorded_at' => now()->subWeek(),
        ]);

        LiveSession::create([
            'schedule_session_id' => $this->booking->bookable_id,
            'session_date' => Carbon::today(),
            'status' => 'active',
            'started_at' => now(),
            'current_booking_id' => $this->booking->id,
        ]);

        $this->assertSame('visits_with_notes', $this->patient->fresh()->consultHistoryState());

        Filament::setCurrentPanel(Filament::getPanel('tenantAdmin'));
        $this->actingAs($this->doctor);

        Livewire::test(ConsultScreen::class)
            ->assertSee('Gastritis / Acid peptic disease')
            ->assertSee('Take omeprazole before meals');
    }

    public function test_staff_cannot_record_visit_notes(): void
    {
        tenancy()->initialize($this->tenant);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        app(VisitRecordService::class)->saveForCompletedBooking($this->booking, $this->staff, [
            'diagnosis_free_text' => 'Should fail',
        ]);
    }
}
