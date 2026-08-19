<?php

namespace App\Services;

use App\Models\BillingPayment;
use App\Models\Commission;
use App\Models\DiscountCode;
use App\Models\Marketer;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CommissionService
{
    public function __construct(
        private readonly PlanPricingService $planPricing,
    ) {}

    /**
     * List vs due for Super Admin preview and tenant snapshots.
     *
     * List is the module (or Clinic) sticker. Due applies Prescription-free-for-life
     * (Solo only), then a percent discount code, then a paying-price override.
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
    public function quoteAmounts(
        string $planTier,
        array $modules,
        ?DiscountCode $code = null,
        bool $rxLifetimeFree = false,
        ?int $payingSetup = null,
        ?int $payingMonthly = null,
    ): array {
        return $this->planPricing->quote(
            $planTier,
            $modules,
            $code,
            $rxLifetimeFree,
            $payingSetup,
            $payingMonthly,
        );
    }

    /**
     * Snapshot plan prices and apply discount when a tenant is onboarded.
     */
    public function applyPricingToTenant(Tenant $tenant, ?DiscountCode $code = null, bool $countRedemption = false): Tenant
    {
        $code ??= $tenant->discount_code_id
            ? DiscountCode::find($tenant->discount_code_id)
            : null;

        $amounts = $this->quoteAmounts(
            (string) $tenant->plan_tier,
            $tenant->enabledProductModules(),
            $code,
            (bool) $tenant->offer_prescription_lifetime_free,
            $tenant->paying_setup_amount !== null ? (int) $tenant->paying_setup_amount : null,
            $tenant->paying_monthly_amount !== null ? (int) $tenant->paying_monthly_amount : null,
        );

        $tenant->fill([
            'list_setup_amount' => $amounts['list_setup'],
            'list_monthly_amount' => $amounts['list_monthly'],
            'setup_amount_due' => $amounts['setup_due'],
            'monthly_amount_due' => $amounts['monthly_due'],
            'discount_code_id' => $code?->id,
        ]);

        if ($code && $code->isValidNow() && $countRedemption) {
            $code->increment('redemption_count');
        }

        $this->syncPendingCommissions($tenant);

        return $tenant;
    }

    /**
     * Recalculate pending commission rows after a re-price. Never touches owed/paid.
     */
    public function syncPendingCommissions(Tenant $tenant): void
    {
        $this->createPendingSetupCommission($tenant);
        $this->refreshPendingMonthlyCommissions($tenant);
        $this->refreshPendingYearPrepaidCommissions($tenant);
    }

    /**
     * @return array{
     *     setup_rate: float,
     *     monthly_rate: float,
     *     setup_commission: int,
     *     monthly_commission: int,
     *     display_name: string,
     *     mr_name: ?string,
     *     mr_setup_rate: float,
     *     mr_setup_commission: int,
     *     year1_prepaid_marketer_rate: float,
     *     year1_prepaid_marketer_commission: int,
     *     year1_prepaid_mr_rate: float,
     *     year1_prepaid_mr_commission: int,
     *     year2_marketer_rate: float,
     *     year2_marketer_commission: int,
     *     year2_mr_rate: float,
     *     year2_mr_commission: int
     * }|null
     */
    public function previewCommission(
        ?int $marketerId,
        int $setupDue,
        int $monthlyDue,
        ?int $medicalRepresentativeId = null,
        ?Tenant $tenant = null,
    ): ?array {
        if (! $marketerId && ! $medicalRepresentativeId) {
            return null;
        }

        $scratch = $tenant ? $tenant->replicate() : new Tenant;
        $scratch->marketer_id = $marketerId;
        $scratch->medical_representative_id = $medicalRepresentativeId;
        if ($tenant) {
            foreach ([
                'commission_setup_mr_rate',
                'commission_setup_marketer_rate',
                'commission_year1_prepaid_mr_rate',
                'commission_year1_prepaid_marketer_rate',
                'commission_year2_mr_rate',
                'commission_year2_marketer_rate',
            ] as $column) {
                $scratch->{$column} = $tenant->{$column};
            }
        }

        $rates = new DealCommissionRates($scratch);
        $year = $monthlyDue * Tenant::PREPAID_YEAR_MONTHS;
        $marketer = $marketerId ? Marketer::find($marketerId) : null;

        return [
            'display_name' => (string) ($marketer?->display_name ?? 'Marketer'),
            'setup_rate' => $rates->marketerRate(DealCommissionRates::KIND_SETUP),
            'monthly_rate' => $rates->marketerRate(DealCommissionRates::KIND_YEAR1_MONTHLY),
            'setup_commission' => $this->commissionAmount($setupDue, $rates->marketerRate(DealCommissionRates::KIND_SETUP)),
            'monthly_commission' => $this->commissionAmount($monthlyDue, $rates->marketerRate(DealCommissionRates::KIND_YEAR1_MONTHLY)),
            'mr_name' => $scratch->medicalRepresentative?->name,
            'mr_setup_rate' => $rates->mrRate(DealCommissionRates::KIND_SETUP),
            'mr_setup_commission' => $this->commissionAmount($setupDue, $rates->mrRate(DealCommissionRates::KIND_SETUP)),
            'year1_prepaid_marketer_rate' => $rates->marketerRate(DealCommissionRates::KIND_YEAR1_PREPAID),
            'year1_prepaid_marketer_commission' => $this->commissionAmount($year, $rates->marketerRate(DealCommissionRates::KIND_YEAR1_PREPAID)),
            'year1_prepaid_mr_rate' => $rates->mrRate(DealCommissionRates::KIND_YEAR1_PREPAID),
            'year1_prepaid_mr_commission' => $this->commissionAmount($year, $rates->mrRate(DealCommissionRates::KIND_YEAR1_PREPAID)),
            'year2_marketer_rate' => $rates->marketerRate(DealCommissionRates::KIND_YEAR2),
            'year2_marketer_commission' => $this->commissionAmount($monthlyDue, $rates->marketerRate(DealCommissionRates::KIND_YEAR2)),
            'year2_mr_rate' => $rates->mrRate(DealCommissionRates::KIND_YEAR2),
            'year2_mr_commission' => $this->commissionAmount($monthlyDue, $rates->mrRate(DealCommissionRates::KIND_YEAR2)),
        ];
    }

    /**
     * Create or refresh pending setup commission rows for marketer and MR.
     * Owed/paid rows are left alone.
     */
    public function createPendingSetupCommission(Tenant $tenant): ?Commission
    {
        $rates = new DealCommissionRates($tenant);
        $base = (int) $tenant->setup_amount_due;
        $first = null;

        if ($tenant->marketer_id) {
            $first = $this->upsertPending(
                $tenant,
                Commission::TYPE_SETUP,
                null,
                $base,
                $rates->marketerRate(DealCommissionRates::KIND_SETUP),
                marketerId: (int) $tenant->marketer_id,
            );
        }

        if ($tenant->medical_representative_id) {
            $row = $this->upsertPending(
                $tenant,
                Commission::TYPE_SETUP,
                null,
                $base,
                $rates->mrRate(DealCommissionRates::KIND_SETUP),
                mrId: (int) $tenant->medical_representative_id,
            );
            $first ??= $row;
        }

        return $first;
    }

    private function refreshPendingMonthlyCommissions(Tenant $tenant): void
    {
        Commission::query()
            ->where('tenant_id', $tenant->id)
            ->where('type', Commission::TYPE_MONTHLY)
            ->where('status', Commission::STATUS_PENDING)
            ->get()
            ->each(function (Commission $commission) use ($tenant): void {
                $kind = (new DealCommissionRates($tenant))->kindForMonthlyPeriod((string) $commission->period);
                $rate = $this->rateForRow($tenant, $kind, $commission);
                $base = (int) $tenant->monthly_amount_due;

                if ($rate <= 0) {
                    $commission->update([
                        'status' => Commission::STATUS_VOID,
                        'payout_note' => 'Year 1 monthly is not commissionable.',
                    ]);

                    return;
                }

                $commission->update([
                    'base_amount' => $base,
                    'rate' => $rate,
                    'commission_amount' => $this->commissionAmount($base, $rate),
                ]);
            });
    }

    private function refreshPendingYearPrepaidCommissions(Tenant $tenant): void
    {
        Commission::query()
            ->where('tenant_id', $tenant->id)
            ->where('type', Commission::TYPE_YEAR_PREPAID)
            ->where('status', Commission::STATUS_PENDING)
            ->get()
            ->each(function (Commission $commission) use ($tenant): void {
                $year = $tenant->serviceYearForPeriod((string) $commission->period);
                $kind = (new DealCommissionRates($tenant))->prepaidKindForYear($year);
                $rate = $this->rateForRow($tenant, $kind, $commission);
                $base = (int) $tenant->monthly_amount_due * Tenant::PREPAID_YEAR_MONTHS;
                $commission->update([
                    'base_amount' => $base,
                    'rate' => $rate,
                    'commission_amount' => $this->commissionAmount($base, $rate),
                ]);
            });
    }

    public function confirmSetupPayment(Tenant $tenant, User $confirmedBy, ?string $notes = null, ?int $amountPaid = null): BillingPayment
    {
        return DB::transaction(function () use ($tenant, $confirmedBy, $notes, $amountPaid) {
            $paid = $amountPaid ?? (int) $tenant->setup_amount_due;
            $list = (int) $tenant->list_setup_amount;
            $discount = max(0, $list - $paid);

            $billing = BillingPayment::updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'type' => BillingPayment::TYPE_SETUP,
                    'period' => null,
                ],
                [
                    'list_amount' => $list,
                    'discount_amount' => $discount,
                    'amount_paid' => $paid,
                    'discount_code_id' => $tenant->discount_code_id,
                    'confirmed_by' => $confirmedBy->id,
                    'confirmed_at' => now(),
                    'notes' => $notes,
                ]
            );

            $tenant->update(['setup_paid_at' => now()]);

            $this->markKindOwed($tenant, DealCommissionRates::KIND_SETUP, Commission::TYPE_SETUP, null, $billing, $paid);

            return $billing;
        });
    }

    public function confirmMonthlyPayment(
        Tenant $tenant,
        string $period,
        User $confirmedBy,
        ?string $notes = null,
        ?int $amountPaid = null,
        bool $skipCommission = false,
    ): BillingPayment {
        return DB::transaction(function () use ($tenant, $period, $confirmedBy, $notes, $amountPaid, $skipCommission) {
            $paid = $amountPaid ?? (int) $tenant->monthly_amount_due;
            $list = (int) $tenant->list_monthly_amount;
            $discount = max(0, $list - $paid);

            $billing = BillingPayment::updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'type' => BillingPayment::TYPE_MONTHLY,
                    'period' => $period,
                ],
                [
                    'list_amount' => $list,
                    'discount_amount' => $discount,
                    'amount_paid' => $paid,
                    'discount_code_id' => $tenant->discount_code_id,
                    'confirmed_by' => $confirmedBy->id,
                    'confirmed_at' => now(),
                    'notes' => $notes,
                ]
            );

            if (! $skipCommission) {
                $kind = (new DealCommissionRates($tenant))->kindForMonthlyPeriod($period);
                $this->markKindOwed($tenant, $kind, Commission::TYPE_MONTHLY, $period, $billing, $paid);
            }

            return $billing;
        });
    }

    public function generateMonthlyPendingCommissions(?string $period = null): int
    {
        $period = $period ?? now()->format('Y-m');
        $count = 0;

        Tenant::query()
            ->where(function ($query): void {
                $query->whereNotNull('marketer_id')
                    ->orWhereNotNull('medical_representative_id');
            })
            ->whereNotNull('setup_paid_at')
            ->whereIn('billing_status', ['active', 'trial'])
            ->each(function (Tenant $tenant) use ($period, &$count): void {
                $kind = (new DealCommissionRates($tenant))->kindForMonthlyPeriod($period);
                $base = (int) $tenant->monthly_amount_due;

                if ($tenant->marketer_id) {
                    $marketer = $tenant->marketer;
                    if ($marketer && $marketer->is_active) {
                        $created = $this->createPendingIfMissing(
                            $tenant,
                            Commission::TYPE_MONTHLY,
                            $period,
                            $base,
                            (new DealCommissionRates($tenant))->marketerRate($kind),
                            marketerId: (int) $tenant->marketer_id,
                        );
                        if ($created) {
                            $count++;
                        }
                    }
                }

                if ($tenant->medical_representative_id) {
                    $created = $this->createPendingIfMissing(
                        $tenant,
                        Commission::TYPE_MONTHLY,
                        $period,
                        $base,
                        (new DealCommissionRates($tenant))->mrRate($kind),
                        mrId: (int) $tenant->medical_representative_id,
                    );
                    if ($created) {
                        $count++;
                    }
                }
            });

        return $count;
    }

    public function confirmYearPrepaid(
        Tenant $tenant,
        User $confirmedBy,
        ?string $notes = null,
        ?int $amountPaid = null,
        ?string $startPeriod = null,
    ): int {
        if (! $tenant->hasSetupPaid()) {
            throw new \InvalidArgumentException('Confirm setup payment before recording a prepaid year.');
        }

        $start = Carbon::createFromFormat('Y-m', $startPeriod ?? now()->format('Y-m'))?->startOfMonth();
        if (! $start) {
            throw new \InvalidArgumentException('Billing period must be YYYY-MM.');
        }

        $open = [];
        for ($i = 0; $i < Tenant::PREPAID_YEAR_MONTHS; $i++) {
            $period = $start->copy()->addMonths($i)->format('Y-m');
            $already = BillingPayment::query()
                ->where('tenant_id', $tenant->id)
                ->where('type', BillingPayment::TYPE_MONTHLY)
                ->where('period', $period)
                ->whereNotNull('confirmed_at')
                ->exists();

            if (! $already) {
                $open[] = $period;
            }
        }

        if ($open === []) {
            return 0;
        }

        $monthlyDue = (int) $tenant->monthly_amount_due;
        $fullYearTotal = $monthlyDue * Tenant::PREPAID_YEAR_MONTHS;
        $useMonthlyDue = $amountPaid === null || $amountPaid === $fullYearTotal;
        $n = count($open);
        $base = $useMonthlyDue ? $monthlyDue : intdiv($amountPaid, $n);
        $remainder = $useMonthlyDue ? 0 : ($amountPaid % $n);

        return DB::transaction(function () use ($tenant, $confirmedBy, $notes, $open, $base, $remainder) {
            $created = 0;
            $last = count($open) - 1;
            $buckets = [];

            foreach ($open as $i => $period) {
                $amount = $base + ($i === $last ? $remainder : 0);
                $billing = $this->confirmMonthlyPayment($tenant, $period, $confirmedBy, $notes, $amount, skipCommission: true);
                $year = min(2, $tenant->serviceYearForPeriod($period));
                $buckets[$year] ??= ['amount' => 0, 'period' => $period, 'billing' => $billing];
                $buckets[$year]['amount'] += $amount;
                $created++;
            }

            foreach ($buckets as $year => $bucket) {
                $kind = (new DealCommissionRates($tenant))->prepaidKindForYear((int) $year);
                $this->markKindOwed(
                    $tenant,
                    $kind,
                    Commission::TYPE_YEAR_PREPAID,
                    $bucket['period'],
                    $bucket['billing'],
                    $bucket['amount'],
                );
            }

            return $created;
        });
    }

    public function markCommissionPaid(Commission $commission, ?string $payoutNote = null): Commission
    {
        $commission->update([
            'status' => Commission::STATUS_PAID,
            'paid_at' => now(),
            'payout_note' => $payoutNote,
        ]);

        return $commission->fresh();
    }

    public function voidCommission(Commission $commission, ?string $note = null): Commission
    {
        $commission->update([
            'status' => Commission::STATUS_VOID,
            'payout_note' => $note,
        ]);

        return $commission->fresh();
    }

    private function markKindOwed(
        Tenant $tenant,
        string $kind,
        string $type,
        ?string $period,
        BillingPayment $billing,
        int $amountPaid,
    ): void {
        $rates = new DealCommissionRates($tenant);

        if ($tenant->marketer_id) {
            $this->markPayeeOwed(
                $tenant,
                $type,
                $period,
                $billing,
                $amountPaid,
                $rates->marketerRate($kind),
                marketerId: (int) $tenant->marketer_id,
            );
        }

        if ($tenant->medical_representative_id) {
            $this->markPayeeOwed(
                $tenant,
                $type,
                $period,
                $billing,
                $amountPaid,
                $rates->mrRate($kind),
                mrId: (int) $tenant->medical_representative_id,
            );
        }
    }

    private function markPayeeOwed(
        Tenant $tenant,
        string $type,
        ?string $period,
        BillingPayment $billing,
        int $amountPaid,
        float $rate,
        ?int $marketerId = null,
        ?int $mrId = null,
    ): void {
        if ($rate <= 0) {
            return;
        }

        $lookup = $this->payeeLookup($tenant, $type, $period, $marketerId, $mrId);
        $existing = Commission::where($lookup)->first();

        if ($existing && $existing->status === Commission::STATUS_PAID) {
            return;
        }

        Commission::updateOrCreate($lookup, [
            'marketer_id' => $marketerId,
            'medical_representative_id' => $mrId,
            'payee_key' => $lookup['payee_key'],
            'billing_payment_id' => $billing->id,
            'base_amount' => $amountPaid,
            'rate' => $rate,
            'commission_amount' => $this->commissionAmount($amountPaid, $rate),
            'status' => Commission::STATUS_OWED,
        ]);
    }

    private function upsertPending(
        Tenant $tenant,
        string $type,
        ?string $period,
        int $base,
        float $rate,
        ?int $marketerId = null,
        ?int $mrId = null,
    ): ?Commission {
        $lookup = $this->payeeLookup($tenant, $type, $period, $marketerId, $mrId);
        $existing = Commission::where($lookup)->first();

        if ($rate <= 0) {
            if ($existing && $existing->status === Commission::STATUS_PENDING) {
                $existing->update([
                    'status' => Commission::STATUS_VOID,
                    'payout_note' => 'Rate is 0% for this deal.',
                ]);
            }

            return $existing?->fresh();
        }

        $payload = [
            'marketer_id' => $marketerId,
            'medical_representative_id' => $mrId,
            'payee_key' => $lookup['payee_key'],
            'base_amount' => $base,
            'rate' => $rate,
            'commission_amount' => $this->commissionAmount($base, $rate),
            'status' => Commission::STATUS_PENDING,
        ];

        if ($existing) {
            if ($existing->status === Commission::STATUS_PENDING) {
                $existing->update($payload);
            }

            return $existing->fresh();
        }

        if ($base <= 0) {
            return null;
        }

        return Commission::create(array_merge($lookup, $payload));
    }

    private function createPendingIfMissing(
        Tenant $tenant,
        string $type,
        ?string $period,
        int $base,
        float $rate,
        ?int $marketerId = null,
        ?int $mrId = null,
    ): bool {
        if ($rate <= 0 || $base <= 0) {
            return false;
        }

        $lookup = $this->payeeLookup($tenant, $type, $period, $marketerId, $mrId);
        if (Commission::where($lookup)->exists()) {
            return false;
        }

        Commission::create(array_merge($lookup, [
            'marketer_id' => $marketerId,
            'medical_representative_id' => $mrId,
            'payee_key' => $lookup['payee_key'],
            'base_amount' => $base,
            'rate' => $rate,
            'commission_amount' => $this->commissionAmount($base, $rate),
            'status' => Commission::STATUS_PENDING,
        ]));

        return true;
    }

    /**
     * @return array{payee_key: string, tenant_id: string, type: string, period: ?string}
     */
    private function payeeLookup(
        Tenant $tenant,
        string $type,
        ?string $period,
        ?int $marketerId,
        ?int $mrId,
    ): array {
        $payeeKey = $mrId
            ? Commission::payeeKeyForMr($mrId)
            : Commission::payeeKeyForMarketer((int) $marketerId);

        return [
            'payee_key' => $payeeKey,
            'tenant_id' => $tenant->id,
            'type' => $type,
            'period' => $period,
        ];
    }

    private function rateForRow(Tenant $tenant, string $kind, Commission $commission): float
    {
        $rates = new DealCommissionRates($tenant);

        return $commission->medical_representative_id
            ? $rates->mrRate($kind)
            : $rates->marketerRate($kind);
    }

    private function commissionAmount(int $base, float $rate): int
    {
        return (int) round($base * $rate);
    }

    /**
     * @return array{collected: int, owed: int, paid: int, net: int}
     */
    public function platformFinanceSummary(?string $period = null): array
    {
        $collectedQuery = BillingPayment::query()->whereNotNull('confirmed_at');
        $owedQuery = Commission::query()->where('status', Commission::STATUS_OWED);
        $paidQuery = Commission::query()->where('status', Commission::STATUS_PAID);

        if ($period) {
            $collectedQuery->where(function ($q) use ($period) {
                $q->where('period', $period)
                    ->orWhere(function ($q2) use ($period) {
                        $q2->where('type', BillingPayment::TYPE_SETUP)
                            ->whereYear('confirmed_at', substr($period, 0, 4))
                            ->whereMonth('confirmed_at', substr($period, 5, 2));
                    });
            });
            $owedQuery->where(function ($q) use ($period) {
                $q->where('period', $period)
                    ->orWhere(function ($q2) use ($period) {
                        $q2->whereIn('type', [Commission::TYPE_SETUP, Commission::TYPE_YEAR_PREPAID])
                            ->whereHas('billingPayment', function ($q3) use ($period) {
                                $q3->whereYear('confirmed_at', substr($period, 0, 4))
                                    ->whereMonth('confirmed_at', substr($period, 5, 2));
                            });
                    });
            });
            $paidQuery->whereYear('paid_at', substr($period, 0, 4))
                ->whereMonth('paid_at', substr($period, 5, 2));
        }

        $collected = (int) $collectedQuery->sum('amount_paid');
        $owed = (int) $owedQuery->sum('commission_amount');
        $paid = (int) $paidQuery->sum('commission_amount');

        return [
            'collected' => $collected,
            'owed' => $owed,
            'paid' => $paid,
            'net' => $collected - $paid,
        ];
    }
}
