<?php

namespace Tests\Feature;

use App\Console\Commands\LoadDosingDefaultsCommand;
use App\Filament\TenantAdmin\Support\VisitNotesFormSchema;
use App\Models\Condition;
use App\Models\Domain;
use App\Models\Medicine;
use App\Models\MedicineUsage;
use App\Models\Patient;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ConditionService;
use App\Services\MedicineService;
use App\Services\PrescriptionTemplateService;
use App\Support\PrescriptionTiming;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The prefill chain, the packs, and the guards that keep them harmless.
 *
 * The behaviour under test is a promise to the doctor: the pad fills what it
 * can defend and leaves everything else blank. Each test here pins one half of
 * that — what must be filled, and what must never be.
 */
class RxAutomationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $doctor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['id' => 'rx-auto', 'plan_tier' => 'solo', 'queue_runner' => 'doctor']);
        Domain::create(['domain' => 'rx-auto.localhost', 'tenant_id' => $this->tenant->id]);

        tenancy()->initialize($this->tenant);

        $this->doctor = User::create([
            'name' => 'Dr Auto',
            'email' => 'doc@rxauto.test',
            'password' => Hash::make('secret'),
            'role' => User::ROLE_DOCTOR,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    private function medicine(array $attributes = []): Medicine
    {
        return Medicine::create(array_merge([
            'brand_name' => 'SECLO',
            'generic_name' => 'Omeprazole',
            'default_strength' => '20 mg',
            'form' => 'capsule',
            'aliases' => [],
            'priority' => Medicine::TIER_CURATED,
        ], $attributes));
    }

    public function test_catalogue_default_fills_frequency_duration_and_timing(): void
    {
        $this->medicine([
            'default_frequency' => '1+0+0',
            'default_duration' => '1 month',
            'default_timing' => PrescriptionTiming::BEFORE_FOOD,
        ]);

        $row = app(MedicineService::class)->search('seclo', $this->doctor)->firstWhere('brand_name', 'SECLO');

        $this->assertSame('1+0+0', $row['frequency']);
        $this->assertSame('1 month', $row['duration']);
        $this->assertSame(PrescriptionTiming::BEFORE_FOOD, $row['timing']);
    }

    public function test_a_drug_with_no_catalogue_default_stays_blank(): void
    {
        // The old behaviour was '1+1+1' and '5 days' for everything. Blank is
        // the correct answer when nothing knows better, and this is the test
        // that stops a literal creeping back in.
        $this->medicine(['brand_name' => 'RAREDRUG', 'generic_name' => 'Something unusual']);

        $row = app(MedicineService::class)->search('raredrug', $this->doctor)->firstWhere('brand_name', 'RAREDRUG');

        $this->assertNull($row['frequency']);
        $this->assertNull($row['duration']);
        $this->assertNull($row['timing']);
    }

    public function test_doctors_own_default_beats_the_catalogue_field_by_field(): void
    {
        $this->medicine([
            'default_frequency' => '1+0+0',
            'default_duration' => '1 month',
            'default_timing' => PrescriptionTiming::BEFORE_FOOD,
        ]);

        MedicineUsage::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->doctor->id,
            'medicine_name' => 'SECLO',
            'last_frequency' => '1+0+1',
            // Duration and timing left unset: the catalogue should still fill
            // those, rather than the doctor's row blanking them.
        ]);

        $row = app(MedicineService::class)->search('seclo', $this->doctor)->firstWhere('brand_name', 'SECLO');

        $this->assertSame('1+0+1', $row['frequency']);
        $this->assertSame('1 month', $row['duration']);
        $this->assertSame(PrescriptionTiming::BEFORE_FOOD, $row['timing']);
    }

    public function test_saving_a_default_from_the_pad_persists_the_whole_line(): void
    {
        app(MedicineService::class)->saveDoctorMedicine($this->doctor, [
            'medicine_name' => 'napa',
            'generic_name' => 'Paracetamol',
            'dose' => '500 mg',
            'frequency' => '1+1+1',
            'duration' => '3 days',
            'timing' => 'af',
        ]);

        $usage = MedicineUsage::query()->where('user_id', $this->doctor->id)->firstOrFail();

        $this->assertSame('NAPA', $usage->medicine_name);
        $this->assertSame('3 days', $usage->last_duration);
        $this->assertSame(PrescriptionTiming::AFTER_FOOD, $usage->last_timing);
    }

    public function test_dosing_loader_rejects_a_value_the_pad_cannot_render(): void
    {
        $this->medicine(['brand_name' => 'LOSECTIL', 'generic_name' => 'Omeprazole']);

        $csv = tempnam(sys_get_temp_dir(), 'dosing').'.csv';
        file_put_contents($csv, implode("\n", [
            '## test sheet',
            'generic_name,default_frequency,default_duration,default_timing,hold,note',
            'Omeprazole,twice daily,1 month,before_food,,',
        ]));

        $this->artisan('dosing-defaults:load', ['path' => $csv])->assertSuccessful();

        $medicine = Medicine::query()->where('brand_name', 'LOSECTIL')->firstOrFail();

        $this->assertNull($medicine->default_frequency, 'A free-text frequency must not reach the catalogue.');
        $this->assertSame('1 month', $medicine->default_duration);
        $this->assertSame(PrescriptionTiming::BEFORE_FOOD, $medicine->default_timing);

        unlink($csv);
    }

    public function test_dosing_loader_matches_salts_but_never_combinations_or_non_oral_forms(): void
    {
        $this->medicine(['brand_name' => 'PANTONIX', 'generic_name' => 'Pantoprazole Sodium', 'form' => 'tablet']);
        $this->medicine(['brand_name' => 'PANTONIX IV', 'generic_name' => 'Pantoprazole Sodium', 'form' => 'injection']);
        $this->medicine(['brand_name' => 'PANTONIX PLUS', 'generic_name' => 'Pantoprazole Sodium + Domperidone', 'form' => 'tablet']);

        $csv = tempnam(sys_get_temp_dir(), 'dosing').'.csv';
        file_put_contents($csv, implode("\n", [
            'generic_name,default_frequency,default_duration,default_timing,hold,note',
            'Pantoprazole,1+0+0,1 month,before_food,,',
        ]));

        $this->artisan('dosing-defaults:load', ['path' => $csv])->assertSuccessful();

        $this->assertSame('1+0+0', Medicine::query()->where('brand_name', 'PANTONIX')->value('default_frequency'));
        $this->assertNull(
            Medicine::query()->where('brand_name', 'PANTONIX IV')->value('default_frequency'),
            'An oral pattern must not land on an injection.'
        );
        $this->assertNull(
            Medicine::query()->where('brand_name', 'PANTONIX PLUS')->value('default_frequency'),
            'A combination must not inherit a single-ingredient default.'
        );

        unlink($csv);
    }

    public function test_a_row_on_hold_is_skipped(): void
    {
        $this->medicine(['brand_name' => 'HELDBRAND', 'generic_name' => 'Tramadol']);

        $csv = tempnam(sys_get_temp_dir(), 'dosing').'.csv';
        file_put_contents($csv, implode("\n", [
            'generic_name,default_frequency,default_duration,default_timing,hold,note',
            'Tramadol,1+0+1,3 days,after_food,hold,',
        ]));

        $this->artisan('dosing-defaults:load', ['path' => $csv])->assertSuccessful();

        $this->assertNull(Medicine::query()->where('brand_name', 'HELDBRAND')->value('default_frequency'));

        unlink($csv);
    }

    public function test_every_shipped_dosing_value_is_one_the_pad_can_show(): void
    {
        // The sheet is edited by hand, so this walks the real file: a typo in
        // it would otherwise only surface as a chip that renders blank in the
        // middle of a consultation.
        $rows = array_filter(
            array_map('str_getcsv', file(base_path('data/dosing-defaults.csv'), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)),
            fn (array $row) => ! str_starts_with(trim((string) $row[0]), '#') && trim((string) $row[0]) !== 'generic_name'
        );

        $this->assertGreaterThan(100, count($rows), 'The starter sheet should still be there.');

        foreach ($rows as $row) {
            [$generic, $frequency, $duration, $timing] = array_pad($row, 4, null);

            if (filled($frequency)) {
                $this->assertContains(
                    str_replace('1/2', '½', trim($frequency)),
                    VisitNotesFormSchema::FREQUENCY_PRESETS,
                    "{$generic}: frequency \"{$frequency}\" is not a chip the pad can show."
                );
            }

            if (filled($duration)) {
                $this->assertContains(
                    trim($duration),
                    VisitNotesFormSchema::DURATION_PRESETS,
                    "{$generic}: duration \"{$duration}\" is not a chip the pad can show."
                );
            }

            if (filled($timing)) {
                $this->assertContains(
                    trim($timing),
                    PrescriptionTiming::KEYS,
                    "{$generic}: timing \"{$timing}\" is not a known key."
                );
            }
        }
    }

    public function test_diagnosis_search_carries_its_advice_in_the_panel_language(): void
    {
        $condition = Condition::create([
            'code' => 'SLD-TEST-001',
            'name' => 'Gastritis test',
            'aliases' => [],
            'default_advice' => json_encode(['en' => 'Avoid spicy food.', 'bn' => 'ঝাল খাবার এড়িয়ে চলুন।'], JSON_UNESCAPED_UNICODE),
            'default_tests' => 'CBC',
        ]);

        $row = app(ConditionService::class)->search('gastritis', $this->doctor)->first();

        $this->assertSame($condition->id, $row['id']);
        $this->assertSame('Avoid spicy food.', $row['advice']);
        $this->assertSame('CBC', $row['tests']);

        app()->setLocale('bn');
        $this->assertSame('ঝাল খাবার এড়িয়ে চলুন।', $condition->adviceForLocale());
        app()->setLocale('en');
    }

    public function test_shipped_presets_never_carry_a_medicine(): void
    {
        // The line this product does not cross: advice and a work-up are
        // proposals a doctor would say out loud anyway; a drug attached to a
        // diagnosis is a clinical recommendation.
        $this->artisan('conditions:load')->assertSuccessful();
        $this->artisan('condition-presets:load')->assertSuccessful();

        $withPresets = Condition::query()->whereNotNull('default_advice')->get();

        $this->assertGreaterThan(30, $withPresets->count());

        foreach ($withPresets as $condition) {
            $this->assertFalse(
                Medicine::query()->whereNotNull('generic_name')->exists()
                    && str_contains(mb_strtolower((string) $condition->default_tests), 'tablet'),
                "{$condition->code} looks like it lists a medicine."
            );
        }
    }

    public function test_a_pack_round_trips_and_only_a_coded_diagnosis_anchors_it(): void
    {
        $condition = Condition::create(['code' => 'SLD-TEST-002', 'name' => 'IHD test', 'aliases' => []]);

        $service = app(PrescriptionTemplateService::class);

        $service->save($this->doctor, 'IHD standard', [
            'diagnosis' => $condition->id,
            'advice' => 'Stop smoking',
            'tests_advised' => 'ECG',
            'follow_up_relative' => '1 month',
            'prescription_items' => [
                ['medicine_name' => 'ECOSPRIN', 'dose' => '75 mg', 'frequency' => '0+0+1', 'duration' => 'Continue', 'timing' => 'af'],
                ['medicine_name' => '', 'dose' => '10 mg'],
            ],
        ]);

        $packs = $service->forDoctor($this->doctor);

        $this->assertCount(1, $packs);
        $this->assertSame($condition->id, $packs[0]['condition_id']);
        $this->assertCount(1, $packs[0]['items'], 'A blank medicine row must not become a pack line.');
        $this->assertSame(PrescriptionTiming::AFTER_FOOD, $packs[0]['items'][0]['timing']);

        // Saving under the same name replaces, rather than growing a list of
        // near-duplicates the doctor has to read through mid-consult.
        $service->save($this->doctor, 'IHD standard', [
            'diagnosis' => VisitNotesFormSchema::FREE_DIAGNOSIS_PREFIX.'Something uncoded',
            'prescription_items' => [['medicine_name' => 'MONAS']],
        ]);

        $packs = $service->forDoctor($this->doctor);

        $this->assertCount(1, $packs);
        $this->assertNull($packs[0]['condition_id'], 'Free text cannot anchor a pack.');
        $this->assertSame('MONAS', $packs[0]['items'][0]['medicine_name']);
    }

    public function test_history_seed_reads_the_patient_record_and_never_invents(): void
    {
        $patient = Patient::create([
            'name' => 'Rahima',
            'phone' => '01799000111',
            'conditions' => 'HTN, DM',
            'medicines' => 'Losartan 50 mg',
        ]);

        $this->assertSame(
            'HTN, DM · On: Losartan 50 mg',
            VisitNotesFormSchema::historySeedFromPatient($patient)
        );

        $blank = Patient::create(['name' => 'Karim', 'phone' => '01799000222']);

        $this->assertSame('', VisitNotesFormSchema::historySeedFromPatient($blank));
        $this->assertSame('', VisitNotesFormSchema::historySeedFromPatient(null));
    }

    public function test_complaint_rows_round_trip_through_plain_text(): void
    {
        $formatted = \App\Support\ComplaintChips::format([
            ['complaint' => 'Fever', 'duration' => '3 days'],
            ['complaint' => 'Cough', 'duration' => ''],
            ['complaint' => '', 'duration' => '1 week'],
        ]);

        $this->assertSame("Fever — 3 days\nCough", $formatted);

        $this->assertSame(
            [
                ['complaint' => 'Fever', 'duration' => '3 days'],
                ['complaint' => 'Cough', 'duration' => ''],
            ],
            \App\Support\ComplaintChips::parse($formatted)
        );

        // Legacy chip string and free-typed modal text still open as rows.
        $this->assertSame(
            [
                ['complaint' => 'Fever', 'duration' => '3 days'],
                ['complaint' => 'Cough', 'duration' => ''],
            ],
            \App\Support\ComplaintChips::parse('Fever × 3 days, Cough')
        );
    }
}
