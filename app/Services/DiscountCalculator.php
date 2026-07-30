<?php

namespace App\Services;

use App\Models\DiscountCode;

class DiscountCalculator
{
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
    public function calculate(int $listSetup, int $listMonthly, ?DiscountCode $code = null): array
    {
        $setupDue = $listSetup;
        $monthlyDue = $listMonthly;
        $setupDiscount = 0;
        $monthlyDiscount = 0;

        if ($code && $code->isValidNow()) {
            if ($code->setup_percent !== null && $code->setup_percent > 0) {
                $setupDiscount = (int) round($listSetup * ($code->setup_percent / 100));
                $setupDue = max(0, $listSetup - $setupDiscount);
            }

            if ($code->monthly_percent !== null && $code->monthly_percent > 0) {
                $monthlyDiscount = (int) round($listMonthly * ($code->monthly_percent / 100));
                $monthlyDue = max(0, $listMonthly - $monthlyDiscount);
            }
        }

        return [
            'list_setup' => $listSetup,
            'list_monthly' => $listMonthly,
            'setup_due' => $setupDue,
            'monthly_due' => $monthlyDue,
            'setup_discount' => $setupDiscount,
            'monthly_discount' => $monthlyDiscount,
        ];
    }
}
