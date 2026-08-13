<?php

namespace Tests\Feature;

use App\Filament\TenantAdmin\Pages\ConsultScreen;
use App\Models\Booking;
use App\Models\Chamber;
use App\Models\Doctor;
use App\Models\Domain;
use App\Models\LiveSession;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\ScheduleSession;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VisitRecord;
use App\Services\VisitRecordService;
use App\Support\PrescriptionTiming;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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
            ->assertSee('খাবারের পর');
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
                'diagnosis' => \App\Filament\TenantAdmin\Support\VisitNotesFormSchema::FREE_DIAGNOSIS_PREFIX.'Migraine',
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
            ->assertSeeHtml('Save &amp; print')
            // The seed reaches the browser as Alpine config, not as a value
            // already written to the record.
            ->assertSeeHtml('HTN, DM');
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

        // Both the page header action and the desk's sticky bar render a
        // Complete visit button, so at desk widths the doctor saw two
        // identical green buttons. The desk bar is the one that stays — it
        // sits with Preview / Save & print / Save only, in work order.
        $this->assertStringContainsString('cs-rx-desk__bar-actions', $html);
        $this->assertStringContainsString(
            '.fi-header-actions-ctn { display: none; }',
            $html,
            'The header copy must be hidden whenever the desk is on screen',
        );
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

        $usage = \App\Models\MedicineUsage::query()->where('user_id', $this->doctor->id)->firstOrFail();

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

        $warnings = \App\Support\RxSafety::allWarnings(
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
}
