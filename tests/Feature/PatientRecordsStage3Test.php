<?php

namespace Tests\Feature;

use App\Models\Condition;
use App\Models\Domain;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ConditionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PatientRecordsStage3Test extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $doctor;

    private ConditionService $conditionService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['id' => 'stage3-conditions', 'plan_tier' => 'solo']);
        Domain::create(['domain' => 'stage3-conditions.localhost', 'tenant_id' => $this->tenant->id]);

        tenancy()->initialize($this->tenant);

        $this->doctor = User::create([
            'name' => 'Dr Conditions',
            'email' => 'doc@stage3.test',
            'password' => Hash::make('secret'),
            'role' => User::ROLE_DOCTOR,
            'tenant_id' => $this->tenant->id,
        ]);

        Condition::create([
            'code' => 'SLD-GI-001',
            'name' => 'Gastritis / Acid peptic disease',
            'aliases' => ['gastric', 'acidity', 'APD', 'গ্যাস্ট্রিক'],
            'category' => 'Gastrointestinal',
        ]);

        Condition::create([
            'code' => 'SLD-RES-001',
            'name' => 'Upper respiratory tract infection',
            'aliases' => ['URTI', 'common cold', 'cold', 'সর্দি'],
            'category' => 'Respiratory',
        ]);

        Condition::create([
            'code' => 'SLD-END-001',
            'name' => 'Type 2 diabetes mellitus',
            'aliases' => ['DM', 'diabetes', 'T2DM'],
            'category' => 'Endocrine',
        ]);

        $this->conditionService = app(ConditionService::class);

        tenancy()->end();
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    public function test_search_finds_condition_by_alias(): void
    {
        tenancy()->initialize($this->tenant);

        $results = $this->conditionService->search('gastric', $this->doctor);

        $this->assertTrue($results->isNotEmpty());
        $this->assertSame('SLD-GI-001', $results->first()['code']);
        $this->assertSame('Gastritis / Acid peptic disease', $results->first()['name']);
    }

    public function test_search_requires_three_characters(): void
    {
        tenancy()->initialize($this->tenant);

        $this->assertTrue($this->conditionService->search('co', $this->doctor)->isEmpty());
        $this->assertTrue($this->conditionService->search('cold', $this->doctor)->isNotEmpty());
    }

    public function test_frequent_conditions_rank_higher(): void
    {
        tenancy()->initialize($this->tenant);

        $gastritis = Condition::query()->where('code', 'SLD-GI-001')->firstOrFail();
        $peptic = Condition::create([
            'code' => 'SLD-GI-002',
            'name' => 'Peptic disorder',
            'aliases' => ['stomach disorder'],
            'category' => 'Gastrointestinal',
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->conditionService->recordUsage($this->doctor, $peptic);
        }

        $this->conditionService->recordUsage($this->doctor, $gastritis);

        $results = $this->conditionService->search('dis', $this->doctor);

        $this->assertGreaterThanOrEqual(2, $results->count());
        $this->assertSame('SLD-GI-002', $results->first()['code']);
    }

    public function test_uncoded_free_text_path(): void
    {
        tenancy()->initialize($this->tenant);

        $resolved = $this->conditionService->resolveSelection(null, 'Rare skin rash');

        $this->assertFalse($resolved['coded']);
        $this->assertNull($resolved['condition_id']);
        $this->assertNull($resolved['code']);
        $this->assertSame('Rare skin rash', $resolved['name']);
    }

    public function test_coded_selection_resolves_condition(): void
    {
        tenancy()->initialize($this->tenant);

        $condition = Condition::query()->where('code', 'SLD-GI-001')->firstOrFail();

        $resolved = $this->conditionService->resolveSelection($condition->id, null);

        $this->assertTrue($resolved['coded']);
        $this->assertSame($condition->id, $resolved['condition_id']);
        $this->assertSame('SLD-GI-001', $resolved['code']);
    }

    public function test_conditions_load_command_imports_csv(): void
    {
        Condition::query()->delete();

        $this->artisan('conditions:load', ['path' => base_path('data/condition-list-draft.csv')])
            ->assertSuccessful();

        $this->assertGreaterThan(200, Condition::count());
        $this->assertNotNull(Condition::query()->where('code', 'SLD-GI-001')->first());
    }

    public function test_authenticated_doctor_can_search_via_api(): void
    {
        $response = $this->actingAs($this->doctor)
            ->getJson('http://stage3-conditions.localhost/api/conditions/search?q=gastric');

        $response->assertOk()
            ->assertJsonPath('results.0.code', 'SLD-GI-001')
            ->assertJsonPath('allow_free_text', true);
    }

    public function test_staff_cannot_search_conditions_via_api(): void
    {
        $staff = User::create([
            'name' => 'Staff',
            'email' => 'staff@stage3.test',
            'password' => Hash::make('secret'),
            'role' => User::ROLE_STAFF,
            'tenant_id' => $this->tenant->id,
        ]);

        $this->actingAs($staff)
            ->getJson('http://stage3-conditions.localhost/api/conditions/search?q=gastric')
            ->assertForbidden();
    }
}
