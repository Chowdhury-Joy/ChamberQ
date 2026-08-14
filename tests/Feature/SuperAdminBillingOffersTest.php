<?php

namespace Tests\Feature;

use App\Filament\SuperAdmin\Resources\Tenants\Pages\EditTenant;
use App\Models\BillingPayment;
use App\Models\Commission;
use App\Models\Marketer;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CommissionService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class SuperAdminBillingOffersTest extends TestCase
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

    public function test_maestro_prescription_free_for_life_waives_rx_units_from_bundle(): void
    {
        $tenant = $this->maestroTenant([
            'offer_prescription_lifetime_free' => true,
        ]);

        $service = app(CommissionService::class);
        $service->applyPricingToTenant($tenant);
        $tenant->save();

        $tenant->refresh();
        $this->assertSame(15000, (int) $tenant->list_setup_amount);
        $this->assertSame(3000, (int) $tenant->list_monthly_amount);
        $this->assertSame(12500, (int) $tenant->setup_amount_due);
        $this->assertSame(2750, (int) $tenant->monthly_amount_due);
    }

    public function test_website_plus_prescription_with_rx_free_equals_front_door_only(): void
    {
        $tenant = Tenant::create([
            'id' => 'site-rx',
            'plan_tier' => 'solo',
            'marketer_id' => $this->marketer->id,
            'offer_prescription_lifetime_free' => true,
            'feature_flags' => Tenant::featureFlagsWithModules([], [
                Tenant::MODULE_FRONT_DOOR,
                Tenant::MODULE_PRESCRIPTION,
            ]),
        ]);

        app(CommissionService::class)->applyPricingToTenant($tenant);
        $tenant->save();
        $tenant->refresh();

        $this->assertSame(5500, (int) $tenant->list_setup_amount);
        $this->assertSame(1250, (int) $tenant->list_monthly_amount);
        $this->assertSame(3000, (int) $tenant->setup_amount_due);
        $this->assertSame(1000, (int) $tenant->monthly_amount_due);
    }

    public function test_prepaid_year_halves_setup_after_rx_free(): void
    {
        $tenant = $this->maestroTenant([
            'offer_prescription_lifetime_free' => true,
            'offer_prepaid_year_setup' => true,
        ]);

        app(CommissionService::class)->applyPricingToTenant($tenant);
        $tenant->save();
        $tenant->refresh();

        $this->assertSame(15000, (int) $tenant->list_setup_amount);
        $this->assertSame(6250, (int) $tenant->setup_amount_due);
        $this->assertSame(2750, (int) $tenant->monthly_amount_due);
    }

    public function test_rx_free_does_not_subtract_when_prescription_is_not_included(): void
    {
        $tenant = Tenant::create([
            'id' => 'site-only',
            'plan_tier' => 'solo',
            'offer_prescription_lifetime_free' => true,
            'feature_flags' => Tenant::featureFlagsWithModules([], [
                Tenant::MODULE_FRONT_DOOR,
            ]),
        ]);

        app(CommissionService::class)->applyPricingToTenant($tenant);
        $tenant->save();
        $tenant->refresh();

        $this->assertSame(3000, (int) $tenant->setup_amount_due);
        $this->assertSame(1000, (int) $tenant->monthly_amount_due);
    }

    public function test_pending_setup_commission_updates_when_modules_change(): void
    {
        $tenant = $this->maestroTenant();
        $service = app(CommissionService::class);
        $service->applyPricingToTenant($tenant);
        $tenant->save();
        $service->createPendingSetupCommission($tenant);

        $this->assertSame(15000, (int) Commission::where('tenant_id', $tenant->id)->value('base_amount'));

        $tenant->feature_flags = Tenant::featureFlagsWithModules($tenant->feature_flags, [
            Tenant::MODULE_FRONT_DOOR,
        ]);
        $service->applyPricingToTenant($tenant);
        $tenant->save();
        $service->createPendingSetupCommission($tenant);

        $commission = Commission::where('tenant_id', $tenant->id)->first();
        $this->assertSame(3000, (int) $commission->base_amount);
        $this->assertSame(600, (int) $commission->commission_amount);
        $this->assertSame(Commission::STATUS_PENDING, $commission->status);
    }

    public function test_owed_setup_commission_is_not_rewritten_when_modules_change(): void
    {
        $tenant = $this->maestroTenant();
        $service = app(CommissionService::class);
        $service->applyPricingToTenant($tenant);
        $tenant->save();
        $service->createPendingSetupCommission($tenant);
        $service->confirmSetupPayment($tenant, $this->superAdmin, null, 15000);

        $tenant->feature_flags = Tenant::featureFlagsWithModules($tenant->feature_flags, [
            Tenant::MODULE_FRONT_DOOR,
        ]);
        $service->applyPricingToTenant($tenant);
        $tenant->save();
        $service->createPendingSetupCommission($tenant);

        $commission = Commission::where('tenant_id', $tenant->id)
            ->where('type', Commission::TYPE_SETUP)
            ->first();
        $this->assertSame(Commission::STATUS_OWED, $commission->status);
        $this->assertSame(15000, (int) $commission->base_amount);
        $this->assertSame(3000, (int) $commission->commission_amount);
    }

    public function test_pending_monthly_commission_updates_when_monthly_due_changes(): void
    {
        $tenant = $this->maestroTenant();
        $service = app(CommissionService::class);
        $service->applyPricingToTenant($tenant);
        $tenant->update([
            'setup_paid_at' => now(),
            'billing_status' => 'active',
        ]);

        $period = now()->format('Y-m');
        $service->generateMonthlyPendingCommissions($period);

        $this->assertSame(300, (int) Commission::where('tenant_id', $tenant->id)
            ->where('type', Commission::TYPE_MONTHLY)
            ->value('commission_amount'));

        $tenant->offer_prescription_lifetime_free = true;
        $service->applyPricingToTenant($tenant);
        $tenant->save();

        $commission = Commission::where('tenant_id', $tenant->id)
            ->where('type', Commission::TYPE_MONTHLY)
            ->where('period', $period)
            ->first();
        $this->assertSame(Commission::STATUS_PENDING, $commission->status);
        $this->assertSame(2750, (int) $commission->base_amount);
        $this->assertSame(275, (int) $commission->commission_amount);
    }

    public function test_confirm_year_prepaid_creates_twelve_owed_monthly_rows(): void
    {
        $tenant = $this->maestroTenant();
        $service = app(CommissionService::class);
        $service->applyPricingToTenant($tenant);
        $tenant->update([
            'setup_paid_at' => now(),
            'billing_status' => 'active',
        ]);

        $count = $service->confirmYearPrepaid($tenant, $this->superAdmin, 'year prepaid');

        $this->assertSame(12, $count);
        $this->assertSame(12, BillingPayment::query()
            ->where('tenant_id', $tenant->id)
            ->where('type', BillingPayment::TYPE_MONTHLY)
            ->count());
        $this->assertSame(12, Commission::query()
            ->where('tenant_id', $tenant->id)
            ->where('type', Commission::TYPE_MONTHLY)
            ->where('status', Commission::STATUS_OWED)
            ->count());
        $this->assertSame(36000, (int) BillingPayment::query()
            ->where('tenant_id', $tenant->id)
            ->where('type', BillingPayment::TYPE_MONTHLY)
            ->sum('amount_paid'));
        $this->assertSame(3600, (int) Commission::query()
            ->where('tenant_id', $tenant->id)
            ->where('type', Commission::TYPE_MONTHLY)
            ->sum('commission_amount'));
    }

    public function test_confirm_year_prepaid_skips_already_confirmed_months(): void
    {
        $tenant = $this->maestroTenant();
        $service = app(CommissionService::class);
        $service->applyPricingToTenant($tenant);
        $tenant->update([
            'setup_paid_at' => now(),
            'billing_status' => 'active',
        ]);

        $period = now()->format('Y-m');
        $service->confirmMonthlyPayment($tenant, $period, $this->superAdmin);

        $count = $service->confirmYearPrepaid($tenant, $this->superAdmin, 'rest of year');

        $this->assertSame(11, $count);
        $this->assertSame(12, BillingPayment::query()
            ->where('tenant_id', $tenant->id)
            ->where('type', BillingPayment::TYPE_MONTHLY)
            ->count());
    }

    public function test_super_admin_tenant_form_uses_maestro_label_and_offer_ticks(): void
    {
        $tenant = $this->maestroTenant();
        app(CommissionService::class)->applyPricingToTenant($tenant);
        $tenant->save();

        $this->actingAs($this->superAdmin);
        Filament::setCurrentPanel(Filament::getPanel('superAdmin'));

        Livewire::test(EditTenant::class, ['record' => $tenant->getKey()])
            ->assertSee('Maestro')
            ->assertSee('Prescription free for life')
            ->assertSee('Prepaid year')
            ->fillForm([
                'offer_prescription_lifetime_free' => true,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $tenant->refresh();
        $this->assertTrue((bool) $tenant->offer_prescription_lifetime_free);
        $this->assertSame(12500, (int) $tenant->setup_amount_due);
        $this->assertSame(2750, (int) $tenant->monthly_amount_due);
    }

    public function test_plan_tier_label_is_maestro_for_solo(): void
    {
        $this->assertSame('Maestro', Tenant::planTierLabel('solo'));
        $this->assertSame('Clinic', Tenant::planTierLabel('clinic'));
    }

    public function test_clinic_prescription_free_does_not_lower_clinic_due(): void
    {
        $tenant = Tenant::create([
            'id' => 'bigclinic',
            'plan_tier' => 'clinic',
            'offer_prescription_lifetime_free' => true,
            'feature_flags' => Tenant::featureFlagsWithModules([], Tenant::productModules()),
        ]);

        app(CommissionService::class)->applyPricingToTenant($tenant);
        $tenant->save();
        $tenant->refresh();

        $this->assertSame(75000, (int) $tenant->setup_amount_due);
        $this->assertSame(7500, (int) $tenant->monthly_amount_due);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function maestroTenant(array $extra = []): Tenant
    {
        return Tenant::create(array_merge([
            'id' => 'drkarim',
            'plan_tier' => 'solo',
            'marketer_id' => $this->marketer->id,
            'feature_flags' => Tenant::featureFlagsWithModules([], Tenant::productModules()),
        ], $extra));
    }
}
