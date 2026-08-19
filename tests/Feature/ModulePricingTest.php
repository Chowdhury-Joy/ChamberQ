<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Services\PlanPricingService;
use Tests\TestCase;

class ModulePricingTest extends TestCase
{
    public function test_all_three_modules_use_solo_bundle_prices(): void
    {
        $prices = app(PlanPricingService::class)->listPricesForModules('solo', Tenant::productModules());

        $this->assertSame(25000, $prices['setup']);
        $this->assertSame(3000, $prices['monthly']);
    }

    public function test_front_door_only_prices(): void
    {
        $prices = app(PlanPricingService::class)->listPricesForModules('solo', [
            Tenant::MODULE_FRONT_DOOR,
        ]);

        $this->assertSame(5000, $prices['setup']);
        $this->assertSame(1000, $prices['monthly']);
    }

    public function test_prescription_only_prices(): void
    {
        $prices = app(PlanPricingService::class)->listPricesForModules('solo', [
            Tenant::MODULE_PRESCRIPTION,
        ]);

        $this->assertSame(4500, $prices['setup']);
        $this->assertSame(250, $prices['monthly']);
    }

    public function test_live_queue_only_prices(): void
    {
        $prices = app(PlanPricingService::class)->listPricesForModules('solo', [
            Tenant::MODULE_LIVE_QUEUE,
        ]);

        $this->assertSame(18000, $prices['setup']);
        $this->assertSame(2000, $prices['monthly']);
    }

    public function test_website_plus_queue_sums_units_without_bundle_discount(): void
    {
        $prices = app(PlanPricingService::class)->listPricesForModules('solo', [
            Tenant::MODULE_FRONT_DOOR,
            Tenant::MODULE_LIVE_QUEUE,
        ]);

        $this->assertSame(23000, $prices['setup']);
        $this->assertSame(3000, $prices['monthly']);
    }

    public function test_website_plus_prescription_sums_units(): void
    {
        $prices = app(PlanPricingService::class)->listPricesForModules('solo', [
            Tenant::MODULE_FRONT_DOOR,
            Tenant::MODULE_PRESCRIPTION,
        ]);

        $this->assertSame(9500, $prices['setup']);
        $this->assertSame(1250, $prices['monthly']);
    }

    public function test_clinic_tier_ignores_modules_for_list_price(): void
    {
        $prices = app(PlanPricingService::class)->listPricesForModules('clinic', [
            Tenant::MODULE_FRONT_DOOR,
        ]);

        $this->assertSame(75000, $prices['setup']);
        $this->assertSame(7500, $prices['monthly']);
    }

    public function test_list_prices_for_tenant_reads_enabled_modules(): void
    {
        $tenant = new Tenant([
            'plan_tier' => 'solo',
            'feature_flags' => Tenant::featureFlagsWithModules([], [
                Tenant::MODULE_PRESCRIPTION,
            ]),
        ]);

        $prices = app(PlanPricingService::class)->listPricesForTenant($tenant);

        $this->assertSame(4500, $prices['setup']);
        $this->assertSame(250, $prices['monthly']);
    }

    public function test_prescription_lifetime_free_waives_rx_units_on_website_plus_rx(): void
    {
        $quote = app(PlanPricingService::class)->quote(
            'solo',
            [Tenant::MODULE_FRONT_DOOR, Tenant::MODULE_PRESCRIPTION],
            prescriptionLifetimeFree: true,
        );

        $this->assertSame(9500, $quote['list_setup']);
        $this->assertSame(1250, $quote['list_monthly']);
        $this->assertSame(5000, $quote['setup_due']);
        $this->assertSame(1000, $quote['monthly_due']);
    }

    public function test_prescription_lifetime_free_on_maestro_bundle_subtracts_rx_units(): void
    {
        $quote = app(PlanPricingService::class)->quote(
            'solo',
            Tenant::productModules(),
            prescriptionLifetimeFree: true,
        );

        $this->assertSame(25000, $quote['list_setup']);
        $this->assertSame(3000, $quote['list_monthly']);
        $this->assertSame(20500, $quote['setup_due']);
        $this->assertSame(2750, $quote['monthly_due']);
    }

    public function test_paying_override_wins_after_rx_free(): void
    {
        $quote = app(PlanPricingService::class)->quote(
            'solo',
            Tenant::productModules(),
            prescriptionLifetimeFree: true,
            payingSetup: 20000,
            payingMonthly: 2400,
        );

        $this->assertSame(25000, $quote['list_setup']);
        $this->assertSame(20000, $quote['setup_due']);
        $this->assertSame(2400, $quote['monthly_due']);
    }

    public function test_module_mix_paying_override(): void
    {
        $quote = app(PlanPricingService::class)->quote(
            'solo',
            [Tenant::MODULE_FRONT_DOOR],
            payingSetup: 2000,
        );

        $this->assertSame(5000, $quote['list_setup']);
        $this->assertSame(2000, $quote['setup_due']);
        $this->assertSame(1000, $quote['monthly_due']);
    }

    public function test_clinic_rx_free_does_not_change_clinic_list(): void
    {
        $quote = app(PlanPricingService::class)->quote(
            'clinic',
            Tenant::productModules(),
            prescriptionLifetimeFree: true,
        );

        $this->assertSame(75000, $quote['list_setup']);
        $this->assertSame(75000, $quote['setup_due']);
        $this->assertSame(7500, $quote['monthly_due']);
    }
}
