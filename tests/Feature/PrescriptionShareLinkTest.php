<?php

namespace Tests\Feature;

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
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * The patient's shared copy is a full clinical pad (diagnosis, notes, meds,
 * chamber). Portal phone lookup is the durable backup; /p/{token} still
 * expires after SHARE_LINK_EXPIRY_HOURS. Voice notes and photos stay off.
 */
class PrescriptionShareLinkTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $doctor;

    private User $staff;

    private Prescription $prescription;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['id' => 'rx-share', 'plan_tier' => 'solo']);
        Domain::create(['domain' => 'rx-share.localhost', 'tenant_id' => $this->tenant->id]);

        tenancy()->initialize($this->tenant);

        $this->doctor = User::create([
            'name' => 'Dr Share',
            'email' => 'doc@rxshare.test',
            'password' => Hash::make('secret'),
            'role' => User::ROLE_DOCTOR,
            'tenant_id' => $this->tenant->id,
        ]);

        $this->staff = User::create([
            'name' => 'Staff Share',
            'email' => 'staff@rxshare.test',
            'password' => Hash::make('secret'),
            'role' => User::ROLE_STAFF,
            'tenant_id' => $this->tenant->id,
        ]);

        $chamber = Chamber::create([
            'name' => 'Main Chamber',
            'address' => 'SECRETCHAMBERADDRESS',
            'contact' => '0299999999',
        ]);
        $doctorProfile = Doctor::create([
            'name' => 'Dr Share',
            'qualifications' => 'MBBS, FCPS',
            'registration_number' => 'C-55443',
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

        $patient = Patient::create([
            'name' => 'Shared Patient',
            'phone' => '01712345699',
            'age' => 30,
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
            'serial_number' => 1,
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $condition = Condition::create([
            'code' => 'SLD-XX-001',
            'name' => 'SECRETDIAGNOSISNAME',
            'aliases' => [],
            'category' => 'Test',
        ]);

        $visitRecord = VisitRecord::create([
            'tenant_id' => $this->tenant->id,
            'booking_id' => $booking->id,
            'patient_id' => $patient->id,
            'recorded_by' => $this->doctor->id,
            'condition_id' => $condition->id,
            'diagnosis_uncoded' => null,
            'tests_advised' => 'SECRETTESTSADVISED',
            'reports_seen' => 'SECRETREPORTSSEEN',
            'recorded_at' => now(),
        ]);

        $this->prescription = Prescription::create([
            'tenant_id' => $this->tenant->id,
            'visit_record_id' => $visitRecord->id,
            'patient_id' => $patient->id,
            'prescribed_by' => $this->doctor->id,
            'advice' => 'Take after meals',
        ]);

        PrescriptionItem::create([
            'prescription_id' => $this->prescription->id,
            'medicine_name' => 'NAPA',
            'generic_name' => 'Paracetamol',
            'dose' => '500mg',
            'frequency' => 'TDS',
            'duration' => '5 days',
            'sort_order' => 1,
        ]);

        tenancy()->end();
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    private function shareUrl(): string
    {
        tenancy()->initialize($this->tenant);
        $this->app['request']->headers->set('HOST', 'rx-share.localhost');

        return $this->prescription->shareUrl();
    }

    /**
     * The share link the doctor actually sends. Its shape (short token vs the
     * signed URL this replaced) and its billing are covered by
     * PrescriptionShortLinkTest; what matters here is the privacy scope.
     */
    public function test_share_link_opens_without_a_login(): void
    {
        $url = $this->shareUrl();

        $this->get($url)
            ->assertOk()
            ->assertSee('Shared Patient')
            ->assertSee('NAPA')
            ->assertSee('Paracetamol')
            ->assertSee('500mg')
            ->assertSee('Take after meals')
            ->assertSee('C-55443');
    }

    /**
     * The masthead labels carry Bangla and English at once, on both the
     * patient's copy and the doctor's printout. A patient's family reads the
     * Bangla; a pharmacist or a referred-to consultant may be handed only the
     * English. Neither may be the sole language on the page.
     */
    public function test_prescription_headers_label_the_patient_row_in_both_languages(): void
    {
        $this->get($this->shareUrl())
            ->assertOk()
            ->assertSee('Patient / রোগী', escape: false)
            ->assertSee('Date / তারিখ', escape: false)
            ->assertSee('Reg. No. / রেজি. নং', escape: false)
            ->assertSee(\App\Support\Bilingual::label('Please bring this prescription when you visit again.'), escape: false);

        tenancy()->initialize($this->tenant);
        $printUrl = 'http://rx-share.localhost/prescriptions/'.$this->prescription->id.'/print';
        tenancy()->end();

        $this->actingAs($this->doctor)->get($printUrl)
            ->assertOk()
            ->assertSee('Patient / রোগী', escape: false)
            ->assertSee('Age / বয়স', escape: false)
            ->assertSee('Female / মহিলা', escape: false)
            ->assertSee('Diagnosis / রোগ নির্ণয়', escape: false);
    }

    public function test_shared_copy_includes_diagnosis_and_chamber_contact(): void
    {
        $url = $this->shareUrl();

        $this->get($url)
            ->assertOk()
            ->assertSee('SECRETDIAGNOSISNAME')
            ->assertSee('SECRETTESTSADVISED')
            ->assertSee('SECRETCHAMBERADDRESS')
            ->assertSee('0299999999')
            // Reports the patient brought stay off the patient copy — they are
            // chamber paperwork, not something the patient needs on their phone.
            ->assertDontSee('SECRETREPORTSSEEN');
    }

    /**
     * The old signed route is kept alive only until the links already delivered
     * to patients have expired. Until then it must still open — and must still
     * refuse anything unsigned.
     */
    public function test_legacy_signed_link_still_opens_but_only_when_signed(): void
    {
        tenancy()->initialize($this->tenant);
        $this->app['request']->headers->set('HOST', 'rx-share.localhost');

        $signed = URL::temporarySignedRoute(
            'prescriptions.share',
            now()->addHours(Prescription::SHARE_LINK_EXPIRY_HOURS),
            ['prescription' => $this->prescription->id],
        );

        $this->get($signed)->assertOk()->assertSee('NAPA');

        $bare = 'http://rx-share.localhost/prescriptions/'.$this->prescription->id.'/share';

        $this->get($bare)->assertForbidden();
        $this->get($bare.'?signature=deadbeef&expires='.now()->addDay()->timestamp)->assertForbidden();
    }

    public function test_link_stops_working_once_it_expires(): void
    {
        $url = $this->shareUrl();

        $this->travelTo(now()->addHours(Prescription::SHARE_LINK_EXPIRY_HOURS + 1));

        $this->get($url)->assertNotFound();
    }

    public function test_doctor_only_print_route_is_unchanged(): void
    {
        tenancy()->initialize($this->tenant);

        $printUrl = 'http://rx-share.localhost/prescriptions/'.$this->prescription->id.'/print';

        $this->actingAs($this->doctor)->get($printUrl)->assertOk()->assertSee('NAPA');
        $this->actingAs($this->staff)->get($printUrl)->assertForbidden();
        $this->get($printUrl)->assertForbidden();
    }
}
