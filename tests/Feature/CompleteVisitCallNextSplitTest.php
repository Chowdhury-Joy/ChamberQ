<?php

namespace Tests\Feature;

use App\Filament\TenantAdmin\Pages\ConsultScreen;
use App\Filament\TenantAdmin\Support\VisitNotesFormSchema;
use App\Models\Booking;
use App\Models\Chamber;
use App\Models\Doctor;
use App\Models\Domain;
use App\Models\Medicine;
use App\Models\LiveSession;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\ScheduleSession;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The doctor writes the prescription, hands it over, and only then calls the
 * next patient — so completing a visit must not advance the queue by itself.
 */
class CompleteVisitCallNextSplitTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $doctor;

    private LiveSession $liveSession;

    private Booking $first;

    private Booking $second;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['id' => 'split-visit', 'plan_tier' => 'solo', 'queue_runner' => 'doctor']);
        Domain::create(['domain' => 'split-visit.localhost', 'tenant_id' => $this->tenant->id]);

        tenancy()->initialize($this->tenant);

        $this->doctor = User::create([
            'name' => 'Dr Split',
            'email' => 'doc@split.test',
            'password' => Hash::make('secret'),
            'role' => User::ROLE_DOCTOR,
            'tenant_id' => $this->tenant->id,
        ]);

        $chamber = Chamber::create(['name' => 'Main', 'address' => 'Dhaka']);
        $doctorProfile = Doctor::create([
            'name' => 'Dr Split',
            'registration_number' => 'B-99887',
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
            'name' => 'Rahim Mia',
            'phone' => '01712345601',
            'age' => 35,
            'age_recorded_at' => today(),
            'sex' => 'male',
        ]);

        $this->first = Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $session->id,
            'booking_date' => today(),
            'patient_id' => $patient->id,
            'patient_name' => $patient->name,
            'patient_phone' => $patient->phone,
            'serial_number' => 1,
            'status' => 'in_chamber',
            'in_chamber_at' => now(),
        ]);

        $this->second = Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $session->id,
            'booking_date' => today(),
            'patient_name' => 'Nasreen Akhtar',
            'patient_phone' => '01712345602',
            'serial_number' => 2,
            'status' => 'waiting',
        ]);

        $this->liveSession = LiveSession::create([
            'schedule_session_id' => $session->id,
            'session_date' => Carbon::today(),
            'status' => 'active',
            'started_at' => now(),
            'current_booking_id' => $this->first->id,
        ]);

        Medicine::create([
            'brand_name' => 'SERGEL',
            'generic_name' => 'Esomeprazole',
            'default_strength' => '40 mg',
            'form' => 'capsule',
            'aliases' => ['sergel'],
            'category' => 'GI',
            'practice_types' => [Doctor::PRACTICE_GENERAL],
        ]);
        Medicine::create([
            'brand_name' => 'NAPA',
            'generic_name' => 'Paracetamol',
            'default_strength' => '500 mg',
            'form' => 'tablet',
            'aliases' => ['napa'],
            'category' => 'Analgesic',
            'practice_types' => [Doctor::PRACTICE_GENERAL],
        ]);
        Medicine::create([
            'brand_name' => 'OMEPRAZOLE',
            'generic_name' => 'Omeprazole',
            'default_strength' => '20 mg',
            'form' => 'capsule',
            'aliases' => ['omeprazole'],
            'category' => 'GI',
            'practice_types' => [Doctor::PRACTICE_GENERAL],
        ]);

        tenancy()->end();
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    private function actingAsDoctorOnPanel(): void
    {
        tenancy()->initialize($this->tenant);
        Filament::setCurrentPanel(Filament::getPanel('tenantAdmin'));
        $this->actingAs($this->doctor);
    }

    public function test_complete_visit_saves_prescription_without_advancing_the_queue(): void
    {
        $this->actingAsDoctorOnPanel();

        Livewire::test(ConsultScreen::class)
            ->callAction('completeVisit', data: [
                'diagnosis' => VisitNotesFormSchema::FREE_DIAGNOSIS_PREFIX.'Acidity',
                'prescription_items' => [
                    ['medicine_name' => 'OMEPRAZOLE', 'dose' => '20 mg', 'frequency' => '1+1+1', 'duration' => '7 days'],
                ],
            ]);

        $this->first->refresh();
        $this->liveSession->refresh();

        $this->assertSame('completed', $this->first->status);
        $this->assertSame($this->first->id, $this->liveSession->current_booking_id, 'Queue must not advance on complete.');
        $this->assertSame('waiting', $this->second->fresh()->status);

        $prescription = Prescription::query()->first();
        $this->assertNotNull($prescription, 'Prescription should have been saved.');
        // Brand names are stored uppercase so they read like a paper script.
        $this->assertSame('OMEPRAZOLE', $prescription->items->first()?->medicine_name);
    }

    public function test_call_next_advances_only_when_pressed(): void
    {
        $this->actingAsDoctorOnPanel();

        Livewire::test(ConsultScreen::class)
            ->callAction('completeVisit', data: [])
            ->callAction('callNext');

        $this->liveSession->refresh();

        $this->assertSame($this->second->id, $this->liveSession->current_booking_id);
        $this->assertSame('called', $this->second->fresh()->status);
    }

    public function test_call_next_is_hidden_mid_consult_and_offered_once_the_visit_is_closed(): void
    {
        $this->actingAsDoctorOnPanel();

        // Patient still in the chamber — advancing now would cut the consult short.
        Livewire::test(ConsultScreen::class)
            ->assertActionVisible('completeVisit')
            ->assertActionHidden('callNext');

        $this->first->update(['status' => 'completed', 'completed_at' => now()]);

        Livewire::test(ConsultScreen::class)
            ->assertActionHidden('completeVisit')
            ->assertActionVisible('callNext');
    }

    public function test_prescription_can_be_written_mid_consult_without_ending_the_visit(): void
    {
        $this->actingAsDoctorOnPanel();

        Livewire::test(ConsultScreen::class)
            ->assertSee('Nothing written yet')
            ->callAction('writePrescription', data: [
                'diagnosis' => VisitNotesFormSchema::FREE_DIAGNOSIS_PREFIX.'Acidity',
                'prescription_items' => [
                    ['medicine_name' => 'SERGEL', 'dose' => 'other', 'dose_other' => '20 mg', 'frequency' => '1+0+1', 'duration' => '14 days'],
                ],
            ]);

        $this->first->refresh();
        $this->liveSession->refresh();

        // Patient is still with the doctor — writing must not end anything.
        $this->assertSame('in_chamber', $this->first->status);
        $this->assertSame($this->first->id, $this->liveSession->current_booking_id);
        $this->assertSame('SERGEL', Prescription::query()->first()?->items->first()?->medicine_name);
    }

    public function test_reopening_the_prescription_shows_what_was_already_written(): void
    {
        $this->actingAsDoctorOnPanel();

        Livewire::test(ConsultScreen::class)
            ->callAction('writePrescription', data: [
                'diagnosis' => VisitNotesFormSchema::FREE_DIAGNOSIS_PREFIX.'Acidity',
                'advice' => 'Avoid spicy food',
                'prescription_items' => [
                    ['medicine_name' => 'SERGEL', 'dose' => 'other', 'dose_other' => '20 mg', 'frequency' => '1+0+1', 'duration' => '14 days'],
                ],
            ]);

        // Reopen: the form must come back filled, not blank, or the doctor
        // would silently wipe the medicine by saving again.
        Livewire::test(ConsultScreen::class)
            ->mountAction('writePrescription')
            ->assertSchemaStateSet([
                'diagnosis' => VisitNotesFormSchema::FREE_DIAGNOSIS_PREFIX.'Acidity',
                'advice' => 'Avoid spicy food',
            ]);
    }

    /**
     * The repeater posts its whole list on submit, so re-saving must replace
     * what is stored rather than append — otherwise every edit during a consult
     * would duplicate the medicines already prescribed.
     */
    public function test_editing_replaces_the_medicine_list_instead_of_appending(): void
    {
        tenancy()->initialize($this->tenant);
        $service = app(\App\Services\VisitRecordService::class);

        $service->saveForCompletedBooking($this->first, $this->doctor, [
            'prescription_items' => [
                ['medicine_name' => 'SERGEL', 'dose' => '20mg', 'frequency' => '1+0+1', 'duration' => '14 days'],
            ],
        ]);

        // Doctor reopens and adds the medicine the patient mentioned late.
        $service->saveForCompletedBooking($this->first, $this->doctor, [
            'prescription_items' => [
                ['medicine_name' => 'SERGEL', 'dose' => '20mg', 'frequency' => '1+0+1', 'duration' => '14 days'],
                ['medicine_name' => 'NAPA', 'dose' => '500 mg', 'frequency' => '1+1+1', 'duration' => '3 days'],
            ],
        ]);

        $items = Prescription::query()->first()?->items;

        $this->assertCount(2, $items, 'Editing must not duplicate or drop medicines.');
        $this->assertSame(['SERGEL', 'NAPA'], $items->pluck('medicine_name')->all());
        $this->assertSame(1, Prescription::query()->count(), 'Re-saving must reuse the same prescription.');
        $this->assertSame(1, \App\Models\VisitRecord::query()->count(), 'Re-saving must reuse the same visit record.');

        // And removing one on a later edit must actually remove it.
        $service->saveForCompletedBooking($this->first, $this->doctor, [
            'prescription_items' => [
                ['medicine_name' => 'NAPA', 'dose' => '500 mg', 'frequency' => '1+1+1', 'duration' => '3 days'],
            ],
        ]);

        $this->assertSame(['NAPA'], Prescription::query()->first()?->items->pluck('medicine_name')->all());
    }

    public function test_completing_after_writing_keeps_the_prescription(): void
    {
        $this->actingAsDoctorOnPanel();

        Livewire::test(ConsultScreen::class)
            ->callAction('writePrescription', data: [
                'prescription_items' => [
                    ['medicine_name' => 'SERGEL', 'dose' => 'other', 'dose_other' => '20 mg', 'frequency' => '1+0+1', 'duration' => '14 days'],
                ],
            ])
            // Complete carries the written notes in, so finishing does not blank them.
            ->callAction('completeVisit', data: VisitNotesFormSchema::stateFromRecord(
                \App\Models\VisitRecord::query()->where('booking_id', $this->first->id)->first()
            ));

        $this->first->refresh();

        $this->assertSame('completed', $this->first->status);
        $this->assertSame(1, Prescription::query()->count());
        $this->assertSame('SERGEL', Prescription::query()->first()?->items->first()?->medicine_name);
    }

    /**
     * `->fillForm()` replaces Filament's normal default-value hydration, so the
     * disabled instructional field (which relies on `->default()`) went blank
     * the first time this action opened for a patient with nothing written yet.
     */
    public function test_write_prescription_form_shows_its_instructional_hint(): void
    {
        $this->actingAsDoctorOnPanel();

        Livewire::test(ConsultScreen::class)
            ->mountAction('writePrescription')
            ->assertSchemaStateSet([
                '_visit_notes_hint' => 'All fields are optional — leave blank to complete without notes.',
            ]);
    }

    /**
     * Saving advice with no medicines is notes, not a prescription — the card
     * must not claim there is one to "edit" until a medicine actually exists.
     */
    public function test_advice_alone_is_labelled_as_notes_not_a_prescription(): void
    {
        $this->actingAsDoctorOnPanel();

        Livewire::test(ConsultScreen::class)
            ->callAction('writePrescription', data: ['advice' => 'Drink more water'])
            ->assertSee('Notes so far')
            ->assertSee('Write prescription')
            ->assertDontSee('Edit prescription')
            ->assertDontSee('Prescription so far');

        $this->assertSame(0, Prescription::query()->count());
    }

    public function test_screen_can_warn_when_nobody_has_been_called_yet(): void
    {
        $this->actingAsDoctorOnPanel();

        Livewire::test(ConsultScreen::class)
            ->callAction('completeVisit', data: [])
            // The count runs client-side (Live Queue Control does not poll), so
            // assert the wiring is present with the agreed 30s threshold.
            ->assertSee('Visit completed — ready for next patient')
            ->assertSee('Nobody called yet')
            ->assertSee(Booking::CALL_NEXT_NUDGE_SECONDS.' * 1000', escape: false);
    }

    public function test_print_and_send_appear_while_the_patient_is_still_in_the_room(): void
    {
        $this->actingAsDoctorOnPanel();

        Livewire::test(ConsultScreen::class)
            ->callAction('completeVisit', data: [
                'prescription_items' => [
                    ['medicine_name' => 'NAPA', 'dose' => '500 mg', 'frequency' => '1+1+1', 'duration' => '5 days'],
                ],
            ])
            ->assertSee('Visit completed')
            ->assertSee('Print prescription')
            ->assertSee('Send via WhatsApp');
    }

    /**
     * Completing a visit must not build a behavioural profile of the doctor.
     *
     * Automatic learning was removed on 2026-08-11 by owner decision: doctors
     * curate **My medicines** explicitly, and the app does not infer a
     * shortlist from what gets prescribed. The prescription itself is still
     * saved — only the side-effect writes are gone.
     */
    public function test_completing_a_visit_does_not_record_medicine_usage(): void
    {
        $this->actingAsDoctorOnPanel();

        Livewire::test(ConsultScreen::class)
            ->callAction('completeVisit', data: [
                'prescription_items' => [
                    ['medicine_name' => 'SERGEL', 'dose' => '40 mg', 'frequency' => '1+0+1', 'duration' => '14 days'],
                ],
            ]);

        $this->assertSame('completed', $this->first->fresh()->status);

        // The prescription is still written — this is not a regression in
        // saving, only in silently learning.
        $this->assertDatabaseHas('prescription_items', ['medicine_name' => 'SERGEL']);

        $this->assertSame(
            0,
            \App\Models\MedicineUsage::query()->count(),
            'Prescribing must not add anything to the doctor\'s personal list',
        );
    }

    /**
     * Filling in a medicine and pressing "Add medicine" must fold the finished
     * row away, or the doctor scrolls past every drug already prescribed to
     * reach the empty one.
     *
     * `collapsed()` alone cannot do this: it only seeds an item's initial Alpine
     * state, and Livewire's morph preserves the client-side state of rows that
     * are already mounted. So the button also dispatches Filament's own
     * `repeater-collapse` event, which mounted rows do listen to.
     *
     * This asserts the rendered attributes because both plausible spellings fail
     * silently and look correct in review:
     *   - `x-on:click` in extraAttributes is dropped — Action seeds that key into
     *     the bag itself, and the bag wins over merged extra attributes.
     *   - `alpineClickHandler()` replaces `wire:click`, leaving a button that
     *     collapses the list but never adds a row.
     */
    public function test_add_medicine_collapses_finished_rows_and_still_adds_one(): void
    {
        tenancy()->initialize($this->tenant);
        $this->actingAs($this->doctor);

        $schema = \Filament\Schemas\Schema::make(new ConsultScreen())
            ->statePath('data')
            ->components(VisitNotesFormSchema::components());

        $repeater = null;
        foreach ($schema->getFlatComponents(withHidden: true) as $component) {
            if ($component instanceof \Filament\Forms\Components\Repeater
                && $component->getName() === 'prescription_items') {
                $repeater = $component;

                break;
            }
        }

        $this->assertNotNull($repeater, 'The prescription medicine repeater was not found.');

        $addAction = $repeater->getAction('add');

        // Mirrors Action::toButtonHtml(): the bag is seeded first, then extra
        // attributes are merged over it. Asserting on the merged result is the
        // only way to catch a key that the bag silently swallows.
        $rendered = (new \Filament\Support\View\ComponentAttributeBag([
            'wire:click' => $addAction->getLivewireClickHandler(),
            'x-on:click' => $addAction->getAlpineClickHandler(),
        ]))->merge($addAction->getExtraAttributes(), escape: false)->toHtml();

        $this->assertStringContainsString('repeater-collapse', $rendered);
        $this->assertStringContainsString($repeater->getStatePath(), $rendered);
        // The row must still actually be added.
        $this->assertStringContainsString('mountAction', $rendered);
    }

    /**
     * Collapsing the rows above shrinks the page under the doctor's scroll
     * position, so the new empty row can land off-screen and they have to
     * scroll back up to type — the exact complaint the collapse was meant to
     * fix. The add action therefore announces itself once the row exists, and
     * the repeater listens for that and scrolls the new row into view.
     *
     * Asserted end to end (row really appended, event really dispatched)
     * because the browser calls this action with a schema *key*
     * (`mountedActionSchema0.…`), not the state path — pass the wrong one and
     * the call silently succeeds while adding nothing.
     */
    public function test_adding_a_medicine_announces_itself_so_the_new_row_is_scrolled_to(): void
    {
        tenancy()->initialize($this->tenant);
        $this->actingAs($this->doctor);
        Filament::setCurrentPanel(Filament::getPanel('tenantAdmin'));

        $component = Livewire::test(ConsultScreen::class)
            ->mountAction('writePrescription')
            ->call('mountAction', 'add', [], [
                'schemaComponent' => 'mountedActionSchema0.prescription_items',
            ]);

        $this->assertCount(
            1,
            $component->get('mountedActions')[0]['data']['prescription_items'] ?? [],
            'Add medicine did not append a row.',
        );

        $component->assertDispatched(VisitNotesFormSchema::MEDICINE_ADDED_EVENT);

        // …and the repeater is actually listening for it.
        $schema = \Filament\Schemas\Schema::make(new ConsultScreen())
            ->statePath('data')
            ->components(VisitNotesFormSchema::components());

        $repeater = null;
        foreach ($schema->getFlatComponents(withHidden: true) as $child) {
            if ($child instanceof \Filament\Forms\Components\Repeater
                && $child->getName() === 'prescription_items') {
                $repeater = $child;

                break;
            }
        }

        $this->assertArrayHasKey(
            'x-on:'.VisitNotesFormSchema::MEDICINE_ADDED_EVENT.'.window',
            $repeater->getExtraAttributes(),
            'Nothing reacts to the event, so the new row is never scrolled to.',
        );

        // theme.css greys written-up (collapsed) rows via this class. Without it
        // the rule silently matches nothing and every row looks identical again.
        $this->assertStringContainsString(
            'cs-rx-medicines',
            $repeater->getExtraAttributes()['class'] ?? '',
            'The medicine repeater lost the class theme.css styles collapsed rows through.',
        );
    }
}
