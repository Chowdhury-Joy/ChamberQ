<?php

namespace App\Services;

use App\Models\Tenant;
use InvalidArgumentException;

class DealCommissionRates
{
    public const KIND_SETUP = 'setup';

    public const KIND_YEAR1_MONTHLY = 'year1_monthly';

    public const KIND_YEAR1_PREPAID = 'year1_prepaid';

    public const KIND_YEAR2 = 'year2';

    public function __construct(private readonly Tenant $tenant) {}

    public function hasMr(): bool
    {
        return filled($this->tenant->medical_representative_id);
    }

    public function marketerRate(string $kind): float
    {
        return $this->rate($kind, 'marketer');
    }

    public function mrRate(string $kind): float
    {
        if (! $this->hasMr()) {
            return 0.0;
        }

        return $this->rate($kind, 'mr');
    }

    public function kindForMonthlyPeriod(string $period): string
    {
        return $this->tenant->serviceYearForPeriod($period) <= 1
            ? self::KIND_YEAR1_MONTHLY
            : self::KIND_YEAR2;
    }

    public function prepaidKindForYear(int $serviceYear): string
    {
        return $serviceYear <= 1 ? self::KIND_YEAR1_PREPAID : self::KIND_YEAR2;
    }

    /**
     * @return array{mr: float, marketer: float}
     */
    public function pair(string $kind): array
    {
        $mr = $this->mrRate($kind);
        $marketer = $this->marketerRate($kind);
        $this->assertPair($mr, $marketer);

        return ['mr' => $mr, 'marketer' => $marketer];
    }

    public static function assertPair(float $mr, float $marketer): void
    {
        if ($mr < 0 || $marketer < 0 || $mr > 1 || $marketer > 1) {
            throw new InvalidArgumentException('Commission rates must be between 0% and 100%.');
        }

        if (round($mr + $marketer, 4) > 1.0) {
            throw new InvalidArgumentException('MR and marketer cuts cannot exceed 100%.');
        }
    }

    public static function percentToRate(mixed $percent): ?float
    {
        if ($percent === null || $percent === '') {
            return null;
        }

        return round(((float) $percent) / 100, 4);
    }

    public static function rateToPercent(mixed $rate): ?float
    {
        if ($rate === null || $rate === '') {
            return null;
        }

        return round((float) $rate * 100, 2);
    }

    private function rate(string $kind, string $payee): float
    {
        $override = $this->override($kind, $payee);
        if ($override !== null) {
            return $this->clamp($override);
        }

        if ($kind === self::KIND_YEAR1_MONTHLY) {
            $defaults = config('commissions.year1_monthly', []);

            return $this->clamp((float) ($defaults[$payee] ?? 0));
        }

        $path = $this->hasMr() ? 'with_mr' : 'direct';
        $defaults = config('commissions.'.$kind.'.'.$path, []);

        return $this->clamp((float) ($defaults[$payee] ?? 0));
    }

    private function override(string $kind, string $payee): ?float
    {
        $column = match ([$kind, $payee]) {
            [self::KIND_SETUP, 'mr'] => 'commission_setup_mr_rate',
            [self::KIND_SETUP, 'marketer'] => 'commission_setup_marketer_rate',
            [self::KIND_YEAR1_PREPAID, 'mr'] => 'commission_year1_prepaid_mr_rate',
            [self::KIND_YEAR1_PREPAID, 'marketer'] => 'commission_year1_prepaid_marketer_rate',
            [self::KIND_YEAR2, 'mr'] => 'commission_year2_mr_rate',
            [self::KIND_YEAR2, 'marketer'] => 'commission_year2_marketer_rate',
            default => null,
        };

        if ($column === null) {
            return null;
        }

        $value = $this->tenant->{$column};

        return $value === null ? null : (float) $value;
    }

    private function clamp(float $rate): float
    {
        return max(0.0, min(1.0, $rate));
    }
}
