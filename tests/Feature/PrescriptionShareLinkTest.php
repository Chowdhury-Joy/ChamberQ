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
     * The printed pad is Bangla-first. English stays as a quieter second
     * line so a pharmacist can still read it. The paper the patient walks
     * out with must not lead with English just because the doctor uses the
     * English admin panel.
     */
    public function test_prescription_headers_label_the_patient_row_in_both_languages(): void
    {
        $share = $this->get($this->shareUrl())->assertOk();
        $this->assertBanglaFocusedPad($share->getContent());

        tenancy()->initialize($this->tenant);
        $printUrl = 'http://rx-share.localhost/prescriptions/'.$this->prescription->id.'/print';
        tenancy()->end();

        $print = $this->actingAs($this->doctor)->get($printUrl)->assertOk();
        $this->assertBanglaFocusedPad($print->getContent());
        $this->assertStringContainsString('রোগ নির্ণয়', $print->getContent());
        $this->assertStringContainsString('Diagnosis', $print->getContent());
    }

    private function assertBanglaFocusedPad(string $html): void
    {
        $this->assertStringContainsString('lang="bn"', $html);
        $this->assertStringContainsString('Hind Siliguri', $html);
        $this->assertStringContainsString('class="pad-l-bn"', $html);

        $bnAt = mb_strpos($html, '>রোগী<');
        $enAt = mb_strpos($html, '>Patient<');
        $this->assertNotFalse($bnAt, 'Bangla patient label missing');
        $this->assertNotFalse($enAt, 'English patient label missing — pharmacist still needs it');
        $this->assertLessThan(
            $enAt,
            $bnAt,
            'Bangla must lead English on the printed pad, even when the admin panel is English',
        );
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

    public function test_the_patient_copy_keeps_the_letterhead_even_if_paper_is_in_the_url(): void
    {
        $html = $this->get($this->shareUrl().'?paper=1')->assertOk()->getContent();

        $this->assertStringContainsString('<header class="pad-header">', $html);
        $this->assertStringContainsString('SECRETCHAMBERADDRESS', $html);
        $this->assertStringNotContainsString('class="pad pad--on-paper"', $html);
    }

    /**
     * A second medicine whose frequency and duration multiply out cleanly.
     * The one seeded in setUp deliberately does not (`TDS`), so both the
     * spoken-out dose and the total have a silent case in every assertion.
     */
    private function addCountableMedicine(): void
    {
        tenancy()->initialize($this->tenant);

        PrescriptionItem::create([
            'prescription_id' => $this->prescription->id,
            'medicine_name' => 'SERGEL',
            'generic_name' => 'Esomeprazole',
            'dose' => '20 mg',
            'frequency' => '1+0+1',
            'duration' => '7 days',
            'sort_order' => 2,
        ]);

        tenancy()->end();
    }

    public function test_medicines_are_numbered_on_both_the_print_and_the_patient_copy(): void
    {
        $this->addCountableMedicine();

        // "Stop number 3" is how a doctor refers back to a line on a follow-up
        // call, and how a pharmacist reads a strip back to the counter.
        $share = $this->get($this->shareUrl())->assertOk()->getContent();
        $this->assertStringContainsString('<span class="med-number">1.</span>', $share);
        $this->assertStringContainsString('<span class="med-number">2.</span>', $share);

        tenancy()->initialize($this->tenant);
        $printUrl = 'http://rx-share.localhost/prescriptions/'.$this->prescription->id.'/print';
        tenancy()->end();

        $print = $this->actingAs($this->doctor)->get($printUrl)->assertOk()->getContent();
        $this->assertStringContainsString('<span class="med-number">1.</span>', $print);
        $this->assertStringContainsString('<span class="med-number">2.</span>', $print);
    }

    public function test_the_total_is_printed_only_when_the_doctors_own_line_multiplies_out(): void
    {
        $this->addCountableMedicine();

        $html = $this->get($this->shareUrl())->assertOk()->getContent();

        // 1+0+1 for 7 days is 14 doses — arithmetic on what the doctor wrote,
        // which the pharmacist is otherwise doing by hand off two columns at
        // opposite ends of the row.
        $this->assertStringContainsString('class="total-doses"', $html);
        $this->assertMatchesRegularExpression(
            '/class="total-doses">.*?মোট.*?\b14\b/su',
            $html,
            '1+0+1 for 7 days must print as 14.',
        );

        // TDS has no daily count. A number invented for it would be read as
        // the doctor's, so nothing is printed at all.
        $this->assertSame(
            1,
            substr_count($html, 'class="total-doses"'),
            'Only the line that multiplies out cleanly may carry a total.',
        );
    }

    public function test_the_patient_copy_writes_the_dose_out_and_the_doctors_print_does_not(): void
    {
        $this->addCountableMedicine();

        // The share link is read on a phone essentially always, and a patient
        // reading `1+0+1` has to be taught the three positions first.
        $share = $this->get($this->shareUrl())->assertOk()->getContent();
        // The class *attribute*, not the stylesheet — asserting on the bare
        // string matched the CSS rule and passed with the markup removed.
        $this->assertStringContainsString('<div class="medicine-plain">', $share);
        $this->assertStringContainsString('সকাল ১টি · রাত ১টি', $share);
        // The shorthand itself stays on the sheet for the pharmacist.
        $this->assertStringContainsString('1+0+1', $share);

        tenancy()->initialize($this->tenant);
        $printUrl = 'http://rx-share.localhost/prescriptions/'.$this->prescription->id.'/print';
        tenancy()->end();

        // The doctor's A4 pad is unchanged — this is a patient-copy addition,
        // not a new line on everyone's paper.
        $print = $this->actingAs($this->doctor)->get($printUrl)->assertOk()->getContent();
        $this->assertStringNotContainsString('<div class="medicine-plain">', $print);
        $this->assertStringNotContainsString('সকাল ১টি', $print);
    }

    public function test_the_patient_copy_turns_the_medicine_list_into_cards_on_a_phone(): void
    {
        $html = $this->get($this->shareUrl())->assertOk()->getContent();

        // The A4 grid squeezed onto a phone put a nowrap dosing column against
        // the brand. Below 640px each medicine becomes its own card instead.
        $this->assertMatchesRegularExpression(
            '/@media \(max-width: 640px\) \{[^@]*\.med-row \{\s*display: block;/s',
            $html,
        );
        $this->assertMatchesRegularExpression('/\.medicine-plain \{[^}]*font-size: 15px/s', $html);
    }

    public function test_only_the_share_link_offers_to_forward_on_whatsapp(): void
    {
        $share = $this->get($this->shareUrl())->assertOk()->getContent();
        $this->assertStringContainsString('wa.me', $share);
        $this->assertStringContainsString(__('Send on WhatsApp'), $share);

        // The portal route reaches this same view after a session phone lookup.
        // Forwarding must not hand the patient's number to whoever receives the prescription.
        tenancy()->initialize($this->tenant);
        tenancy()->end();

        $this->post('http://rx-share.localhost/portal', ['phone' => '01712345699'])
            ->assertRedirect('http://rx-share.localhost/portal');

        $portal = $this->get('http://rx-share.localhost/portal/prescriptions/'.$this->prescription->id)
            ->assertOk()
            ->getContent();
        $this->assertStringNotContainsString('wa.me', $portal);
    }
}
