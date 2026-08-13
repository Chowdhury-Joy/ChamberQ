<?php

namespace Tests\Feature;

use App\Filament\TenantAdmin\Pages\DailyRoster;
use App\Filament\TenantAdmin\Support\VisitNotesFormSchema;
use App\Models\Booking;
use App\Models\Chamber;
use App\Models\Condition;
use App\Models\Doctor;
use App\Models\Domain;
use App\Models\Medicine;
use App\Models\Patient;
use App\Models\ScheduleSession;
use App\Models\Tenant;
use App\Models\User;
use App\Services\VisitRecordService;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Doctors who write on paper and let staff key it in afterwards.
 *
 * The delegation is per-doctor and off by default, and staff never gain access
 * to diagnosis, advice, voice notes or visit history through it.
 */
class StaffPrescriptionEntryTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $doctorUser;

    private User $staff;

    private Doctor $doctorProfile;

    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['id' => 'staff-scripts', 'plan_tier' => 'solo']);
        Domain::create(['domain' => 'staff-scripts.localhost', 'tenant_id' => $this->tenant->id]);

        tenancy()->initialize($this->tenant);

        $this->doctorUser = User::create([
            'name' => 'Dr Shamim',
            'email' => 'doc@scripts.test',
            'password' => Hash::make('secret'),
            'role' => User::ROLE_DOCTOR,
            'tenant_id' => $this->tenant->id,
        ]);

        $this->staff = User::create([
            'name' => 'Rina',
            'email' => 'staff@scripts.test',
            'password' => Hash::make('secret'),
            'role' => User::ROLE_STAFF,
            'tenant_id' => $this->tenant->id,
        ]);

        $chamber = Chamber::create(['name' => 'Main', 'address' => 'Dhaka']);

        $this->doctorProfile = Doctor::create([
            'name' => 'Dr Shamim',
            'practice_type' => Doctor::PRACTICE_GENERAL,
            'qualifications' => 'MBBS',
            'registration_number' => 'A-99999',
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

        $patient = Patient::create([
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
            'patient_id' => $patient->id,
            'patient_name' => $patient->name,
            'patient_phone' => $patient->phone,
            'serial_number' => 1,
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        Medicine::create([
            'brand_name' => 'NAPA',
            'generic_name' => 'Paracetamol',
            'aliases' => [],
            'category' => 'Analgesic',
            'default_strength' => '500 mg',
            'practice_types' => [Doctor::PRACTICE_GENERAL],
        ]);

        tenancy()->end();
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    /** @return array<string, mixed> */
    private function paperScript(): array
    {
        return [
            'prescription_items' => [
                [
                    'medicine_name' => 'NAPA',
                    'generic_name' => 'Paracetamol',
                    'dose' => '500 mg',
                    'frequency' => '1+1+1',
                    'duration' => '5 days',
                ],
            ],
            'follow_up_relative' => '1_week',
        ];
    }

    public function test_delegation_is_off_by_default(): void
    {
        tenancy()->initialize($this->tenant);

        $this->assertFalse($this->doctorProfile->allowsStaffPrescriptionEntry());
        $this->assertFalse($this->staff->canEnterPrescriptionFor($this->doctorProfile));
    }

    public function test_staff_cannot_enter_prescription_when_delegation_is_off(): void
    {
        tenancy()->initialize($this->tenant);

        $this->expectException(HttpException::class);

        app(VisitRecordService::class)->saveStaffEnteredPrescription(
            $this->booking,
            $this->staff,
            $this->paperScript(),
        );
    }

    public function test_staff_can_enter_prescription_once_doctor_permits(): void
    {
        tenancy()->initialize($this->tenant);

        $this->doctorProfile->update(['staff_may_enter_prescriptions' => true]);

        $record = app(VisitRecordService::class)->saveStaffEnteredPrescription(
            $this->booking->fresh(),
            $this->staff,
            $this->paperScript(),
        );

        $this->assertNotNull($record);
        $this->assertSame($this->staff->id, $record->recorded_by);
        $this->assertSame('NAPA', $record->prescription->items->first()->medicine_name);
        $this->assertSame('1+1+1', $record->prescription->items->first()->frequency);
        $this->assertSame(
            now()->addWeek()->toDateString(),
            $record->follow_up_date->toDateString(),
        );
    }

    public function test_clinical_fields_submitted_by_staff_are_discarded(): void
    {
        tenancy()->initialize($this->tenant);

        $this->doctorProfile->update(['staff_may_enter_prescriptions' => true]);

        $condition = Condition::create([
            'code' => 'SLD-GI-001',
            'name' => 'Gastritis',
            'aliases' => ['gastric'],
            'category' => 'Gastrointestinal',
        ]);

        $record = app(VisitRecordService::class)->saveStaffEnteredPrescription(
            $this->booking->fresh(),
            $this->staff,
            $this->paperScript() + [
                'condition_id' => $condition->id,
                'diagnosis_free_text' => 'Snuck in',
                'advice' => 'Snuck in',
                'tests_advised' => 'Snuck in',
                'reports_seen' => 'Snuck in',
                'voice_transcript' => 'Snuck in',
            ],
        );

        $this->assertNotNull($record);
        $this->assertNull($record->condition_id);
        $this->assertNull($record->diagnosis_uncoded);
        $this->assertNull($record->advice);
        $this->assertNull($record->tests_advised);
        $this->assertNull($record->reports_seen);
        $this->assertNull($record->voice_transcript);
        $this->assertNull($record->report_photo_paths);
    }

    public function test_staff_can_attach_report_photos_without_writing_clinical_notes(): void
    {
        tenancy()->initialize($this->tenant);

        $this->doctorProfile->update(['staff_may_enter_prescriptions' => true]);

        $path = 'visit-reports/'.$this->tenant->id.'/cbc.jpg';
        Storage::disk('local')->put($path, 'cbc-bytes');

        $record = app(VisitRecordService::class)->saveStaffEnteredPrescription(
            $this->booking->fresh(),
            $this->staff,
            $this->paperScript() + [
                'report_photos' => [$path],
                'reports_seen' => 'Staff must not write this',
            ],
        );

        $this->assertNotNull($record);
        $this->assertSame([$path], $record->report_photo_paths);
        $this->assertNull($record->reports_seen);
        $this->assertContains('report_photos', VisitNotesFormSchema::STAFF_WRITABLE_FIELDS);
        $this->assertNotContains('reports_seen', VisitNotesFormSchema::STAFF_WRITABLE_FIELDS);
    }

    public function test_staff_entry_keeps_report_photos_the_doctor_already_attached(): void
    {
        tenancy()->initialize($this->tenant);

        $this->doctorProfile->update(['staff_may_enter_prescriptions' => true]);

        $path = 'visit-reports/'.$this->tenant->id.'/xray.jpg';
        Storage::disk('local')->put($path, 'xray-bytes');

        app(VisitRecordService::class)->saveForCompletedBooking(
            $this->booking->fresh(),
            $this->doctorUser,
            [
                'diagnosis_free_text' => 'Viral fever',
                'report_photos' => [$path],
            ],
        );

        app(VisitRecordService::class)->saveStaffEnteredPrescription(
            $this->booking->fresh(),
            $this->staff,
            $this->paperScript(),
        );

        $record = $this->booking->fresh()->visitRecord->fresh();

        $this->assertSame([$path], $record->report_photo_paths);
        $this->assertSame('Viral fever', $record->diagnosis_uncoded);
    }

    public function test_staff_entry_does_not_overwrite_the_doctors_own_notes(): void
    {
        tenancy()->initialize($this->tenant);

        $this->doctorProfile->update(['staff_may_enter_prescriptions' => true]);

        app(VisitRecordService::class)->saveForCompletedBooking(
            $this->booking->fresh(),
            $this->doctorUser,
            ['diagnosis_free_text' => 'Viral fever', 'advice' => 'Rest and fluids'],
        );

        app(VisitRecordService::class)->saveStaffEnteredPrescription(
            $this->booking->fresh(),
            $this->staff,
            $this->paperScript(),
        );

        $record = $this->booking->fresh()->visitRecord->fresh(['prescription.items']);

        $this->assertSame('Viral fever', $record->diagnosis_uncoded);
        $this->assertSame('Rest and fluids', $record->advice);
        $this->assertSame('NAPA', $record->prescription->items->first()->medicine_name);
    }

    public function test_staff_entry_does_not_grant_visit_note_access(): void
    {
        tenancy()->initialize($this->tenant);

        $this->doctorProfile->update(['staff_may_enter_prescriptions' => true]);

        $this->assertFalse($this->staff->canViewVisitNotes());
        $this->assertFalse($this->staff->canRecordVisitNotes());
        $this->assertFalse($this->staff->canViewConsultScreen());
    }

    public function test_roster_hides_the_action_until_the_doctor_permits(): void
    {
        tenancy()->initialize($this->tenant);
        Filament::setCurrentPanel(Filament::getPanel('tenantAdmin'));
        $this->actingAs($this->staff);

        Livewire::test(DailyRoster::class)
            ->assertTableActionHidden('enterPrescription', $this->booking);

        $this->doctorProfile->update(['staff_may_enter_prescriptions' => true]);

        Livewire::test(DailyRoster::class)
            ->assertTableActionVisible('enterPrescription', $this->booking);
    }

    public function test_roster_action_is_hidden_from_the_doctor(): void
    {
        tenancy()->initialize($this->tenant);
        Filament::setCurrentPanel(Filament::getPanel('tenantAdmin'));
        $this->doctorProfile->update(['staff_may_enter_prescriptions' => true]);
        $this->actingAs($this->doctorUser);

        Livewire::test(DailyRoster::class)
            ->assertTableActionHidden('enterPrescription', $this->booking);
    }
}
