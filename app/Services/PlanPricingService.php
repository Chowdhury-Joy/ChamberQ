<?php

namespace App\Services;

use App\Models\Tenant;

class PlanPricingService
{
    /**
     * List prices for a plan tier alone (ignores modules). Prefer
     * `listPricesForTenant()` / `listPricesForModules()` for Solo billing.
     *
     * @return array{setup: int, monthly: int}
     */
    public function listPricesForTier(string $planTier): array
    {
        $plans = config('marketing.plans', []);
        $key = $planTier === 'clinic' ? 'clinic' : 'solo';
        $plan = $plans[$key] ?? $plans['solo'];

        return [
            'setup' => (int) ($plan['setup'] ?? 0),
            'monthly' => (int) ($plan['monthly'] ?? 0),
        ];
    }

    /**
     * @return array{setup: int, monthly: int}
     */
    public function listPricesForTenant(Tenant $tenant): array
    {
        return $this->listPricesForModules(
            (string) $tenant->plan_tier,
            $tenant->enabledProductModules(),
        );
    }

    /**
     * Solo: sum of selected module unit prices, or the all-three bundle.
     * Clinic: fixed Clinic list price (modules still gate features).
     *
     * @param  list<string>  $modules
     * @return array{setup: int, monthly: int}
     */
    public function listPricesForModules(string $planTier, array $modules): array
    {
        if ($planTier === 'clinic') {
            return $this->listPricesForTier('clinic');
        }

        $selected = array_values(array_intersect(Tenant::productModules(), $modules));

        if ($selected === []) {
            return ['setup' => 0, 'monthly' => 0];
        }

        $config = config('marketing.modules', []);

        if (count($selected) === count(Tenant::productModules())) {
            $bundle = $config['bundle_all'] ?? [];

            return [
                'setup' => (int) ($bundle['setup'] ?? $this->listPricesForTier('solo')['setup']),
                'monthly' => (int) ($bundle['monthly'] ?? $this->listPricesForTier('solo')['monthly']),
            ];
        }

        $setup = 0;
        $monthly = 0;

        foreach ($selected as $module) {
            $row = $config[$module] ?? [];
            $setup += (int) ($row['setup'] ?? 0);
            $monthly += (int) ($row['monthly'] ?? 0);
        }

        return [
            'setup' => $setup,
            'monthly' => $monthly,
        ];
    }
}
