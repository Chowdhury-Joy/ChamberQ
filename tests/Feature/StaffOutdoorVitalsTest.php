<?php

namespace Tests\Feature;

use App\Filament\TenantAdmin\Support\DeskActionLayout;
use App\Filament\TenantAdmin\Support\VisitNotesFormSchema;
use App\Models\Booking;
use App\Models\Chamber;
use App\Models\Doctor;
use App\Models\Domain;
use App\Models\Patient;
use App\Models\ScheduleSession;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VisitRecord;
use App\Services\VisitRecordService;
use App\Support\StaffDeskJobs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The prep desk takes the whole on-examination vitals set when the doctor has
 * staff — not just weight and BP, and not only in chambers that happen to run
 * the intervention stations module.
 */
class StaffOutdoorVitalsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private ScheduleSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        // Deliberately a plain chamber: front door and live queue, no stations
        // module. Vitals must still be a desk job here.
        $this->tenant = Tenant::create([
            'id' => 'outdoor-vitals-clinic',
            'plan_tier' => 'clinic',
            'queue_runner' => Tenant::QUEUE_RUNNER_STAFF,
            'feature_flags' => Tenant::featureFlagsWithModules([], [
                Tenant::MODULE_FRONT_DOOR,
                Tenant::MODULE_LIVE_QUEUE,
                Tenant::MODULE_PRESCRIPTION,
            ]),
        ]);
        Domain::create(['domain' => 'outdoor-vitals.localhost', 'tenant_id' => 'outdoor-vitals-clinic']);

        tenancy()->initialize($this->tenant);

        $chamber = Chamber::create(['name' => 'Main']);
        $doctor = Doctor::create(['name' => 'Dr Vitals']);
        $this->session = ScheduleSession::create([
            'chamber_id' => $chamber->id,
            'doctor_id' => $doctor->id,
            'day_of_week' => now()->dayOfWeek,
            'session_name' => 'Morning',
            'start_time' => '09:00',
            'end_time' => '12:00',
            'slot_cap' => 20,
        ]);

        tenancy()->end();
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    private function makeUser(string $email, string $role, ?array $deskJobs = null): User
    {
        return User::create([
            'name' => $email,
            'email' => $email,
            'password' => Hash::make('secret'),
            'role' => $role,
            'tenant_id' => 'outdoor-vitals-clinic',
            'desk_jobs' => $deskJobs,
        ]);
    }

    private function makeWaitingBooking(int $serial = 1): Booking
    {
        $patient = Patient::create(['name' => 'Rahim', 'phone' => '0171000'.str_pad((string) $serial, 4, '0', STR_PAD_LEFT)]);

        return Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $this->session->id,
            'booking_date' => today()->toDateString(),
            'patient_id' => $patient->id,
            'patient_name' => $patient->name,
            'patient_phone' => $patient->phone,
            'serial_number' => $serial,
            'status' => 'waiting',
        ]);
    }

    public function test_prep_staff_record_every_vital_the_doctors_pad_asks_for(): void
    {
        tenancy()->initialize($this->tenant);

        $prep = $this->makeUser('prep@vitals.test', User::ROLE_STAFF, [StaffDeskJobs::JOB_PREP]);
        $booking = $this->makeWaitingBooking();

        $record = app(VisitRecordService::class)->saveStaffVitals($booking, $prep, [
            'weight_kg' => 70.4,
            'bp_systolic' => 150,
            'bp_diastolic' => 95,
            'pulse_bpm' => 88,
            'spo2_percent' => 96,
            'temperature_f' => 101.2,
        ]);

        $this->assertNotNull($record);
        $this->assertSame(70.4, (float) $record->weight_kg);
        $this->assertSame(150, $record->bp_systolic);
        $this->assertSame(95, $record->bp_diastolic);
        $this->assertSame(88, $record->pulse_bpm);
        $this->assertSame(96, $record->spo2_percent);
        $this->assertSame(101.2, (float) $record->temperature_f);
        $this->assertSame($prep->id, $record->vitals_recorded_by);
        $this->assertTrue($record->vitalsTakenAtDesk());

        tenancy()->end();
    }

    public function test_the_staff_form_offers_exactly_the_doctors_vitals_boxes(): void
    {
        $names = array_map(
            fn ($field): string => $field->getName(),
            VisitNotesFormSchema::vitalsFields(),
        );

        $this->assertSame(VisitNotesFormSchema::VITAL_FIELDS, $names);
    }

    public function test_out_of_range_desk_readings_are_dropped_like_the_doctors_are(): void
    {
        tenancy()->initialize($this->tenant);

        $prep = $this->makeUser('prep2@vitals.test', User::ROLE_STAFF, [StaffDeskJobs::JOB_PREP]);
        $booking = $this->makeWaitingBooking(2);

        $record = app(VisitRecordService::class)->saveStaffVitals($booking, $prep, [
            'weight_kg' => 68,
            'temperature_f' => 900,
            'spo2_percent' => 4,
        ]);

        $this->assertNotNull($record);
        $this->assertSame(68.0, (float) $record->weight_kg);
        $this->assertNull($record->temperature_f);
        $this->assertNull($record->spo2_percent);

        tenancy()->end();
    }

    public function test_half_a_blood_pressure_is_refused_at_the_desk(): void
    {
        tenancy()->initialize($this->tenant);

        $prep = $this->makeUser('prep3@vitals.test', User::ROLE_STAFF, [StaffDeskJobs::JOB_PREP]);
        $booking = $this->makeWaitingBooking(3);

        $this->expectException(\InvalidArgumentException::class);

        app(VisitRecordService::class)->saveStaffVitals($booking, $prep, ['bp_systolic' => 150]);

        tenancy()->end();
    }

    public function test_a_pulse_only_reading_clears_the_vitals_needed_prompt(): void
    {
        tenancy()->initialize($this->tenant);

        $prep = $this->makeUser('prep4@vitals.test', User::ROLE_STAFF, [StaffDeskJobs::JOB_PREP]);
        $booking = $this->makeWaitingBooking(4);

        $this->actingAs($prep);
        $this->assertTrue(DeskActionLayout::needsOutdoorVitals($booking));

        app(VisitRecordService::class)->saveStaffVitals($booking, $prep, ['pulse_bpm' => 88]);

        $this->assertFalse(DeskActionLayout::needsOutdoorVitals($booking->fresh()));

        tenancy()->end();
    }

    public function test_vitals_are_a_desk_job_without_the_stations_module(): void
    {
        tenancy()->initialize($this->tenant);

        $this->assertFalse($this->tenant->hasStations());

        $prep = $this->makeUser('prep5@vitals.test', User::ROLE_STAFF, [StaffDeskJobs::JOB_PREP]);
        $money = $this->makeUser('money@vitals.test', User::ROLE_STAFF, [StaffDeskJobs::JOB_MONEY]);
        $booking = $this->makeWaitingBooking(5);

        $this->actingAs($prep);
        $this->assertTrue(DeskActionLayout::canRecordVitals($booking));

        $this->actingAs($money);
        $this->assertFalse(DeskActionLayout::canRecordVitals($booking));

        tenancy()->end();
    }

    public function test_money_only_staff_still_cannot_save_vitals(): void
    {
        tenancy()->initialize($this->tenant);

        $money = $this->makeUser('money2@vitals.test', User::ROLE_STAFF, [StaffDeskJobs::JOB_MONEY]);
        $booking = $this->makeWaitingBooking(6);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        app(VisitRecordService::class)->saveStaffVitals($booking, $money, ['weight_kg' => 71]);

        tenancy()->end();
    }

    public function test_completing_an_untouched_visit_keeps_the_desks_credit(): void
    {
        tenancy()->initialize($this->tenant);

        $prep = $this->makeUser('prep6@vitals.test', User::ROLE_STAFF, [StaffDeskJobs::JOB_PREP]);
        $doctor = $this->makeUser('doc@vitals.test', User::ROLE_DOCTOR);
        $booking = $this->makeWaitingBooking(7);

        $service = app(VisitRecordService::class);
        $service->saveStaffVitals($booking, $prep, [
            'weight_kg' => 70.4,
            'bp_systolic' => 150,
            'bp_diastolic' => 95,
        ]);

        // The pad prefills what the desk measured; the doctor completes without
        // touching a vitals box.
        $state = VisitNotesFormSchema::stateFromRecord($booking->fresh()->visitRecord);
        $record = $service->saveForCompletedBooking($booking, $doctor, $state + [
            'diagnosis' => VisitNotesFormSchema::FREE_DIAGNOSIS_PREFIX.'Hypertension',
        ]);

        $this->assertNotNull($record);
        $this->assertSame($prep->id, $record->vitals_recorded_by);
        $this->assertSame(150, $record->bp_systolic);

        tenancy()->end();
    }

    public function test_a_doctor_who_re_measures_takes_the_reading_off_the_desks_name(): void
    {
        tenancy()->initialize($this->tenant);

        $prep = $this->makeUser('prep7@vitals.test', User::ROLE_STAFF, [StaffDeskJobs::JOB_PREP]);
        $doctor = $this->makeUser('doc2@vitals.test', User::ROLE_DOCTOR);
        $booking = $this->makeWaitingBooking(8);

        $service = app(VisitRecordService::class);
        $service->saveStaffVitals($booking, $prep, [
            'weight_kg' => 70.4,
            'bp_systolic' => 150,
            'bp_diastolic' => 95,
        ]);

        $state = VisitNotesFormSchema::stateFromRecord($booking->fresh()->visitRecord);
        $record = $service->saveForCompletedBooking($booking, $doctor, array_merge($state, [
            'diagnosis' => VisitNotesFormSchema::FREE_DIAGNOSIS_PREFIX.'Hypertension',
            // Re-checked in the chamber and it had settled.
            'bp_systolic' => 138,
            'bp_diastolic' => 88,
        ]));

        $this->assertNotNull($record);
        $this->assertNull($record->vitals_recorded_by);
        $this->assertFalse($record->vitalsTakenAtDesk());
        $this->assertSame(138, $record->bp_systolic);

        tenancy()->end();
    }

    public function test_the_desk_never_stamps_over_who_wrote_the_visit(): void
    {
        tenancy()->initialize($this->tenant);

        $prep = $this->makeUser('prep8@vitals.test', User::ROLE_STAFF, [StaffDeskJobs::JOB_PREP]);
        $doctor = $this->makeUser('doc3@vitals.test', User::ROLE_DOCTOR);
        $booking = $this->makeWaitingBooking(9);

        VisitRecord::create([
            'booking_id' => $booking->id,
            'tenant_id' => 'outdoor-vitals-clinic',
            'patient_id' => $booking->patient_id,
            'recorded_by' => $doctor->id,
            'chief_complaint' => 'Headache',
            'recorded_at' => now(),
        ]);

        $record = app(VisitRecordService::class)->saveStaffVitals($booking, $prep, ['weight_kg' => 70]);

        $this->assertSame($doctor->id, $record->recorded_by);
        $this->assertSame($prep->id, $record->vitals_recorded_by);
        $this->assertSame('Headache', $record->chief_complaint);

        tenancy()->end();
    }
}
