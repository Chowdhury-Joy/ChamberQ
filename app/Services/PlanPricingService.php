<?php

namespace App\Services;

use App\Models\DiscountCode;
use App\Models\Tenant;

class PlanPricingService
{
    public function __construct(
        private readonly DiscountCalculator $discountCalculator,
    ) {}

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

    /**
     * Sticker list plus due after Super Admin offer ticks and a discount code.
     * Order: module list → waive Prescription units (Solo only) → percent code
     * → 50% prepaid-year setup. List snapshots stay the pre-offer sticker.
     *
     * @param  list<string>  $modules
     * @return array{
     *     list_setup: int,
     *     list_monthly: int,
     *     setup_due: int,
     *     monthly_due: int,
     *     setup_discount: int,
     *     monthly_discount: int
     * }
     */
    public function quote(
        string $planTier,
        array $modules,
        ?DiscountCode $code = null,
        bool $prescriptionLifetimeFree = false,
        bool $prepaidYearSetup = false,
    ): array {
        $list = $this->listPricesForModules($planTier, $modules);
        $setupDue = $list['setup'];
        $monthlyDue = $list['monthly'];
        $setupDiscount = 0;
        $monthlyDiscount = 0;

        $selected = array_values(array_intersect(Tenant::productModules(), $modules));
        if (
            $prescriptionLifetimeFree
            && $planTier !== 'clinic'
            && in_array(Tenant::MODULE_PRESCRIPTION, $selected, true)
        ) {
            $rx = config('marketing.modules.prescription', []);
            $rxSetup = (int) ($rx['setup'] ?? 0);
            $rxMonthly = (int) ($rx['monthly'] ?? 0);
            $setupDue = max(0, $setupDue - $rxSetup);
            $monthlyDue = max(0, $monthlyDue - $rxMonthly);
            $setupDiscount += $rxSetup;
            $monthlyDiscount += $rxMonthly;
        }

        if ($code) {
            $fromCode = $this->discountCalculator->calculate($setupDue, $monthlyDue, $code);
            $setupDiscount += $fromCode['setup_discount'];
            $monthlyDiscount += $fromCode['monthly_discount'];
            $setupDue = $fromCode['setup_due'];
            $monthlyDue = $fromCode['monthly_due'];
        }

        if ($prepaidYearSetup) {
            $half = (int) round($setupDue * 0.5);
            $setupDiscount += $half;
            $setupDue -= $half;
        }

        return [
            'list_setup' => $list['setup'],
            'list_monthly' => $list['monthly'],
            'setup_due' => $setupDue,
            'monthly_due' => $monthlyDue,
            'setup_discount' => $setupDiscount,
            'monthly_discount' => $monthlyDiscount,
        ];
    }

    /**
     * @return array{
     *     list_setup: int,
     *     list_monthly: int,
     *     setup_due: int,
     *     monthly_due: int,
     *     setup_discount: int,
     *     monthly_discount: int
     * }
     */
    public function quoteForTenant(Tenant $tenant, ?DiscountCode $code = null): array
    {
        return $this->quote(
            (string) $tenant->plan_tier,
            $tenant->enabledProductModules(),
            $code,
            (bool) $tenant->offer_prescription_lifetime_free,
            (bool) $tenant->offer_prepaid_year_setup,
        );
    }

    public function modulePriceHelperText(): string
    {
        $modules = config('marketing.modules', []);
        $frontDoor = $modules['front_door'] ?? [];
        $prescription = $modules['prescription'] ?? [];
        $liveQueue = $modules['live_queue'] ?? [];
        $bundle = $modules['bundle_all'] ?? [];

        return sprintf(
            'Front door ৳%s + ৳%s/mo · Prescription ৳%s setup (৳%s/mo) · Live queue ৳%s + ৳%s/mo · All three ৳%s + ৳%s/mo. SMS is optional. Maestro pricing follows these boxes; Clinic uses Clinic list price (unchecking modules does not lower it).',
            number_format((int) ($frontDoor['setup'] ?? 0)),
            number_format((int) ($frontDoor['monthly'] ?? 0)),
            number_format((int) ($prescription['setup'] ?? 0)),
            number_format((int) ($prescription['monthly'] ?? 0)),
            number_format((int) ($liveQueue['setup'] ?? 0)),
            number_format((int) ($liveQueue['monthly'] ?? 0)),
            number_format((int) ($bundle['setup'] ?? 0)),
            number_format((int) ($bundle['monthly'] ?? 0)),
        );
    }
}
