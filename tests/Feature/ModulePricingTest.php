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

        $this->assertSame(15000, $prices['setup']);
        $this->assertSame(3000, $prices['monthly']);
    }

    public function test_front_door_only_prices(): void
    {
        $prices = app(PlanPricingService::class)->listPricesForModules('solo', [
            Tenant::MODULE_FRONT_DOOR,
        ]);

        $this->assertSame(7500, $prices['setup']);
        $this->assertSame(1000, $prices['monthly']);
    }

    public function test_prescription_only_prices(): void
    {
        $prices = app(PlanPricingService::class)->listPricesForModules('solo', [
            Tenant::MODULE_PRESCRIPTION,
        ]);

        $this->assertSame(2500, $prices['setup']);
        $this->assertSame(0, $prices['monthly']);
    }

    public function test_live_queue_only_prices(): void
    {
        $prices = app(PlanPricingService::class)->listPricesForModules('solo', [
            Tenant::MODULE_LIVE_QUEUE,
        ]);

        $this->assertSame(7500, $prices['setup']);
        $this->assertSame(2000, $prices['monthly']);
    }

    public function test_website_plus_queue_sums_units_without_bundle_discount(): void
    {
        $prices = app(PlanPricingService::class)->listPricesForModules('solo', [
            Tenant::MODULE_FRONT_DOOR,
            Tenant::MODULE_LIVE_QUEUE,
        ]);

        $this->assertSame(15000, $prices['setup']);
        $this->assertSame(3000, $prices['monthly']);
    }

    public function test_website_plus_prescription_sums_units(): void
    {
        $prices = app(PlanPricingService::class)->listPricesForModules('solo', [
            Tenant::MODULE_FRONT_DOOR,
            Tenant::MODULE_PRESCRIPTION,
        ]);

        $this->assertSame(10000, $prices['setup']);
        $this->assertSame(1000, $prices['monthly']);
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

        $this->assertSame(2500, $prices['setup']);
        $this->assertSame(0, $prices['monthly']);
    }
}
