<?php

namespace Tests\Feature;

use App\Filament\TenantAdmin\Support\VisitNotesFormSchema;
use App\Models\Doctor;
use App\Models\Domain;
use App\Models\Medicine;
use App\Models\MedicineUsage;
use App\Models\Tenant;
use App\Models\User;
use App\Services\MedicineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MedicinePickerTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $doctor;

    private MedicineService $medicineService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['id' => 'medicine-picker', 'plan_tier' => 'solo']);
        Domain::create(['domain' => 'medicine-picker.localhost', 'tenant_id' => $this->tenant->id]);

        tenancy()->initialize($this->tenant);

        $this->doctor = User::create([
            'name' => 'Dr Picker',
            'email' => 'doc@picker.test',
            'password' => Hash::make('secret'),
            'role' => User::ROLE_DOCTOR,
            'tenant_id' => $this->tenant->id,
        ]);

        Medicine::create([
            'brand_name' => 'NAPA',
            'generic_name' => 'Paracetamol',
            'default_strength' => '500 mg',
            'form' => 'tablet',
            'aliases' => ['napa', 'paracetamol'],
            'category' => 'Analgesic',
        ]);

        Medicine::create([
            'brand_name' => 'SERGEL',
            'generic_name' => 'Esomeprazole',
            'default_strength' => '40 mg',
            'form' => 'capsule',
            'aliases' => ['sergel'],
            'category' => 'GI',
        ]);

        $this->medicineService = app(MedicineService::class);

        tenancy()->end();
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    public function test_search_returns_catalog_matches_with_prefill_payload(): void
    {
        tenancy()->initialize($this->tenant);

        $results = $this->medicineService->search('napa', $this->doctor);

        $this->assertTrue($results->contains(fn (array $row) => $row['brand_name'] === 'NAPA'));
        $napa = $results->first(fn (array $row) => $row['brand_name'] === 'NAPA');
        $this->assertSame('Paracetamol', $napa['generic_name']);
        $this->assertSame('500 mg', $napa['dose']);
    }

    public function test_doctor_usage_boosts_ranking(): void
    {
        tenancy()->initialize($this->tenant);

        MedicineUsage::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->doctor->id,
            'medicine_name' => 'SERGEL',
            'generic_name' => 'Esomeprazole',
            'last_dose' => '40 mg',
            'last_frequency' => '1+0+1',
            'last_duration' => '14 days',
            'use_count' => 12,
            'last_used_at' => now(),
        ]);

        $results = $this->medicineService->search('se', $this->doctor);

        $this->assertSame('SERGEL', $results->first()['brand_name']);
    }

    /**
     * My medicines is an explicit list: the doctor saves a brand and its
     * defaults, and saving again just updates them. Nothing counts how often a
     * medicine gets prescribed — automatic learning was removed 2026-08-11.
     */
    public function test_saving_a_doctor_medicine_stores_defaults_without_counting_use(): void
    {
        tenancy()->initialize($this->tenant);

        $saved = $this->medicineService->saveDoctorMedicine($this->doctor, [
            'medicine_name' => 'sergel',
            'generic_name' => 'Esomeprazole',
            'dose' => '40 mg',
            'frequency' => '1+0+1',
            'duration' => '14 days',
        ]);

        $this->assertSame('SERGEL', $saved->medicine_name);
        $this->assertSame('40 mg', $saved->last_dose);
        $this->assertNull($saved->last_used_at, 'Saving a preference is not a prescription event');

        $this->medicineService->saveDoctorMedicine($this->doctor, [
            'medicine_name' => 'SERGEL',
            'dose' => '20 mg',
        ]);

        $again = $saved->fresh();
        $this->assertSame('20 mg', $again->last_dose, 'Re-saving updates the default');
        $this->assertSame(1, MedicineUsage::query()->count(), 'and does not duplicate the row');
        // The retired learning counters keep their schema defaults; nothing
        // increments or stamps them any more.
        $this->assertSame(0, $again->use_count);
        $this->assertNull($again->last_used_at);
    }

    public function test_api_search_requires_doctor_login(): void
    {
        $url = 'http://medicine-picker.localhost/api/medicines/search?q=napa';

        $this->getJson($url)->assertUnauthorized();

        tenancy()->initialize($this->tenant);

        $this->actingAs($this->doctor)
            ->getJson($url)
            ->assertOk()
            ->assertJsonPath('results.0.brand_name', 'NAPA');
    }

    public function test_search_filters_by_doctor_practice_type(): void
    {
        tenancy()->initialize($this->tenant);

        Medicine::create([
            'brand_name' => 'ORALDYNE',
            'generic_name' => 'Chlorhexidine',
            'default_strength' => '0.12%',
            'form' => 'mouthwash',
            'aliases' => ['oraldyne'],
            'category' => 'Dental',
            'practice_types' => [Doctor::PRACTICE_DENTIST, Doctor::PRACTICE_GENERAL],
        ]);

        $dentistDoctor = Doctor::create([
            'name' => 'Dr Dentist',
            'practice_type' => Doctor::PRACTICE_DENTIST,
        ]);

        $this->assertTrue(
            $this->brandsFound('oral', $dentistDoctor)->contains('ORALDYNE'),
            'A dentist must reach a dentist-tagged brand',
        );

        $generalOnly = Doctor::create([
            'name' => 'Dr GP',
            'practice_type' => Doctor::PRACTICE_GENERAL,
        ]);

        // A general physician is the one practice type nothing is withheld
        // from — see Medicine::visibleToPracticeType().
        $this->assertTrue($this->brandsFound('napa', $generalOnly)->contains('NAPA'));
        $this->assertTrue($this->brandsFound('oral', $generalOnly)->contains('ORALDYNE'));
    }

    public function test_search_hides_general_only_medicines_from_a_dentist(): void
    {
        tenancy()->initialize($this->tenant);

        Medicine::create([
            'brand_name' => 'GPONLY',
            'generic_name' => 'Test',
            'default_strength' => '5 mg',
            'form' => 'tablet',
            'aliases' => [],
            'category' => 'Analgesic',
            'practice_types' => [Doctor::PRACTICE_GENERAL],
        ]);

        $dentistDoctor = Doctor::create([
            'name' => 'Dr Dentist',
            'practice_type' => Doctor::PRACTICE_DENTIST,
        ]);

        $this->assertFalse($this->brandsFound('gponly', $dentistDoctor)->contains('GPONLY'));
    }

    public function test_clinic_doctor_without_booking_gets_their_own_practice_type(): void
    {
        $clinic = Tenant::create(['id' => 'medicine-clinic', 'plan_tier' => 'clinic']);
        Domain::create(['domain' => 'medicine-clinic.localhost', 'tenant_id' => $clinic->id]);

        tenancy()->initialize($clinic);

        Medicine::create([
            'brand_name' => 'ORALDYNE',
            'generic_name' => 'Chlorhexidine',
            'default_strength' => '0.12%',
            'form' => 'mouthwash',
            'aliases' => ['oraldyne'],
            'category' => 'Dental',
            'practice_types' => [Doctor::PRACTICE_DENTIST],
        ]);

        // A brand belonging to a *different* specialism. This is the row that
        // makes the assertions below bite: `visibleToPracticeType()` never
        // withholds anything from a general physician, so a dentist-only brand
        // stays visible under the bug too. Only a dermatologist-only brand
        // tells "resolved as dentist" apart from "fell back to general".
        Medicine::create([
            'brand_name' => 'DERMONLY',
            'generic_name' => 'Clobetasol',
            'default_strength' => '0.05%',
            'form' => 'ointment',
            'aliases' => ['dermonly'],
            'category' => 'Dermatology',
            'practice_types' => [Doctor::PRACTICE_DERMATOLOGIST],
        ]);

        $dentistLogin = User::create([
            'name' => 'Dr Dentist',
            'email' => 'dentist@clinic.test',
            'password' => Hash::make('secret'),
            'role' => User::ROLE_DOCTOR,
            'tenant_id' => $clinic->id,
        ]);

        // A second doctor exists, so the solo "there is only one" shortcut
        // cannot be what resolves this.
        Doctor::create(['name' => 'Dr GP', 'practice_type' => Doctor::PRACTICE_GENERAL]);
        $dentistProfile = Doctor::create([
            'name' => 'Dr Dentist',
            'user_id' => $dentistLogin->id,
            'practice_type' => Doctor::PRACTICE_DENTIST,
        ]);

        $this->actingAs($dentistLogin);

        $resolved = app(MedicineService::class)->resolvePrescribingDoctor();

        $this->assertNotNull($resolved);
        $this->assertSame($dentistProfile->id, $resolved->id);

        // The rule this guards (bug_history.md, 2026-08-07T16:16:01+0600): with
        // no booking in context, a clinic specialist must search their *own*
        // practice type, not silently fall back to the general-physician list.
        // No $prescribingDoctor is passed — resolution has to come from the
        // signed-in user, exactly as `/api/medicines/search` and My medicines
        // call it.
        $service = app(MedicineService::class);

        $this->assertTrue(
            $service->search('oral', $dentistLogin)
                ->contains(fn (array $row): bool => $row['brand_name'] === 'ORALDYNE'),
            'A clinic dentist must reach their own practice-type brands',
        );

        // The fallback would have widened the list to general-physician, which
        // withholds nothing. Finding a dermatologist-only brand here is what
        // that regression looks like.
        $this->assertFalse(
            $service->search('dermonly', $dentistLogin)
                ->contains(fn (array $row): bool => $row['brand_name'] === 'DERMONLY'),
            'Falling back to the general-physician catalogue would leak this in',
        );
    }

    public function test_clinic_doctor_without_a_paired_login_resolves_to_null(): void
    {
        $clinic = Tenant::create(['id' => 'medicine-clinic-unpaired', 'plan_tier' => 'clinic']);
        Domain::create(['domain' => 'medicine-clinic-unpaired.localhost', 'tenant_id' => $clinic->id]);

        tenancy()->initialize($clinic);

        $login = User::create([
            'name' => 'Dr Unpaired',
            'email' => 'unpaired@clinic.test',
            'password' => Hash::make('secret'),
            'role' => User::ROLE_DOCTOR,
            'tenant_id' => $clinic->id,
        ]);

        Doctor::create(['name' => 'Dr One', 'practice_type' => Doctor::PRACTICE_DENTIST]);
        Doctor::create(['name' => 'Dr Two', 'practice_type' => Doctor::PRACTICE_GENERAL]);

        $this->actingAs($login);

        $this->assertNull(app(MedicineService::class)->resolvePrescribingDoctor());
    }

    public function test_solo_still_resolves_its_single_doctor_without_pairing(): void
    {
        tenancy()->initialize($this->tenant);

        $solo = Doctor::create(['name' => 'Dr Solo', 'practice_type' => Doctor::PRACTICE_DERMATOLOGIST]);

        $this->actingAs($this->doctor);

        $this->assertSame($solo->id, app(MedicineService::class)->resolvePrescribingDoctor()?->id);
    }

    public function test_deleting_a_login_clears_the_doctor_pairing(): void
    {
        tenancy()->initialize($this->tenant);

        $profile = Doctor::create([
            'name' => 'Dr Picker',
            'user_id' => $this->doctor->id,
            'practice_type' => Doctor::PRACTICE_GENERAL,
        ]);

        $this->doctor->delete();

        $this->assertNull($profile->fresh()->user_id);
    }

    public function test_display_label_includes_generic_for_dropdown_search(): void
    {
        tenancy()->initialize($this->tenant);

        $napa = Medicine::query()->where('brand_name', 'NAPA')->first();

        // The form is in the label deliberately: the catalogue holds one row
        // per brand + strength + form, so NAPA ships a 500 mg tablet *and* a
        // 500 mg suppository. Without the form those render identically and a
        // doctor cannot tell which one they just picked.
        $this->assertSame('NAPA 500 mg tablet — Paracetamol', $napa->displayLabel());
    }

    public function test_display_label_distinguishes_same_strength_different_forms(): void
    {
        tenancy()->initialize($this->tenant);

        $tablet = Medicine::query()->where('brand_name', 'NAPA')->first();

        $suppository = Medicine::create([
            'brand_name' => 'NAPA',
            'generic_name' => 'Paracetamol',
            'default_strength' => '500 mg',
            'form' => 'suppository',
            'aliases' => ['napa'],
            'category' => 'Analgesic',
            'practice_types' => [Doctor::PRACTICE_GENERAL],
        ]);

        $this->assertNotSame($tablet->displayLabel(), $suppository->displayLabel());
    }

    public function test_resolve_medicine_name_from_direct_brand_value(): void
    {
        tenancy()->initialize($this->tenant);

        $name = $this->medicineService->resolveMedicineNameFromFormState([
            'medicine_name' => 'custom brand',
        ]);

        $this->assertSame('CUSTOM BRAND', $name);
    }

    /**
     * Excluding brands already on the prescription is no longer the service's
     * job — `MedicinePickerFields` rejects sibling repeater rows on top of
     * these results. What `search()` still owns, and what that filter breaks
     * without, is the key it matches on: `brand_name` must be the bare
     * uppercase brand held in `medicine_name` form state, not the display
     * label. If it ever became `NAPA 500 mg tablet — Paracetamol`, the reject
     * would silently stop matching and every already-prescribed brand would
     * reappear in the next row's dropdown.
     */
    public function test_search_returns_the_bare_brand_key_the_picker_excludes_on(): void
    {
        tenancy()->initialize($this->tenant);

        $napa = $this->medicineService->search('napa', $this->doctor)
            ->first(fn (array $row): bool => $row['brand_name'] === 'NAPA');

        $this->assertNotNull($napa);
        $this->assertSame('NAPA', $napa['brand_name']);
        $this->assertNotSame($napa['label'], $napa['brand_name'], 'The label is decorated; the key is not');

        // And the service does not filter on its own: both brands stay
        // reachable, so the picker layer is the only thing withholding one.
        $this->assertTrue($this->brandsFound('sergel')->contains('SERGEL'));
    }

    /**
     * Brand names `search()` surfaces, as the picker consumes them.
     *
     * @return Collection<int, string>
     */
    private function brandsFound(string $needle, ?Doctor $prescribingDoctor = null): Collection
    {
        return $this->medicineService
            ->search($needle, $this->doctor, $prescribingDoctor)
            ->map(fn (array $row): string => $row['brand_name']);
    }

    public function test_dose_options_for_catalogue_brand_include_strength_and_other_only(): void
    {
        tenancy()->initialize($this->tenant);

        $options = VisitNotesFormSchema::doseOptionsForBrand('NAPA');

        // The chip carries the form because two strengths of one brand in
        // different forms are not interchangeable.
        $this->assertSame([
            '500 mg' => '500 mg tablet',
            'other' => 'Other',
        ], $options);
    }

    public function test_dose_options_offer_every_form_a_brand_ships_in(): void
    {
        tenancy()->initialize($this->tenant);

        // The adult tablet is a pinned, hand-verified SKU; the syrup is a
        // catalogue row. Both tiers are set explicitly so this asserts the
        // ordering rule rather than whatever the seeder happened to default to.
        Medicine::query()
            ->where('brand_name', 'NAPA')
            ->update(['priority' => Medicine::TIER_PINNED]);

        Medicine::create([
            'brand_name' => 'NAPA',
            'generic_name' => 'Paracetamol',
            'default_strength' => '120 mg/5 ml',
            'form' => 'syrup',
            'aliases' => ['napa'],
            'category' => 'Analgesic',
            'practice_types' => [Doctor::PRACTICE_GENERAL],
            'priority' => Medicine::TIER_ESSENTIAL,
        ]);

        $options = VisitNotesFormSchema::doseOptionsForBrand('NAPA');

        // The picker dedupes to one entry per brand, so the paediatric syrup
        // is only reachable through these chips. If this regresses, a GP can
        // no longer prescribe NAPA to a child without free-texting it.
        $this->assertArrayHasKey('120 mg/5 ml', $options);
        $this->assertSame('120 mg/5 ml syrup', $options['120 mg/5 ml']);

        // Tier order: the hand-verified adult tablet still leads.
        $this->assertSame('500 mg', array_key_first($options));
    }

    public function test_search_ranks_a_pinned_brand_above_a_parenteral_sku_of_the_same_name(): void
    {
        tenancy()->initialize($this->tenant);

        Medicine::query()
            ->where('brand_name', 'NAPA')
            ->update(['priority' => Medicine::TIER_PINNED]);

        Medicine::create([
            'brand_name' => 'NAPA',
            'generic_name' => 'Paracetamol',
            'default_strength' => '10 mg/ml',
            'form' => 'injection',
            'aliases' => ['napa'],
            'category' => 'Analgesic',
            'practice_types' => [Doctor::PRACTICE_GENERAL],
            'priority' => Medicine::TIER_SPECIALIST,
        ]);

        $results = $this->medicineService->search('napa', $this->doctor);

        // Tiering is what replaced leaving rows out of the catalogue. Both SKUs
        // match "napa" identically on text, so if this regresses a chamber
        // doctor's first hit becomes an IV infusion — the exact hazard the old
        // curated-only catalogue avoided by exclusion.
        $this->assertNotEmpty($results);
        $this->assertStringContainsString('tablet', $results->first()['label']);
    }
}
