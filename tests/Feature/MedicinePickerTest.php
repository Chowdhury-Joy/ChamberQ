<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\Doctor;
use App\Models\Medicine;
use App\Models\MedicineUsage;
use App\Models\Tenant;
use App\Models\User;
use App\Services\MedicineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_record_usage_increments_and_stores_defaults(): void
    {
        tenancy()->initialize($this->tenant);

        $usage = $this->medicineService->recordUsage($this->doctor, [
            'medicine_name' => 'sergel',
            'generic_name' => 'Esomeprazole',
            'dose' => '40 mg',
            'frequency' => '1+0+1',
            'duration' => '14 days',
        ]);

        $this->assertSame('SERGEL', $usage->medicine_name);
        $this->assertSame(1, $usage->use_count);

        $this->medicineService->recordUsage($this->doctor, [
            'medicine_name' => 'SERGEL',
            'dose' => '40 mg',
            'frequency' => '1+0+1',
            'duration' => '14 days',
        ]);

        $this->assertSame(2, $usage->fresh()->use_count);
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

    public function test_catalog_filters_by_doctor_practice_type(): void
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

        $options = $this->medicineService->groupedSelectOptions($this->doctor, $dentistDoctor);

        $this->assertArrayHasKey('Dental', $options);
        $this->assertArrayHasKey('ORALDYNE', $options['Dental']);

        $generalOnly = Doctor::create([
            'name' => 'Dr GP',
            'practice_type' => Doctor::PRACTICE_GENERAL,
        ]);

        $gpOptions = $this->medicineService->groupedSelectOptions($this->doctor, $generalOnly);
        $flatGp = collect($gpOptions)
            ->except([__('Other'), __('Your medicines')])
            ->flatMap(fn (array $group): array => array_keys($group));

        $this->assertTrue($flatGp->contains('NAPA'));
        $this->assertTrue($flatGp->contains('ORALDYNE'));
    }

    public function test_dentist_catalog_hides_general_only_medicines(): void
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

        $options = $this->medicineService->groupedSelectOptions($this->doctor, $dentistDoctor);
        $flat = collect($options)
            ->except([__('Other'), __('Your medicines')])
            ->flatMap(fn (array $group): array => array_keys($group));

        $this->assertFalse($flat->contains('GPONLY'));
    }
}
