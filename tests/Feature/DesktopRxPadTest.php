<?php

namespace Tests\Feature;

use App\Filament\TenantAdmin\Pages\ConsultScreen;
use App\Filament\TenantAdmin\Support\VisitNotesFormSchema;
use App\Models\Booking;
use App\Models\Chamber;
use App\Models\Condition;
use App\Models\Doctor;
use App\Models\Domain;
use App\Models\LiveSession;
use App\Models\MedicineUsage;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\ScheduleSession;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VisitRecord;
use App\Services\VisitRecordService;
use App\Support\PrescriptionTiming;
use App\Support\RxSafety;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class DesktopRxPadTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $doctor;

    private Booking $booking;

    private Patient $patient;

    private LiveSession $liveSession;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['id' => 'rx-pad', 'plan_tier' => 'solo', 'queue_runner' => 'doctor']);
        Domain::create(['domain' => 'rx-pad.localhost', 'tenant_id' => $this->tenant->id]);

        tenancy()->initialize($this->tenant);

        $this->doctor = User::create([
            'name' => 'Dr Pad',
            'email' => 'doc@rxpad.test',
            'password' => Hash::make('secret'),
            'role' => User::ROLE_DOCTOR,
            'tenant_id' => $this->tenant->id,
        ]);

        $chamber = Chamber::create(['name' => 'Main', 'address' => 'Dhaka']);
        $doctorProfile = Doctor::create([
            'name' => 'Dr Pad',
            'registration_number' => 'B-11223',
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
            'name' => 'Aminul Islam',
            'phone' => '01712345999',
            'age' => 26,
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

        $this->liveSession = LiveSession::create([
            'schedule_session_id' => $session->id,
            'session_date' => today()->toDateString(),
            'status' => 'active',
            'started_at' => now(),
            'current_booking_id' => $this->booking->id,
        ]);
    }

    public function test_timing_normalizes_keys_and_shorthand(): void
    {
        $this->assertSame(PrescriptionTiming::AFTER_FOOD, PrescriptionTiming::normalize('af'));
        $this->assertSame(PrescriptionTiming::BEFORE_FOOD, PrescriptionTiming::normalize('before_food'));
        $this->assertNull(PrescriptionTiming::normalize('whenever'));
        $this->assertStringContainsString('After food', (string) PrescriptionTiming::bilingualLabel('af'));
        $this->assertStringContainsString('খাবারের পর', (string) PrescriptionTiming::bilingualLabel('af'));
    }

    public function test_service_saves_pad_fields_and_item_timing(): void
    {
        $record = app(VisitRecordService::class)->saveForCompletedBooking($this->booking->fresh(), $this->doctor, [
            'chief_complaint' => 'Chest pain 2d',
            'history' => 'HTN',
            'on_examination' => 'Heart S1+S2',
            'diagnosis_free_text' => 'IHD',
            'prescription_items' => [
                [
                    'medicine_name' => 'ECOSPRIN PLUS',
                    'generic_name' => 'Aspirin + Clopidogrel',
                    'indication' => 'Antiplatelet',
                    'dose' => '75 mg',
                    'frequency' => '0+0+1',
                    'duration' => 'Continue',
                    'timing' => 'af',
                    'instructions' => 'Do not skip',
                ],
            ],
            'advice' => 'Stop smoking',
            'tests_advised' => 'ECG',
        ]);

        $this->assertNotNull($record);
        $this->assertSame('Chest pain 2d', $record->chief_complaint);
        $this->assertSame('HTN', $record->history);
        $this->assertSame('Heart S1+S2', $record->on_examination);
        $this->assertTrue($record->hasClinicalContent());

        $item = $record->prescription->items->first();
        $this->assertSame('ECOSPRIN PLUS', $item->medicine_name);
        $this->assertSame('Antiplatelet', $item->indication);
        $this->assertSame(PrescriptionTiming::AFTER_FOOD, $item->timing);
        $this->assertSame('Do not skip', $item->instructions);
    }

    public function test_print_shows_structured_left_column_and_bilingual_timing(): void
    {
        $visit = VisitRecord::create([
            'tenant_id' => $this->tenant->id,
            'booking_id' => $this->booking->id,
            'patient_id' => $this->patient->id,
            'recorded_by' => $this->doctor->id,
            'diagnosis_uncoded' => 'IHD',
            'chief_complaint' => 'SECRETCCCHESTPAIN',
            'history' => 'SECRETHOHISTORY',
            'on_examination' => 'SECRETOEFINDINGS',
            'tests_advised' => 'SECRETECG',
            'recorded_at' => now(),
        ]);

        $prescription = Prescription::create([
            'tenant_id' => $this->tenant->id,
            'visit_record_id' => $visit->id,
            'patient_id' => $this->patient->id,
            'prescribed_by' => $this->doctor->id,
        ]);

        PrescriptionItem::create([
            'prescription_id' => $prescription->id,
            'medicine_name' => 'NAPA',
            'dose' => '500 mg',
            'frequency' => '1+0+1',
            'duration' => '5 days',
            'timing' => PrescriptionTiming::AFTER_FOOD,
            'sort_order' => 0,
        ]);

        $response = $this->actingAs($this->doctor)
            ->get(tenant_web_route('prescriptions.print', $prescription));

        $response->assertOk()
            ->assertSee('SECRETCCCHESTPAIN')
            ->assertSee('SECRETHOHISTORY')
            ->assertSee('SECRETOEFINDINGS')
            ->assertSee('SECRETECG')
            ->assertSee('NAPA')
            ->assertSee('After food')
            ->assertSee('খাবারের পর')
            ->assertSee('<span class="pad-l-bn">খাবারের পর</span>', false)
            ->assertDontSee('&lt;span', false);
    }

    /**
     * The patient copy is a complete prescription pad.
     *
     * It used to withhold the clinical left column; that was reversed on
     * 2026-08-12 by owner decision — a patient's own prescription, and any
     * referral built from it, is more useful carrying the diagnosis. Voice
     * notes and prescription photos are the only clinical artefacts that stay
     * off this page.
     */
    public function test_share_page_shows_the_full_clinical_pad(): void
    {
        $visit = VisitRecord::create([
            'tenant_id' => $this->tenant->id,
            'booking_id' => $this->booking->id,
            'patient_id' => $this->patient->id,
            'recorded_by' => $this->doctor->id,
            'diagnosis_uncoded' => 'SECRETDIAGNOSIS',
            'chief_complaint' => 'SECRETCOMPLAINT',
            'recorded_at' => now(),
        ]);

        $prescription = Prescription::create([
            'tenant_id' => $this->tenant->id,
            'visit_record_id' => $visit->id,
            'patient_id' => $this->patient->id,
            'prescribed_by' => $this->doctor->id,
        ]);

        PrescriptionItem::create([
            'prescription_id' => $prescription->id,
            'medicine_name' => 'SERGEL',
            'dose' => '20 mg',
            'frequency' => '1+0+0',
            'duration' => '14 days',
            'timing' => PrescriptionTiming::BEFORE_FOOD,
            'sort_order' => 0,
        ]);

        $token = $prescription->shareToken();

        $response = $this->get(tenant_web_route('prescriptions.share-token', ['token' => $token]));

        $response->assertOk()
            ->assertSee('SERGEL')
            ->assertSee('Before food')
            ->assertSee('খাবারের আগে')
            ->assertSee('<span class="pad-l-bn">খাবারের আগে</span>', false)
            ->assertDontSee('&lt;span', false)
            // The clinical column now travels with the patient's copy.
            ->assertSee('SECRETDIAGNOSIS')
            ->assertSee('SECRETCOMPLAINT');

        // Recordings and photographed slips are never served here — they are
        // doctor-only, behind VisitMediaController.
        $response->assertDontSee('visit-audio')
            ->assertDontSee('visit-photos');
    }

    public function test_consult_screen_save_rx_desk_persists_without_completing(): void
    {
        tenancy()->initialize($this->tenant);
        Filament::setCurrentPanel(Filament::getPanel('tenantAdmin'));
        $this->actingAs($this->doctor);

        Livewire::test(ConsultScreen::class)
            ->assertSeeHtml('cs-rx-desk')
            ->call('saveRxDesk', [
                'chief_complaint' => 'Headache',
                'history' => 'DM',
                'on_examination' => 'NAD',
                'diagnosis' => VisitNotesFormSchema::FREE_DIAGNOSIS_PREFIX.'Migraine',
                'prescription_items' => [
                    [
                        'medicine_name' => 'NAPA',
                        'dose' => '500 mg',
                        'frequency' => '1+0+1',
                        'duration' => '3 days',
                        'timing' => 'af',
                    ],
                ],
                'advice' => 'Rest',
            ]);

        $this->booking->refresh();
        $this->assertSame('in_chamber', $this->booking->status);

        $visit = VisitRecord::query()->where('booking_id', $this->booking->id)->first();
        $this->assertNotNull($visit);
        $this->assertSame('Headache', $visit->chief_complaint);
        $this->assertSame('DM', $visit->history);
        $this->assertSame('NAD', $visit->on_examination);
        $this->assertSame('Migraine', $visit->diagnosis_uncoded);
        $this->assertSame(PrescriptionTiming::AFTER_FOOD, $visit->prescription->items->first()->timing);
    }

    public function test_pad_renders_complaint_chips_and_seeds_history_from_the_patient_record(): void
    {
        $this->patient->update(['conditions' => 'HTN, DM']);

        tenancy()->initialize($this->tenant);
        Filament::setCurrentPanel(Filament::getPanel('tenantAdmin'));
        $this->actingAs($this->doctor);

        Livewire::test(ConsultScreen::class)
            ->assertSeeHtml('cs-rx-desk')
            ->assertSeeHtml('appendComplaint')
            ->assertSeeHtml('cs-rx-desk__mini-table')
            ->assertSeeHtml('cs-rx-desk__oe-table')
            ->assertSeeHtml('cs-rx-desk__vital')
            ->assertDontSeeHtml('<table class="cs-rx-desk__oe-table">')
            ->assertSeeHtml('Save &amp; print')
            // The seed reaches the browser as Alpine config, not as a value
            // already written to the record.
            ->assertSeeHtml('HTN, DM');
    }

    public function test_starter_advice_stays_on_the_desk_after_a_coded_diagnosis_is_saved(): void
    {
        $condition = Condition::create([
            'code' => 'SLD-TEST-ADVICE',
            'name' => 'Gastritis pad test',
            'aliases' => [],
            'default_advice' => json_encode(
                ['en' => 'SECRETPREMADVICE Avoid spicy food.', 'bn' => 'ঝাল খাবার এড়িয়ে চলুন।'],
                JSON_UNESCAPED_UNICODE
            ),
            'default_tests' => 'CBC',
        ]);

        VisitRecord::create([
            'tenant_id' => $this->tenant->id,
            'booking_id' => $this->booking->id,
            'patient_id' => $this->patient->id,
            'recorded_by' => $this->doctor->id,
            'condition_id' => $condition->id,
            'recorded_at' => now(),
        ]);

        tenancy()->initialize($this->tenant);
        Filament::setCurrentPanel(Filament::getPanel('tenantAdmin'));
        $this->actingAs($this->doctor);

        // Saving remounts the Alpine pad. The starter line must still be on
        // the page — otherwise "Add advice" exists only in the moment between
        // picking a diagnosis and the first save, which is when doctors look.
        Livewire::test(ConsultScreen::class)
            ->assertSeeHtml('cs-rx-desk')
            ->assertSee('SECRETPREMADVICE Avoid spicy food.')
            ->assertSeeHtml('cs-rx-desk__chip--advice');
    }

    public function test_the_desk_can_write_a_reason_for_each_medicine(): void
    {
        tenancy()->initialize($this->tenant);
        Filament::setCurrentPanel(Filament::getPanel('tenantAdmin'));
        $this->actingAs($this->doctor);

        // `indication` reached print and the phone modal from the beginning,
        // but the desk had no input for it, so a doctor working at a desk
        // could not say why a medicine was given.
        Livewire::test(ConsultScreen::class)
            ->assertSeeHtml('cs-rx-desk__reason')
            ->assertSeeHtml('item.indication')
            ->call('saveRxDesk', [
                'prescription_items' => [
                    [
                        'medicine_name' => 'NAPA',
                        'indication' => 'Fever',
                        'dose' => '500 mg',
                    ],
                ],
            ]);

        $visit = VisitRecord::query()->where('booking_id', $this->booking->id)->first();
        $this->assertNotNull($visit);
        $this->assertSame('Fever', $visit->prescription->items->first()->indication);
    }

    public function test_preview_opens_a_modal_over_the_desk_rather_than_a_new_page(): void
    {
        tenancy()->initialize($this->tenant);
        Filament::setCurrentPanel(Filament::getPanel('tenantAdmin'));
        $this->actingAs($this->doctor);

        $page = Livewire::test(ConsultScreen::class)
            // Preview mounts the action; only Save & print still opens a tab.
            ->assertSeeHtml('previewPrescription')
            ->call('saveRxDesk', [
                'prescription_items' => [
                    ['medicine_name' => 'NAPA', 'dose' => '500 mg'],
                ],
            ]);

        $prescription = VisitRecord::query()
            ->where('booking_id', $this->booking->id)
            ->first()?->prescription;
        $this->assertNotNull($prescription);

        // The modal frames the real print route, so the preview cannot drift
        // from what comes out of the printer.
        $action = $page->call('mountAction', 'previewPrescription')
            ->instance()
            ->getMountedAction();

        $this->assertNotNull($action, 'Preview must mount an action, not navigate away');
        $this->assertSame(__('Prescription preview'), $action->getModalHeading());

        $modal = $action->getModalContent()->render();
        $this->assertStringContainsString('cs-rx-preview__frame', $modal);
        $this->assertStringContainsString(
            'prescriptions/'.$prescription->id.'/print',
            $modal,
            'The modal frames the real print route',
        );
    }

    public function test_the_desk_leaves_only_one_complete_visit_button_on_screen(): void
    {
        tenancy()->initialize($this->tenant);
        Filament::setCurrentPanel(Filament::getPanel('tenantAdmin'));
        $this->actingAs($this->doctor);

        $html = Livewire::test(ConsultScreen::class)->html();

        $this->assertStringContainsString('cs-rx-desk__bar-actions', $html);

        // Complete visit closes the visit and moves the queue on — a page
        // action, not something the prescription pad does. It now lives only
        // in the page header (desktop) and the thumb strip (phones), so the
        // desk template must not mount it at all. When it did, the header had
        // to be hidden at >=1024px and three breakpoint rules had to be kept
        // in step; see the two-Complete-visit-buttons entry in bug_history.md.
        $desk = file_get_contents(resource_path('views/filament/tenant-admin/components/rx-desk.blade.php'));
        $this->assertStringNotContainsString(
            "mountAction('completeVisit')",
            $desk,
            'The Rx pad bar must not carry Complete visit — it belongs to the page.',
        );

        // And the pad keeps exactly one filled action, so nothing competes
        // with Save & print for the eye. Asserted against the rendered page,
        // not the template — the template still explains in a Blade comment
        // why the button went, and comments are not output.
        $this->assertStringNotContainsString('Save only', $html);
    }

    public function test_the_patient_strip_sticks_below_the_content_header(): void
    {
        // The content header is sticky at top: 0 with z-index 40 and min-h-16
        // (4rem). The patient strip used to share that seat with the old
        // topbar, so name / Preview / Save & print painted over Complete visit.
        $css = file_get_contents(resource_path('css/filament/tenantAdmin/theme.css'));
        $this->assertMatchesRegularExpression(
            '/\.cs-rx-desk__bar\s*\{[^}]*top:\s*4rem/',
            $css,
            'The patient strip must stick under the content header (min-h-16), not at the viewport top.',
        );
        $this->assertDoesNotMatchRegularExpression(
            '/\.cs-rx-desk__bar\s*\{[^}]*top:\s*0(?:px|;|,|\s)/',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/\.fi-header\.fi-content-shell-header\s*\{[^}]*z-index:\s*40/',
            $css,
            'The content header must paint above the patient strip if they ever share space.',
        );
        $this->assertStringNotContainsString('.fi-topbar-ctn { z-index: 40; }', $css);
    }

    public function test_the_desk_never_ships_a_hardcoded_dose_list(): void
    {
        tenancy()->initialize($this->tenant);
        Filament::setCurrentPanel(Filament::getPanel('tenantAdmin'));
        $this->actingAs($this->doctor);

        Livewire::test(ConsultScreen::class)
            // Chips now come from the catalogue for the brand on that row.
            ->assertSeeHtml('ensureDoseOptions')
            ->assertSeeHtml('medicineDosesUrl')
            // The old fixed list offered a 5 mg NAPA nobody manufactures.
            ->assertDontSeeHtml("['500 mg', '10 mg', '20 mg', '40 mg', '5 mg']");
    }

    public function test_the_typing_box_searches_the_catalogue_instead_of_only_parsing_tokens(): void
    {
        tenancy()->initialize($this->tenant);
        Filament::setCurrentPanel(Filament::getPanel('tenantAdmin'));
        $this->actingAs($this->doctor);

        // Typing `napa` and pressing Enter used to add a bare NAPA row: the box
        // parsed tokens and never asked the catalogue anything. It now searches
        // as you type and prefills from the row you pick.
        Livewire::test(ConsultScreen::class)
            ->assertSeeHtml('searchShorthand')
            ->assertSeeHtml('shorthandResults')
            ->assertSeeHtml('moveShorthand');

        $desk = file_get_contents(resource_path('views/filament/tenant-admin/components/rx-desk.blade.php'));

        // Enter on a half-typed name must not silently resolve to a different
        // drug, so the fallback match is exact-only.
        $this->assertStringContainsString('catalogueMatch', $desk);
        $this->assertStringContainsString(
            "(row.brand_name || '').trim().toLowerCase() === needle",
            $desk,
            'A typed name may only prefill from an exact brand match.',
        );
    }

    public function test_the_pad_opens_with_a_row_waiting_and_drops_it_if_left_empty(): void
    {
        tenancy()->initialize($this->tenant);
        Filament::setCurrentPanel(Filament::getPanel('tenantAdmin'));
        $this->actingAs($this->doctor);

        $desk = file_get_contents(resource_path('views/filament/tenant-admin/components/rx-desk.blade.php'));
        $this->assertStringContainsString(
            'if (!this.items.length) {',
            $desk,
            'An empty pad must still show one row to type into.',
        );

        // The waiting row is only ever furniture: an untouched one must never
        // reach the prescription.
        Livewire::test(ConsultScreen::class)->call('saveRxDesk', [
            'chief_complaint' => 'Fever',
            'prescription_items' => [
                ['medicine_name' => '', 'dose' => '', 'frequency' => ''],
            ],
        ]);

        $visit = VisitRecord::query()->where('booking_id', $this->booking->id)->first();
        $this->assertNotNull($visit);
        $this->assertNull($visit->prescription);
    }

    public function test_brand_suggestions_are_not_clipped_inside_the_table(): void
    {
        tenancy()->initialize($this->tenant);
        Filament::setCurrentPanel(Filament::getPanel('tenantAdmin'));
        $this->actingAs($this->doctor);

        // overflow-x:auto on the wrap forces overflow-y:auto too, which clipped
        // the suggestion list inside the brand cell — search ran, nothing showed.
        $css = file_get_contents(resource_path('css/filament/tenantAdmin/theme.css'));
        $this->assertStringContainsString('.cs-rx-desk__table-wrap { overflow: visible; }', $css);
        $this->assertStringNotContainsString('.cs-rx-desk__table-wrap { overflow-x: auto;', $css);

        Livewire::test(ConsultScreen::class)
            ->assertSeeHtml('cs-rx-desk__brand-cell')
            ->assertSeeHtml('resolveTypedBrand');

        $desk = file_get_contents(resource_path('views/filament/tenant-admin/components/rx-desk.blade.php'));
        $this->assertStringContainsString("tenant_web_url('/api/medicines/search')", $desk);
        $this->assertStringContainsString("tenant_web_url('/api/medicines/doses')", $desk);
        $this->assertStringNotContainsString("tenant_web_url('/api/prescriptions/dictate')", $desk);
        $this->assertStringNotContainsString('webkitSpeechRecognition', $desk);
        $this->assertStringNotContainsString(
            'audio/webm',
            $desk,
            'The pad must not upload or keep audio. Voice notes are a separate recorder.',
        );
    }

    public function test_the_desk_backfills_frequency_duration_and_timing_from_brand_defaults(): void
    {
        tenancy()->initialize($this->tenant);
        Filament::setCurrentPanel(Filament::getPanel('tenantAdmin'));
        $this->actingAs($this->doctor);

        // Frequency chips already showed on focus; duration needs a click too,
        // and timing was only a bare dropdown. The pad must also *fill* those
        // cells when a brand is known — picking NAPA then tapping the drops
        // strength must not leave them blank.
        $desk = file_get_contents(resource_path('views/filament/tenant-admin/components/rx-desk.blade.php'));
        $this->assertStringContainsString('applyBrandDefaults', $desk);
        $this->assertStringContainsString('setDose(index, chip.value)', $desk);
        $this->assertStringContainsString("editingCell === 'timing-' + index", $desk);
        $this->assertStringContainsString('brandDefaults', $desk);
    }

    public function test_timing_select_options_are_blade_rendered_so_prefill_is_not_wiped(): void
    {
        tenancy()->initialize($this->tenant);
        Filament::setCurrentPanel(Filament::getPanel('tenantAdmin'));
        $this->actingAs($this->doctor);

        // Alpine x-for inside <select> made the browser reset to "—" the moment
        // prefill wrote after_food, then x-model wrote '' back. Options must
        // already be in the HTML when the value lands.
        $html = Livewire::test(ConsultScreen::class)->html();
        $this->assertStringContainsString('value="after_food"', $html);
        $this->assertStringContainsString('value="before_food"', $html);
        $this->assertStringContainsString('value="at_night"', $html);

        $desk = file_get_contents(resource_path('views/filament/tenant-admin/components/rx-desk.blade.php'));
        $this->assertStringNotContainsString(
            '<template x-for="opt in timingOptions" :key="opt.key">',
            $desk,
            'Timing <option>s must not be Alpine x-for inside the select',
        );
        $this->assertStringContainsString('@foreach ($timingOptions as $opt)', $desk);
    }

    public function test_medicine_search_url_includes_the_tenant_slug_on_the_central_host(): void
    {
        // Local `php artisan serve` is path tenancy: /{slug}/admin. A bare
        // /api/medicines/search 404s on the central app — the console error the
        // owner pasted. tenant_web_url() prefixes the slug on central hosts.
        tenancy()->initialize($this->tenant);
        Filament::setCurrentPanel(Filament::getPanel('tenantAdmin'));
        $this->actingAs($this->doctor);
        request()->headers->set('HOST', '127.0.0.1');

        $this->assertSame(
            '/rx-pad/api/medicines/search',
            tenant_web_url('/api/medicines/search'),
            'Path-tenant medicine search must include the tenant slug',
        );
        $this->assertSame('/rx-pad/api/medicines/doses', tenant_web_url('/api/medicines/doses'));

        // The live endpoint must answer on the prefixed path — that is what
        // the desk's fetch() hits from http://127.0.0.1:8000/{slug}/admin.
        $this->getJson('http://127.0.0.1/rx-pad/api/medicines/search?q=na')
            ->assertOk()
            ->assertJsonPath('query', 'na');
    }

    public function test_desk_saves_pulse_and_spo2_with_the_pad(): void
    {
        tenancy()->initialize($this->tenant);
        Filament::setCurrentPanel(Filament::getPanel('tenantAdmin'));
        $this->actingAs($this->doctor);

        Livewire::test(ConsultScreen::class)->call('saveRxDesk', [
            'pulse_bpm' => 78,
            'spo2_percent' => 98,
            'tests_advised' => 'ECG, CBC',
        ]);

        $visit = VisitRecord::query()->where('booking_id', $this->booking->id)->first();
        $this->assertNotNull($visit);
        $this->assertSame(78, $visit->pulse_bpm);
        $this->assertSame(98, $visit->spo2_percent);
        $this->assertSame('ECG, CBC', $visit->tests_advised);
        $this->assertStringContainsString('Pulse 78 /min', (string) $visit->vitalsSummary());
        $this->assertStringContainsString('SpO₂ 98 %', (string) $visit->vitalsSummary());
    }

    public function test_desk_saves_complaint_rows_as_one_line_each(): void
    {
        tenancy()->initialize($this->tenant);
        Filament::setCurrentPanel(Filament::getPanel('tenantAdmin'));
        $this->actingAs($this->doctor);

        Livewire::test(ConsultScreen::class)->call('saveRxDesk', [
            'chief_complaint' => "Fever — 3 days\nCough — 1 week",
        ]);

        $visit = VisitRecord::query()->where('booking_id', $this->booking->id)->first();
        $this->assertNotNull($visit);
        $this->assertSame("Fever — 3 days\nCough — 1 week", $visit->chief_complaint);
    }

    public function test_star_saves_the_line_to_my_medicines(): void
    {
        tenancy()->initialize($this->tenant);
        Filament::setCurrentPanel(Filament::getPanel('tenantAdmin'));
        $this->actingAs($this->doctor);

        Livewire::test(ConsultScreen::class)->call('saveMedicineDefault', [
            'medicine_name' => 'sergel',
            'generic_name' => 'Esomeprazole',
            'dose' => '20 mg',
            'frequency' => '1+0+0',
            'duration' => '1 month',
            'timing' => 'bf',
        ]);

        $usage = MedicineUsage::query()->where('user_id', $this->doctor->id)->firstOrFail();

        $this->assertSame('SERGEL', $usage->medicine_name);
        $this->assertSame('1+0+0', $usage->last_frequency);
        $this->assertSame(PrescriptionTiming::BEFORE_FOOD, $usage->last_timing);
    }

    public function test_the_consult_screen_applies_packs_but_cannot_create_them(): void
    {
        tenancy()->initialize($this->tenant);
        Filament::setCurrentPanel(Filament::getPanel('tenantAdmin'));
        $this->actingAs($this->doctor);

        // Packs are curated on My medicines, never built with a patient in the
        // chair (owner decision). If a save-a-pack control reappears here, the
        // consult screen has drifted back into being an editing surface.
        $this->assertFalse(
            method_exists(ConsultScreen::class, 'saveRxPack'),
            'The consult screen must not be able to create packs.',
        );

        $desk = file_get_contents(resource_path('views/filament/tenant-admin/components/rx-desk.blade.php'));
        $this->assertStringNotContainsString('savePack', $desk);
        $this->assertStringNotContainsString('packName', $desk);

        // Applying one is the whole point of packs being here at all.
        $this->assertStringContainsString('applyPack', $desk);
    }

    public function test_chief_complaint_alone_counts_as_clinical_content(): void
    {
        $record = app(VisitRecordService::class)->saveForCompletedBooking($this->booking->fresh(), $this->doctor, [
            'chief_complaint' => 'Fever 3 days',
        ]);

        $this->assertNotNull($record);
        $this->assertTrue($record->hasClinicalContent());
    }

    public function test_the_server_re_checks_rx_safety_even_if_the_pad_sends_a_clashing_prescription(): void
    {
        tenancy()->initialize($this->tenant);
        Filament::setCurrentPanel(Filament::getPanel('tenantAdmin'));
        $this->actingAs($this->doctor);
        $this->patient->update(['allergies' => 'Penicillin']);

        // Sent straight to the Livewire method, bypassing the pad's Alpine
        // checks entirely — which is the point. The desk computes its warnings
        // client-side in an untested copy of the rules; if that copy breaks, is
        // edited, or is skipped by a crafted payload, the server must still
        // catch it.
        Livewire::test(ConsultScreen::class)->call('saveRxDesk', [
            'prescription_items' => [
                ['medicine_name' => 'AMOXIL', 'generic_name' => 'Amoxicillin', 'dose' => '500 mg'],
                ['medicine_name' => 'MOXACIL', 'generic_name' => 'Amoxicillin', 'dose' => '500 mg'],
            ],
        ]);

        $warnings = RxSafety::allWarnings(
            $this->patient->fresh()->allergies,
            [
                ['medicine_name' => 'AMOXIL', 'generic_name' => 'Amoxicillin'],
                ['medicine_name' => 'MOXACIL', 'generic_name' => 'Amoxicillin'],
            ],
        );

        // Two brands of one generic, and a penicillin-class allergy on file.
        $this->assertNotEmpty($warnings);
        $this->assertTrue(
            collect($warnings)->contains(fn (string $w): bool => str_contains($w, 'Same generic')),
            'Expected the duplicate-generic rule to fire for two brands of Amoxicillin.',
        );

        // Advisory only — the visit still saved and the queue did not stall.
        $this->booking->refresh();
        $this->assertSame('in_chamber', $this->booking->status);
        $this->assertNotNull(VisitRecord::query()->where('booking_id', $this->booking->id)->first());
    }

    public function test_the_pad_offers_the_doctors_own_medicines_as_chips(): void
    {
        tenancy()->initialize($this->tenant);
        Filament::setCurrentPanel(Filament::getPanel('tenantAdmin'));
        $this->actingAs($this->doctor);

        \App\Models\MedicineUsage::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->doctor->id,
            'medicine_name' => 'SERGEL',
            'generic_name' => 'Esomeprazole',
            'last_dose' => '20 mg',
            'last_frequency' => '1+0+0',
            'last_duration' => '1 month',
            'last_timing' => PrescriptionTiming::BEFORE_FOOD,
        ]);

        $mine = Livewire::test(ConsultScreen::class)->instance()->myMedicines;

        // The chip carries his saved line, not the catalogue's — that is the
        // point of him having curated it.
        $this->assertCount(1, $mine);
        $this->assertSame('SERGEL', $mine[0]['brand_name']);
        $this->assertSame('1+0+0', $mine[0]['frequency']);
        $this->assertSame(PrescriptionTiming::BEFORE_FOOD, $mine[0]['timing']);
    }

    public function test_the_chip_strip_is_personal_and_never_shows_another_doctors_list(): void
    {
        tenancy()->initialize($this->tenant);

        $other = User::create([
            'name' => 'Dr Other',
            'email' => 'other@rxpad.test',
            'password' => Hash::make('secret'),
            'role' => User::ROLE_DOCTOR,
            'tenant_id' => $this->tenant->id,
        ]);

        \App\Models\MedicineUsage::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $other->id,
            'medicine_name' => 'THEIRS',
            'generic_name' => 'Something',
        ]);

        Filament::setCurrentPanel(Filament::getPanel('tenantAdmin'));
        $this->actingAs($this->doctor);

        $mine = Livewire::test(ConsultScreen::class)->instance()->myMedicines;

        // Two doctors sharing a clinic prescribe differently; the strip is a
        // personal shortlist, not the chamber's.
        $this->assertSame([], collect($mine)->pluck('brand_name')->intersect(['THEIRS'])->all());
    }

    public function test_hidden_medicines_stay_off_the_chip_strip(): void
    {
        tenancy()->initialize($this->tenant);
        Filament::setCurrentPanel(Filament::getPanel('tenantAdmin'));
        $this->actingAs($this->doctor);

        \App\Models\MedicineUsage::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->doctor->id,
            'medicine_name' => 'DROPPED',
            'generic_name' => 'Something',
            'hidden_at' => now(),
        ]);

        $names = collect(Livewire::test(ConsultScreen::class)->instance()->myMedicines)
            ->pluck('brand_name');

        // Hiding on My medicines is the doctor saying "stop offering me this".
        $this->assertFalse($names->contains('DROPPED'));
    }

    public function test_the_desk_offers_one_my_paper_tick_not_a_second_print_button(): void
    {
        tenancy()->initialize($this->tenant);
        Filament::setCurrentPanel(Filament::getPanel('tenantAdmin'));
        $this->actingAs($this->doctor);

        $html = Livewire::test(ConsultScreen::class)->html();

        $this->assertStringContainsString('cs-rx-desk__my-paper', $html);
        $this->assertStringContainsString(__('My paper'), $html);
        $this->assertStringContainsString('Save &amp; print', $html);
        $this->assertStringNotContainsString('Print on my paper', $html);
    }

    public function test_print_on_my_paper_hides_letterhead_and_keeps_the_medicines(): void
    {
        tenancy()->initialize($this->tenant);
        Filament::setCurrentPanel(Filament::getPanel('tenantAdmin'));
        $this->actingAs($this->doctor);

        Livewire::test(ConsultScreen::class)->call('saveRxDesk', [
            'prescription_items' => [
                ['medicine_name' => 'NAPA', 'dose' => '500 mg'],
            ],
        ]);

        $prescription = VisitRecord::query()
            ->where('booking_id', $this->booking->id)
            ->first()?->prescription;
        $this->assertNotNull($prescription);

        $printUrl = 'http://rx-pad.localhost/prescriptions/'.$prescription->id.'/print';
        tenancy()->end();

        $full = $this->actingAs($this->doctor)->get($printUrl)->assertOk()->getContent();
        $this->assertStringContainsString('<header class="pad-header">', $full);
        $this->assertStringContainsString('B-11223', $full);
        $this->assertStringContainsString('NAPA', $full);

        $onPaper = $this->actingAs($this->doctor)->get($printUrl.'?paper=1')->assertOk()->getContent();
        $this->assertStringNotContainsString('<header class="pad-header">', $onPaper);
        $this->assertStringContainsString('class="pad pad--on-paper"', $onPaper);
        $this->assertStringNotContainsString('B-11223', $onPaper);
        $this->assertStringContainsString('NAPA', $onPaper);
        $this->assertStringContainsString('Aminul Islam', $onPaper);
    }

    public function test_preview_uses_the_same_paper_flag_as_print(): void
    {
        tenancy()->initialize($this->tenant);
        Filament::setCurrentPanel(Filament::getPanel('tenantAdmin'));
        $this->actingAs($this->doctor);

        $page = Livewire::test(ConsultScreen::class)
            ->set('printOnMyPaper', true)
            ->call('saveRxDesk', [
                'prescription_items' => [
                    ['medicine_name' => 'NAPA', 'dose' => '500 mg'],
                ],
            ]);

        $prescription = VisitRecord::query()
            ->where('booking_id', $this->booking->id)
            ->first()?->prescription;
        $this->assertNotNull($prescription);

        $modal = $page->call('mountAction', 'previewPrescription')
            ->instance()
            ->getMountedAction()
            ->getModalContent()
            ->render();

        $this->assertStringContainsString('prescriptions/'.$prescription->id.'/print', $modal);
        $this->assertStringContainsString('paper=1', $modal);
    }

    public function test_the_reason_box_suggests_as_you_type_and_is_labelled_why(): void
    {
        tenancy()->initialize($this->tenant);
        Filament::setCurrentPanel(Filament::getPanel('tenantAdmin'));
        $this->actingAs($this->doctor);

        $html = Livewire::test(ConsultScreen::class)->html();

        $this->assertStringContainsString('Why?', $html);
        $this->assertStringContainsString('searchIndication', $html);
        $this->assertStringContainsString('pickIndication', $html);
        $this->assertStringContainsString('indicationResults', $html);
        $this->assertStringNotContainsString('Indic +', $html);
        $this->assertStringNotContainsString('CLOSE', $html);
    }

    public function test_reason_suggestions_come_from_a_curated_list_not_drug_class_text(): void
    {
        $names = array_map(
            fn (array $row): string => $row['name'],
            \App\Support\IndicationSuggestions::matching('fev')
        );

        $this->assertContains('Fever', $names);
        $this->assertDoesNotMatchRegularExpression(
            '/indicated:|heartburn|chronic/i',
            implode(' ', $names)
        );
    }

    public function test_the_advice_card_offers_common_chips_and_a_save_as_mine(): void
    {
        tenancy()->initialize($this->tenant);
        Filament::setCurrentPanel(Filament::getPanel('tenantAdmin'));
        $this->actingAs($this->doctor);

        $html = Livewire::test(ConsultScreen::class)->html();

        $this->assertStringContainsString('applyAdviceChip', $html);
        $this->assertStringContainsString('saveAdviceAsMine', $html);
        $this->assertStringContainsString(__('Avoid spicy food'), $html);
        $this->assertStringContainsString(__('Drink plenty of water'), $html);
        $this->assertStringContainsString('ঝাল খাবার এড়িয়ে চলুন', $html);
    }

    /**
     * The ★ used to write to this browser's localStorage, so the same doctor
     * had one set of saved advice at the chamber desk and another on his own
     * laptop, and could edit neither. It is now a row on My medicines.
     */
    public function test_saving_advice_as_mine_keeps_it_for_the_doctor_not_the_browser(): void
    {
        tenancy()->initialize($this->tenant);
        Filament::setCurrentPanel(Filament::getPanel('tenantAdmin'));
        $this->actingAs($this->doctor);

        Livewire::test(ConsultScreen::class)
            ->call('saveAdviceAsMine', 'গরম পানি দিয়ে গার্গল করুন');

        $chips = app(\App\Services\DoctorChipService::class)
            ->forDoctor($this->doctor, \App\Models\DoctorChip::KIND_ADVICE);

        $this->assertContains('গরম পানি দিয়ে গার্গল করুন', collect($chips)->pluck('text')->all());

        // …and it is on the pad the next time it renders.
        $this->assertStringContainsString(
            'গরম পানি দিয়ে গার্গল করুন',
            Livewire::test(ConsultScreen::class)->html(),
        );
    }

    /**
     * Advice and History are the doctor's own vocabulary now: a chip he took
     * off on My medicines must not come back on the pad.
     */
    public function test_a_removed_chip_is_gone_from_the_pad(): void
    {
        tenancy()->initialize($this->tenant);
        Filament::setCurrentPanel(Filament::getPanel('tenantAdmin'));
        $this->actingAs($this->doctor);

        app(\App\Services\DoctorChipService::class)
            ->remove($this->doctor, \App\Models\DoctorChip::KIND_HISTORY, 'default:asthma');

        $html = Livewire::test(ConsultScreen::class)->html();

        $this->assertStringNotContainsString('>Asthma', $html);
        $this->assertStringContainsString('HTN', $html);
    }

    public function test_desk_saves_temperature_with_the_pad(): void
    {
        tenancy()->initialize($this->tenant);
        Filament::setCurrentPanel(Filament::getPanel('tenantAdmin'));
        $this->actingAs($this->doctor);

        Livewire::test(ConsultScreen::class)->call('saveRxDesk', [
            'temperature_f' => 100.5,
        ]);

        $visit = VisitRecord::query()->where('booking_id', $this->booking->id)->first();
        $this->assertNotNull($visit);
        $this->assertSame(100.5, $visit->temperature_f);
        $this->assertStringContainsString('100.5', (string) $visit->temperatureLabel());
        $this->assertStringContainsString('Temp', (string) $visit->vitalsSummary());
    }

    public function test_the_desk_offers_finding_chips_that_write_into_other_findings(): void
    {
        tenancy()->initialize($this->tenant);
        Filament::setCurrentPanel(Filament::getPanel('tenantAdmin'));
        $this->actingAs($this->doctor);

        $html = Livewire::test(ConsultScreen::class)->html();

        $this->assertStringContainsString('toggleFinding', $html);
        $this->assertStringContainsString(__('Anaemia'), $html);
        $this->assertStringContainsString(__('Jaundice'), $html);
        $this->assertStringContainsString(__('Lungs clear'), $html);
        $this->assertStringContainsString(__('Abdomen soft'), $html);
        $this->assertStringContainsString(__('Other findings'), $html);
    }

    public function test_history_more_includes_copd_and_allergy(): void
    {
        tenancy()->initialize($this->tenant);
        Filament::setCurrentPanel(Filament::getPanel('tenantAdmin'));
        $this->actingAs($this->doctor);

        $html = Livewire::test(ConsultScreen::class)->html();

        $this->assertStringContainsString('COPD', $html);
        $this->assertStringContainsString('Allergy', $html);
        $this->assertMatchesRegularExpression(
            '/historyPrimary[^]]*HTN[^]]*DM[^]]*Asthma/',
            $html
        );
        $this->assertDoesNotMatchRegularExpression(
            '/historyPrimary[^]]*(COPD|Allergy)/',
            $html
        );
    }

    public function test_the_desk_has_a_drag_handle_and_keeps_arrow_reorder(): void
    {
        tenancy()->initialize($this->tenant);
        Filament::setCurrentPanel(Filament::getPanel('tenantAdmin'));
        $this->actingAs($this->doctor);

        $html = Livewire::test(ConsultScreen::class)->html();

        $this->assertStringContainsString('cs-rx-desk__grip', $html);
        $this->assertStringContainsString('dropRow', $html);
        $this->assertStringContainsString('moveItem', $html);
        $this->assertStringContainsString(__('Move up'), $html);
        $this->assertStringContainsString(__('Move down'), $html);
    }

    public function test_reports_live_on_the_left_and_voice_photo_is_off_the_pad(): void
    {
        tenancy()->initialize($this->tenant);
        Filament::setCurrentPanel(Filament::getPanel('tenantAdmin'));
        $this->actingAs($this->doctor);

        $html = Livewire::test(ConsultScreen::class)->html();

        $this->assertStringContainsString('cs-rx-desk__reports-card', $html);
        $this->assertStringContainsString('uploadReportPhoto', $html);
        $this->assertStringContainsString(__('Reports the patient brought'), $html);
        $this->assertStringNotContainsString('Voice / photo', $html);

        $desk = file_get_contents(resource_path('views/filament/tenant-admin/components/rx-desk.blade.php'));
        $left = Str::before($desk, 'cs-rx-desk__right');
        $right = Str::after($desk, 'cs-rx-desk__right');
        $this->assertStringContainsString('cs-rx-desk__reports-card', $left);
        $this->assertStringNotContainsString('cs-rx-desk__reports-card', $right);
        $this->assertStringNotContainsString("mountAction('writePrescription')", $desk);
    }

    public function test_desk_saves_report_photos_with_the_pad(): void
    {
        tenancy()->initialize($this->tenant);
        Filament::setCurrentPanel(Filament::getPanel('tenantAdmin'));
        $this->actingAs($this->doctor);

        $path = 'visit-reports/'.$this->tenant->id.'/cbc.jpg';
        \Illuminate\Support\Facades\Storage::disk('local')->put($path, 'cbc-bytes');

        Livewire::test(ConsultScreen::class)->call('saveRxDesk', [
            'reports_seen' => 'CBC from Popular',
            'report_photos' => [$path],
        ]);

        $visit = VisitRecord::query()->where('booking_id', $this->booking->id)->first();
        $this->assertNotNull($visit);
        $this->assertSame('CBC from Popular', $visit->reports_seen);
        $this->assertSame([$path], $visit->report_photo_paths);
    }

    public function test_desk_rejects_report_photos_outside_this_practice_directory(): void
    {
        tenancy()->initialize($this->tenant);
        Filament::setCurrentPanel(Filament::getPanel('tenantAdmin'));
        $this->actingAs($this->doctor);

        Livewire::test(ConsultScreen::class)->call('saveRxDesk', [
            'reports_seen' => 'CBC',
            'report_photos' => ['visit-photos/'.$this->tenant->id.'/slip.jpg'],
        ]);

        $visit = VisitRecord::query()->where('booking_id', $this->booking->id)->first();
        $this->assertNotNull($visit);
        $this->assertSame('CBC', $visit->reports_seen);
        $this->assertNull($visit->report_photo_paths);
    }

    public function test_the_pad_saves_itself_so_complete_visit_cannot_close_on_an_unwritten_script(): void
    {
        tenancy()->initialize($this->tenant);
        Filament::setCurrentPanel(Filament::getPanel('tenantAdmin'));
        $this->actingAs($this->doctor);

        // The doctor types; nobody has pressed Save & print. This is the write
        // the desk now makes on its own, a second after they stop.
        Livewire::test(ConsultScreen::class)->call('autosaveRxDesk', [
            'chief_complaint' => 'Fever 3d',
            'prescription_items' => [
                ['medicine_name' => 'NAPA', 'dose' => '500 mg', 'frequency' => '1+0+1', 'duration' => '5 days'],
            ],
        ]);

        $visit = VisitRecord::query()->where('booking_id', $this->booking->id)->first();
        $this->assertNotNull($visit, 'A draft save must reach the database, or Complete visit reads nothing.');
        $this->assertSame('Fever 3d', $visit->chief_complaint);
        $this->assertSame('NAPA', $visit->prescription->items->first()->medicine_name);

        // And it must not have closed the visit — the doctor is still talking.
        $this->booking->refresh();
        $this->assertSame('in_chamber', $this->booking->status);
    }

    public function test_a_draft_save_is_silent_while_an_explicit_save_still_speaks(): void
    {
        tenancy()->initialize($this->tenant);
        Filament::setCurrentPanel(Filament::getPanel('tenantAdmin'));
        $this->actingAs($this->doctor);

        $items = [
            'prescription_items' => [
                ['medicine_name' => 'NAPA', 'dose' => '500 mg'],
            ],
        ];

        // A toast every couple of seconds trains the doctor to ignore toasts —
        // including the safety warnings that ride the same channel.
        Livewire::test(ConsultScreen::class)->call('autosaveRxDesk', $items);
        Notification::assertNotNotified(__('Prescription saved'));

        Livewire::test(ConsultScreen::class)->call('saveRxDesk', $items);
        Notification::assertNotified(__('Prescription saved'));
    }

    public function test_a_draft_save_refuses_everything_an_explicit_save_refuses(): void
    {
        tenancy()->initialize($this->tenant);
        Filament::setCurrentPanel(Filament::getPanel('tenantAdmin'));
        $this->actingAs($this->doctor);

        $this->booking->update(['status' => 'completed']);

        Livewire::test(ConsultScreen::class)->call('autosaveRxDesk', [
            'prescription_items' => [
                ['medicine_name' => 'SNUCK IN', 'dose' => '500 mg'],
            ],
        ]);

        $this->assertNull(
            VisitRecord::query()->where('booking_id', $this->booking->id)->first(),
            'Autosave must not be a way around the in_chamber gate.',
        );
    }

    public function test_the_pad_is_not_keyed_on_the_record_timestamp(): void
    {
        tenancy()->initialize($this->tenant);
        Filament::setCurrentPanel(Filament::getPanel('tenantAdmin'));
        $this->actingAs($this->doctor);

        // The key used to carry the visit record's updated_at, so any write to
        // that row — the desk's own save, a staff paper entry, a follow-up
        // stamp — remounted the component on the next 3s poll and threw away
        // whatever the doctor had typed and not yet saved.
        $html = Livewire::test(ConsultScreen::class)->html();

        $this->assertStringContainsString('wire:key="rx-desk-'.$this->booking->id.'"', $html);

        $desk = file_get_contents(resource_path('views/filament/tenant-admin/components/rx-desk.blade.php'));
        $this->assertStringNotContainsString(
            'rx-desk-{{ $booking?->id }}-',
            $desk,
            'The pad must not be keyed on anything that changes while the doctor is typing.',
        );

        // And the pad must actually keep the server in step, which is what
        // makes dropping the remount safe.
        $this->assertStringContainsString('x-effect="queueDraftSave()"', $desk);
        $this->assertStringContainsString('flushIfClickedAway($event)', $desk);
        $this->assertStringContainsString('autosaveRxDesk', $desk);
    }

    public function test_the_pad_is_never_morphed_out_from_under_alpine(): void
    {
        tenancy()->initialize($this->tenant);
        Filament::setCurrentPanel(Filament::getPanel('tenantAdmin'));
        $this->actingAs($this->doctor);

        // Dropping updated_at from the key was necessary but not sufficient,
        // and on its own it made the pad worse. That timestamp was also what
        // made the post-save remount *clean*: a changed key replaces the
        // element, so Alpine re-initialises consistently. With a stable key
        // Livewire morphs instead, and `x-data="rxDesk({...})"` is rendered
        // from the record — so its attribute string changes after every save
        // and re-runs init against nodes whose effects are already torn down.
        // Every x-show on the pad then goes dead: the complaint picker, the
        // brand suggestions, the timing chips. Found in the browser; no test
        // here executes Alpine, so these two guards stand in for it.
        $html = Livewire::test(ConsultScreen::class)->html();
        $this->assertMatchesRegularExpression(
            '/<div\s+class="cs-rx-desk"\s+wire:key="rx-desk-[^"]+"\s+wire:ignore/',
            $html,
            'The pad must be wire:ignore — Alpine owns this subtree outright.',
        );

        // The saves that used to drive those re-renders must not render either.
        $page = new \ReflectionMethod(ConsultScreen::class, 'saveRxDesk');
        $this->assertNotEmpty(
            $page->getAttributes(\Livewire\Attributes\Renderless::class),
            'saveRxDesk must be #[Renderless].',
        );
        $auto = new \ReflectionMethod(ConsultScreen::class, 'autosaveRxDesk');
        $this->assertNotEmpty(
            $auto->getAttributes(\Livewire\Attributes\Renderless::class),
            'autosaveRxDesk must be #[Renderless].',
        );
    }

    public function test_the_pad_says_out_loud_whether_it_has_been_saved(): void
    {
        tenancy()->initialize($this->tenant);
        Filament::setCurrentPanel(Filament::getPanel('tenantAdmin'));
        $this->actingAs($this->doctor);

        $html = Livewire::test(ConsultScreen::class)->html();

        // "It saves automatically" is only trustworthy if the screen says so.
        $this->assertStringContainsString('cs-rx-desk__save-state', $html);
        $this->assertStringContainsString(__('Unsaved'), $html);
        $this->assertStringContainsString(__('Saved'), $html);

        $css = file_get_contents(resource_path('css/filament/tenantAdmin/theme.css'));
        $this->assertStringContainsString('.cs-rx-desk__save-state.is-unsaved', $css);

        // A failed save must fall back to Unsaved before the outbox is touched.
        // Doing it the other way round left the badge stuck on "Saving…" when
        // enqueue threw — caught in the browser against a server that was
        // rejecting the write, which is exactly when the doctor is relying on
        // the badge. The offline enqueue is best-effort and must not be able to
        // swallow the state change.
        $desk = file_get_contents(resource_path('views/filament/tenant-admin/components/rx-desk.blade.php'));
        $catch = mb_substr($desk, (int) mb_strpos($desk, '} catch (e) {', (int) mb_strpos($desk, 'async flush()')));
        $unsavedAt = mb_strpos($catch, "this.saveState = 'unsaved';");
        $enqueueAt = mb_strpos($catch, 'ChamberQOffline.enqueue');

        $this->assertNotFalse($unsavedAt);
        $this->assertNotFalse($enqueueAt);
        $this->assertLessThan(
            $enqueueAt,
            $unsavedAt,
            'flush() must say Unsaved before it tries the offline outbox.',
        );
    }

    public function test_the_desk_can_say_three_months_or_pick_a_date(): void
    {
        tenancy()->initialize($this->tenant);
        Filament::setCurrentPanel(Filament::getPanel('tenantAdmin'));
        $this->actingAs($this->doctor);

        // The phone modal has always had both. A doctor wanting six weeks or a
        // yearly review had to leave the desk to say so.
        $html = Livewire::test(ConsultScreen::class)->html();
        $this->assertStringContainsString(__('3 months'), $html);
        $this->assertStringContainsString(__('Pick a date'), $html);

        Livewire::test(ConsultScreen::class)->call('saveRxDesk', [
            'follow_up_relative' => 'pick_date',
            'follow_up_date' => today()->addDays(45)->toDateString(),
            'prescription_items' => [['medicine_name' => 'NAPA']],
        ]);

        $visit = VisitRecord::query()->where('booking_id', $this->booking->id)->first();
        $this->assertNotNull($visit);
        $this->assertSame(
            today()->addDays(45)->toDateString(),
            $visit->follow_up_date?->toDateString(),
            'A hand-picked date must survive the pad, not be rounded to a chip.',
        );
    }

    public function test_the_pad_is_available_on_a_tablet_not_only_a_desktop(): void
    {
        // Below the desk's breakpoint everything fell back to the phone modal,
        // which has no shorthand line, no My medicines, no packs and none of
        // the complaint / history / investigation chips. A tablet on the
        // consult desk is an ordinary chamber setup.
        $page = file_get_contents(resource_path('views/filament/tenant-admin/pages/consult-screen.blade.php'));

        $this->assertMatchesRegularExpression(
            '/@media \(min-width: 768px\) \{\s*\.cs-rx-desk-shell\.is-active \{ display: block; \}/',
            $page,
            'The desk must turn on at the same 768px the rest of this page already uses.',
        );
        $this->assertStringNotContainsString('.cs-rx-desk-shell.is-active { display: block; }'."\n".'            .cs-layout.is-desk-active { display: none !important; }'."\n".'        }'."\n".'    </style>', $page);

        // Stacked below 1024px, so the medicine table gets the full width
        // rather than 66% of a narrow screen.
        $css = file_get_contents(resource_path('css/filament/tenantAdmin/theme.css'));
        $this->assertMatchesRegularExpression(
            '/@media \(max-width: 1023px\) \{[^@]*\.cs-rx-desk__grid \{ grid-template-columns: minmax\(0, 1fr\); \}/s',
            $css,
        );

        // A horizontal scroller here would clip the brand suggestion dropdown;
        // that is why the columns stack instead.
        $this->assertMatchesRegularExpression(
            '/\.cs-rx-desk__table-wrap \{ overflow: visible; \}/',
            $css,
        );
    }
}
