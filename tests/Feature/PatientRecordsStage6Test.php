<?php

namespace Tests\Feature;

use App\Filament\SuperAdmin\Pages\ResearchData;
use App\Models\Booking;
use App\Models\Chamber;
use App\Models\Condition;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\ScheduleSession;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VisitRecord;
use App\Services\ResearchDataService;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class PatientRecordsStage6Test extends TestCase
{
    use RefreshDatabase;

    private ResearchDataService $service;

    private Condition $gastritis;

    private Condition $hypertension;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ResearchDataService::class);

        $this->gastritis = Condition::create([
            'code' => 'SLD-GI-001',
            'name' => 'Gastritis / Acid peptic disease',
            'aliases' => ['gastric'],
            'category' => 'Gastrointestinal',
        ]);

        $this->hypertension = Condition::create([
            'code' => 'SLD-CV-001',
            'name' => 'Hypertension',
            'aliases' => ['high bp'],
            'category' => 'Cardiovascular',
        ]);
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    public function test_coded_diagnosis_counts_are_aggregated_across_tenants(): void
    {
        $this->seedCodedVisits('tenant-a', 'solo', $this->gastritis, 12);
        $this->seedCodedVisits('tenant-b', 'clinic', $this->gastritis, 8);

        $results = $this->service->conditionCounts();

        $this->assertCount(1, $results['rows']);
        $this->assertSame($this->gastritis->id, $results['rows'][0]['condition_id']);
        $this->assertSame('SLD-GI-001', $results['rows'][0]['condition_code']);
        $this->assertSame(20, $results['rows'][0]['count']);
        $this->assertSame(20, $results['total_coded_visits']);
    }

    public function test_uncoded_diagnoses_are_excluded_from_research_counts(): void
    {
        $this->seedCodedVisits('coded-tenant', 'solo', $this->gastritis, 12);
        $this->seedUncodedVisits('uncoded-tenant', 'solo', 15);

        $results = $this->service->conditionCounts();

        $this->assertSame(12, $results['total_coded_visits']);
        $this->assertCount(1, $results['rows']);
    }

    public function test_groups_below_minimum_size_are_suppressed(): void
    {
        $this->seedCodedVisits('small-group', 'solo', $this->gastritis, 9);
        $this->seedCodedVisits('large-group', 'solo', $this->hypertension, 15);

        $results = $this->service->conditionCounts();

        $this->assertSame(1, $results['suppressed_group_count']);
        $this->assertCount(1, $results['rows']);
        $this->assertSame($this->hypertension->id, $results['rows'][0]['condition_id']);
        $this->assertGreaterThanOrEqual(ResearchDataService::MIN_GROUP_SIZE, $results['rows'][0]['count']);
    }

    public function test_plan_tier_filter_respects_k_anonymity(): void
    {
        $this->seedCodedVisits('solo-doc', 'solo', $this->gastritis, 8);
        $this->seedCodedVisits('clinic-doc', 'clinic', $this->gastritis, 15);

        $soloOnly = $this->service->conditionCounts(['plan_tier' => 'solo']);
        $allPlans = $this->service->conditionCounts();

        $this->assertCount(0, $soloOnly['rows']);
        $this->assertSame(1, $soloOnly['suppressed_group_count']);
        $this->assertCount(1, $allPlans['rows']);
        $this->assertSame(23, $allPlans['rows'][0]['count']);
    }

    public function test_date_range_filter_suppresses_small_groups_in_narrow_windows(): void
    {
        Carbon::setTestNow('2026-08-06 12:00:00');

        $this->seedCodedVisits('dated-tenant', 'solo', $this->gastritis, 15, '2026-08-01');
        $this->appendCodedVisits('dated-tenant', $this->gastritis, 5, '2026-07-01');

        $narrow = $this->service->conditionCounts([
            'date_from' => '2026-08-01',
            'date_to' => '2026-08-06',
        ]);

        $wide = $this->service->conditionCounts([
            'date_from' => '2026-07-01',
            'date_to' => '2026-08-06',
        ]);

        $this->assertCount(1, $narrow['rows']);
        $this->assertSame(15, $narrow['rows'][0]['count']);
        $this->assertCount(1, $wide['rows']);
        $this->assertSame(20, $wide['rows'][0]['count']);

        Carbon::setTestNow();
    }

    public function test_output_never_contains_patient_names_or_identifying_fields(): void
    {
        $patientName = 'Secret Patient Karim';

        $this->seedCodedVisits('privacy-tenant', 'solo', $this->gastritis, 12, null, $patientName);

        $results = $this->service->conditionCounts();
        $encoded = json_encode($results);

        $this->assertIsString($encoded);
        $this->assertStringNotContainsString($patientName, $encoded);
        $this->assertStringNotContainsString('patient_id', $encoded);
        $this->assertStringNotContainsString('tenant_id', $encoded);
        $this->assertStringNotContainsString('privacy-tenant', $encoded);

        foreach ($results['rows'] as $row) {
            $this->assertArrayHasKey('condition_code', $row);
            $this->assertArrayHasKey('condition_name', $row);
            $this->assertArrayHasKey('count', $row);
            $this->assertArrayNotHasKey('patient_name', $row);
        }
    }

    public function test_research_page_is_super_admin_only(): void
    {
        $superAdmin = User::create([
            'name' => 'Platform',
            'email' => 'super@research.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_SUPER_ADMIN,
            'tenant_id' => null,
        ]);

        $this->actingAs($superAdmin);
        Filament::setCurrentPanel(Filament::getPanel('superAdmin'));

        $this->assertTrue(ResearchData::canAccess());

        Livewire::test(ResearchData::class)
            ->assertSuccessful()
            ->assertSee('Aggregate anonymous research only');
    }

    private function seedCodedVisits(
        string $tenantId,
        string $planTier,
        Condition $condition,
        int $count,
        ?string $recordedDate = null,
        string $patientName = 'Test Patient',
    ): void {
        Tenant::firstOrCreate(
            ['id' => $tenantId],
            [
                'name' => ucfirst($tenantId).' Practice',
                'plan_tier' => $planTier,
                'billing_status' => 'active',
            ]
        );

        tenancy()->initialize(Tenant::find($tenantId));

        $doctor = User::create([
            'name' => 'Dr Research',
            'email' => "doc-{$tenantId}@research.test",
            'password' => Hash::make('secret'),
            'role' => User::ROLE_DOCTOR,
            'tenant_id' => $tenantId,
        ]);

        $chamber = Chamber::create(['name' => 'Main']);
        $doctorProfile = Doctor::create(['name' => 'Dr Research']);
        $session = ScheduleSession::create([
            'chamber_id' => $chamber->id,
            'doctor_id' => $doctorProfile->id,
            'day_of_week' => Carbon::today()->dayOfWeek,
            'session_name' => 'Morning',
            'start_time' => '09:00',
            'end_time' => '12:00',
            'slot_cap' => 50,
        ]);

        $recordedAt = Carbon::parse($recordedDate ?? today())->setTime(10, 0);

        for ($i = 1; $i <= $count; $i++) {
            $patient = Patient::create([
                'name' => $patientName.' '.$i,
                'phone' => '017'.str_pad((string) $i, 8, '0', STR_PAD_LEFT),
            ]);

            $booking = Booking::create([
                'bookable_type' => ScheduleSession::class,
                'bookable_id' => $session->id,
                'booking_date' => $recordedAt->toDateString(),
                'patient_id' => $patient->id,
                'patient_name' => $patient->name,
                'patient_phone' => $patient->phone,
                'serial_number' => $i,
                'status' => 'completed',
                'completed_at' => $recordedAt,
            ]);

            VisitRecord::create([
                'booking_id' => $booking->id,
                'patient_id' => $patient->id,
                'recorded_by' => $doctor->id,
                'condition_id' => $condition->id,
                'recorded_at' => $recordedAt,
            ]);
        }

        tenancy()->end();
    }

    private function appendCodedVisits(
        string $tenantId,
        Condition $condition,
        int $count,
        ?string $recordedDate = null,
    ): void {
        tenancy()->initialize(Tenant::find($tenantId));

        $doctor = User::query()->where('tenant_id', $tenantId)->firstOrFail();
        $session = ScheduleSession::query()->firstOrFail();
        $recordedAt = Carbon::parse($recordedDate ?? today())->setTime(10, 0);
        $startSerial = (int) Booking::query()->max('serial_number') + 1;

        for ($i = 0; $i < $count; $i++) {
            $serial = $startSerial + $i;
            $patient = Patient::create([
                'name' => 'Append Patient '.$serial,
                'phone' => '017'.str_pad((string) $serial, 8, '9', STR_PAD_LEFT),
            ]);

            $booking = Booking::create([
                'bookable_type' => ScheduleSession::class,
                'bookable_id' => $session->id,
                'booking_date' => $recordedAt->toDateString(),
                'patient_id' => $patient->id,
                'patient_name' => $patient->name,
                'patient_phone' => $patient->phone,
                'serial_number' => $serial,
                'status' => 'completed',
                'completed_at' => $recordedAt,
            ]);

            VisitRecord::create([
                'booking_id' => $booking->id,
                'patient_id' => $patient->id,
                'recorded_by' => $doctor->id,
                'condition_id' => $condition->id,
                'recorded_at' => $recordedAt,
            ]);
        }

        tenancy()->end();
    }

    private function seedUncodedVisits(string $tenantId, string $planTier, int $count): void
    {
        Tenant::create([
            'id' => $tenantId,
            'name' => ucfirst($tenantId).' Practice',
            'plan_tier' => $planTier,
            'billing_status' => 'active',
        ]);

        tenancy()->initialize(Tenant::find($tenantId));

        $doctor = User::create([
            'name' => 'Dr Uncoded',
            'email' => "uncoded-{$tenantId}@research.test",
            'password' => Hash::make('secret'),
            'role' => User::ROLE_DOCTOR,
            'tenant_id' => $tenantId,
        ]);

        $chamber = Chamber::create(['name' => 'Main']);
        $doctorProfile = Doctor::create(['name' => 'Dr Uncoded']);
        $session = ScheduleSession::create([
            'chamber_id' => $chamber->id,
            'doctor_id' => $doctorProfile->id,
            'day_of_week' => Carbon::today()->dayOfWeek,
            'session_name' => 'Morning',
            'start_time' => '09:00',
            'end_time' => '12:00',
            'slot_cap' => 50,
        ]);

        for ($i = 1; $i <= $count; $i++) {
            $booking = Booking::create([
                'bookable_type' => ScheduleSession::class,
                'bookable_id' => $session->id,
                'booking_date' => today(),
                'patient_name' => 'Uncoded Patient '.$i,
                'patient_phone' => '018'.str_pad((string) $i, 8, '0', STR_PAD_LEFT),
                'serial_number' => $i,
                'status' => 'completed',
            ]);

            VisitRecord::create([
                'booking_id' => $booking->id,
                'recorded_by' => $doctor->id,
                'diagnosis_uncoded' => 'Rare uncoded rash',
                'recorded_at' => now(),
            ]);
        }

        tenancy()->end();
    }
}
