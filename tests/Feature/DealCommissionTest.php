<?php

namespace Tests\Feature;

use App\Models\Commission;
use App\Models\Marketer;
use App\Models\MedicalRepresentative;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CommissionService;
use App\Services\DealCommissionRates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DealCommissionTest extends TestCase
{
    use RefreshDatabase;

    private Marketer $marketer;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $partner = User::create([
            'name' => 'Joy Partner',
            'email' => 'joy@partner.com',
            'password' => Hash::make('password'),
            'role' => User::ROLE_MARKETER,
            'tenant_id' => null,
        ]);

        $this->marketer = Marketer::create([
            'user_id' => $partner->id,
            'code' => 'joy20',
            'display_name' => 'Joy',
            'setup_commission_rate' => 0.20,
            'monthly_commission_rate' => 0.10,
            'is_active' => true,
        ]);

        $this->superAdmin = User::create([
            'name' => 'Platform',
            'email' => 'super@platform.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_SUPER_ADMIN,
            'tenant_id' => null,
        ]);
    }

    public function test_mr_setup_splits_twenty_twenty_on_paying_price(): void
    {
        $mr = $this->mr();
        $tenant = $this->maestro(['medical_representative_id' => $mr->id, 'paying_setup_amount' => 20000]);
        $service = app(CommissionService::class);
        $service->applyPricingToTenant($tenant);
        $tenant->save();
        $service->createPendingSetupCommission($tenant);

        $this->assertSame(20000, (int) $tenant->fresh()->setup_amount_due);
        $this->assertSame(25000, (int) $tenant->fresh()->list_setup_amount);

        $mrRow = Commission::query()->where('tenant_id', $tenant->id)->where('medical_representative_id', $mr->id)->first();
        $mkRow = Commission::query()->where('tenant_id', $tenant->id)->where('marketer_id', $this->marketer->id)->first();

        $this->assertSame(4000, (int) $mrRow->commission_amount);
        $this->assertSame(4000, (int) $mkRow->commission_amount);
    }

    public function test_year_1_monthly_creates_no_partner_rows(): void
    {
        $tenant = $this->maestro();
        $service = app(CommissionService::class);
        $service->applyPricingToTenant($tenant);
        $tenant->update(['setup_paid_at' => now(), 'billing_status' => 'active']);

        $count = $service->generateMonthlyPendingCommissions(now()->format('Y-m'));
        $this->assertSame(0, $count);

        $service->confirmMonthlyPayment($tenant, now()->format('Y-m'), $this->superAdmin);
        $this->assertSame(0, Commission::query()->where('type', Commission::TYPE_MONTHLY)->count());
    }

    public function test_year_2_monthly_with_mr_is_five_and_five(): void
    {
        $mr = $this->mr();
        $tenant = $this->maestro(['medical_representative_id' => $mr->id]);
        $service = app(CommissionService::class);
        $service->applyPricingToTenant($tenant);
        $tenant->update(['setup_paid_at' => now()->subYear(), 'billing_status' => 'active']);

        $period = now()->format('Y-m');
        $service->confirmMonthlyPayment($tenant, $period, $this->superAdmin);

        $this->assertSame(150, (int) Commission::query()
            ->where('medical_representative_id', $mr->id)
            ->where('type', Commission::TYPE_MONTHLY)
            ->value('commission_amount'));
        $this->assertSame(150, (int) Commission::query()
            ->where('marketer_id', $this->marketer->id)
            ->where('type', Commission::TYPE_MONTHLY)
            ->value('commission_amount'));
    }

    public function test_year_2_direct_is_ten_percent_to_marketer(): void
    {
        $tenant = $this->maestro();
        $service = app(CommissionService::class);
        $service->applyPricingToTenant($tenant);
        $tenant->update(['setup_paid_at' => now()->subYear(), 'billing_status' => 'active']);

        $service->confirmMonthlyPayment($tenant, now()->format('Y-m'), $this->superAdmin, null, 3000);

        $this->assertSame(300, (int) Commission::query()
            ->where('marketer_id', $this->marketer->id)
            ->where('type', Commission::TYPE_MONTHLY)
            ->value('commission_amount'));
        $this->assertSame(0, Commission::query()->whereNotNull('medical_representative_id')->count());
    }

    public function test_year_1_prepaid_with_mr_is_fifteen_and_five_of_year(): void
    {
        $mr = $this->mr();
        $tenant = $this->maestro(['medical_representative_id' => $mr->id]);
        $service = app(CommissionService::class);
        $service->applyPricingToTenant($tenant);
        $tenant->save();
        $service->confirmSetupPayment($tenant, $this->superAdmin);
        $service->confirmYearPrepaid($tenant, $this->superAdmin, null, null, now()->format('Y-m'));

        $this->assertSame(5400, (int) Commission::query()
            ->where('type', Commission::TYPE_YEAR_PREPAID)
            ->where('medical_representative_id', $mr->id)
            ->value('commission_amount'));
        $this->assertSame(1800, (int) Commission::query()
            ->where('type', Commission::TYPE_YEAR_PREPAID)
            ->where('marketer_id', $this->marketer->id)
            ->value('commission_amount'));
    }

    public function test_year_1_prepaid_direct_is_twenty_percent_to_marketer(): void
    {
        $tenant = $this->maestro();
        $service = app(CommissionService::class);
        $service->applyPricingToTenant($tenant);
        $tenant->save();
        $service->confirmSetupPayment($tenant, $this->superAdmin);
        $service->confirmYearPrepaid($tenant, $this->superAdmin, null, 36000, now()->format('Y-m'));

        $this->assertSame(7200, (int) Commission::query()
            ->where('type', Commission::TYPE_YEAR_PREPAID)
            ->where('marketer_id', $this->marketer->id)
            ->value('commission_amount'));
    }

    public function test_setup_override_percent_uses_paying_amount(): void
    {
        $mr = $this->mr();
        $tenant = $this->maestro([
            'medical_representative_id' => $mr->id,
            'paying_setup_amount' => 20000,
            'commission_setup_mr_rate' => 0.40,
        ]);
        $service = app(CommissionService::class);
        $service->applyPricingToTenant($tenant);
        $tenant->save();
        $service->createPendingSetupCommission($tenant);

        $this->assertSame(8000, (int) Commission::query()
            ->where('medical_representative_id', $mr->id)
            ->value('commission_amount'));
        $this->assertSame(4000, (int) Commission::query()
            ->where('marketer_id', $this->marketer->id)
            ->value('commission_amount'));
    }

    public function test_pair_over_one_hundred_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        DealCommissionRates::assertPair(0.60, 0.50);
    }

    public function test_front_door_only_paying_override_commissions_on_that_amount(): void
    {
        $mr = $this->mr();
        $tenant = Tenant::create([
            'id' => 'site-only-deal',
            'plan_tier' => 'solo',
            'marketer_id' => $this->marketer->id,
            'medical_representative_id' => $mr->id,
            'paying_setup_amount' => 2000,
            'feature_flags' => Tenant::featureFlagsWithModules([], [Tenant::MODULE_FRONT_DOOR]),
        ]);
        $service = app(CommissionService::class);
        $service->applyPricingToTenant($tenant);
        $tenant->save();
        $service->createPendingSetupCommission($tenant);

        $this->assertSame(5000, (int) $tenant->fresh()->list_setup_amount);
        $this->assertSame(2000, (int) $tenant->fresh()->setup_amount_due);
        $this->assertSame(400, (int) Commission::query()->where('medical_representative_id', $mr->id)->value('commission_amount'));
        $this->assertSame(400, (int) Commission::query()->where('marketer_id', $this->marketer->id)->value('commission_amount'));
    }

    public function test_existing_list_snapshot_is_not_rewritten_until_reprice(): void
    {
        $tenant = $this->maestro();
        $tenant->forceFill([
            'list_setup_amount' => 15000,
            'setup_amount_due' => 15000,
            'list_monthly_amount' => 3000,
            'monthly_amount_due' => 3000,
        ])->save();

        $this->assertSame(15000, (int) $tenant->fresh()->setup_amount_due);

        app(CommissionService::class)->applyPricingToTenant($tenant);
        $tenant->save();
        $this->assertSame(25000, (int) $tenant->fresh()->setup_amount_due);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function maestro(array $extra = []): Tenant
    {
        return Tenant::create(array_merge([
            'id' => 'drdeal',
            'plan_tier' => 'solo',
            'marketer_id' => $this->marketer->id,
            'feature_flags' => Tenant::featureFlagsWithModules([], Tenant::productModules()),
        ], $extra));
    }

    private function mr(): MedicalRepresentative
    {
        return MedicalRepresentative::create([
            'name' => 'Rafiq',
            'company' => 'Square',
            'is_active' => true,
        ]);
    }
}
