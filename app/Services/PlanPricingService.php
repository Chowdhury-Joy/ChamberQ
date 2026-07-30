<?php

namespace App\Services;

use App\Models\DiscountCode;

class PlanPricingService
{
    /**
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
}
