<?php

namespace Tests\Feature;

use App\Models\Condition;
use App\Models\ConditionUsage;
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

    /**
     * The diagnosis picker ranks on how well the text matches, and nothing
     * else. It used to boost whatever this doctor had coded most often;
     * automatic learning was removed on 2026-08-11 (owner decision), so an
     * exact match must win regardless of any historical usage rows.
     */
    public function test_conditions_rank_on_text_match_not_past_usage(): void
    {
        tenancy()->initialize($this->tenant);

        $peptic = Condition::create([
            'code' => 'SLD-GI-002',
            'name' => 'Peptic disorder',
            'aliases' => ['stomach disorder'],
            'category' => 'Gastrointestinal',
        ]);

        // Historical rows from before learning was removed must not sway it.
        ConditionUsage::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->doctor->id,
            'condition_id' => $peptic->id,
            'use_count' => 50,
            'last_used_at' => now(),
        ]);

        $results = $this->conditionService->search('gastritis', $this->doctor);

        $this->assertSame(
            'SLD-GI-001',
            $results->first()['code'],
            'The best text match wins; a heavily used condition does not jump the queue',
        );
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
