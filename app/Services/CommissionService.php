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
     * (Solo only), then a percent discount code, then 50% prepaid-year setup.
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
        bool $prepaidYearSetup = false,
    ): array {
        return $this->planPricing->quote(
            $planTier,
            $modules,
            $code,
            $rxLifetimeFree,
            $prepaidYearSetup,
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
            (bool) $tenant->offer_prepaid_year_setup,
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
    }

    /**
     * @return array{setup_rate: float, monthly_rate: float, setup_commission: int, monthly_commission: int, display_name: string}|null
     */
    public function previewCommission(?int $marketerId, int $setupDue, int $monthlyDue): ?array
    {
        if (! $marketerId) {
            return null;
        }

        $marketer = Marketer::find($marketerId);
        if (! $marketer) {
            return null;
        }

        return [
            'display_name' => (string) $marketer->display_name,
            'setup_rate' => (float) $marketer->setup_commission_rate,
            'monthly_rate' => (float) $marketer->monthly_commission_rate,
            'setup_commission' => $this->commissionAmount($setupDue, (float) $marketer->setup_commission_rate),
            'monthly_commission' => $this->commissionAmount($monthlyDue, (float) $marketer->monthly_commission_rate),
        ];
    }

    /**
     * Create or refresh a pending setup commission when tenant has a marketer.
     * Owed/paid rows are left alone.
     */
    public function createPendingSetupCommission(Tenant $tenant): ?Commission
    {
        if (! $tenant->marketer_id) {
            return null;
        }

        $marketer = Marketer::find($tenant->marketer_id);
        if (! $marketer) {
            return null;
        }

        $lookup = [
            'marketer_id' => $marketer->id,
            'tenant_id' => $tenant->id,
            'type' => Commission::TYPE_SETUP,
            'period' => null,
        ];

        $payload = [
            'base_amount' => (int) $tenant->setup_amount_due,
            'rate' => $marketer->setup_commission_rate,
            'commission_amount' => $this->commissionAmount((int) $tenant->setup_amount_due, $marketer->setup_commission_rate),
            'status' => Commission::STATUS_PENDING,
        ];

        $existing = Commission::where($lookup)->first();
        if ($existing) {
            if ($existing->status === Commission::STATUS_PENDING) {
                $existing->update($payload);
            }

            return $existing->fresh();
        }

        if (! $tenant->setup_amount_due) {
            return null;
        }

        return Commission::create(array_merge($lookup, $payload));
    }

    private function refreshPendingMonthlyCommissions(Tenant $tenant): void
    {
        if (! $tenant->marketer_id) {
            return;
        }

        $marketer = Marketer::find($tenant->marketer_id);
        if (! $marketer) {
            return;
        }

        Commission::query()
            ->where('tenant_id', $tenant->id)
            ->where('type', Commission::TYPE_MONTHLY)
            ->where('status', Commission::STATUS_PENDING)
            ->get()
            ->each(function (Commission $commission) use ($tenant, $marketer): void {
                $commission->update([
                    'base_amount' => (int) $tenant->monthly_amount_due,
                    'rate' => $marketer->monthly_commission_rate,
                    'commission_amount' => $this->commissionAmount(
                        (int) $tenant->monthly_amount_due,
                        $marketer->monthly_commission_rate,
                    ),
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

            $this->markCommissionOwed($tenant, Commission::TYPE_SETUP, null, $billing, $paid);

            return $billing;
        });
    }

    public function confirmMonthlyPayment(Tenant $tenant, string $period, User $confirmedBy, ?string $notes = null, ?int $amountPaid = null): BillingPayment
    {
        return DB::transaction(function () use ($tenant, $period, $confirmedBy, $notes, $amountPaid) {
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

            $this->markCommissionOwed($tenant, Commission::TYPE_MONTHLY, $period, $billing, $paid);

            return $billing;
        });
    }

    public function generateMonthlyPendingCommissions(?string $period = null): int
    {
        $period = $period ?? now()->format('Y-m');
        $count = 0;

        Tenant::query()
            ->whereNotNull('marketer_id')
            ->whereNotNull('setup_paid_at')
            ->whereIn('billing_status', ['active', 'trial'])
            ->each(function (Tenant $tenant) use ($period, &$count) {
                $marketer = $tenant->marketer;
                if (! $marketer || ! $marketer->is_active) {
                    return;
                }

                $created = Commission::firstOrCreate(
                    [
                        'marketer_id' => $marketer->id,
                        'tenant_id' => $tenant->id,
                        'type' => Commission::TYPE_MONTHLY,
                        'period' => $period,
                    ],
                    [
                        'base_amount' => (int) $tenant->monthly_amount_due,
                        'rate' => $marketer->monthly_commission_rate,
                        'commission_amount' => $this->commissionAmount((int) $tenant->monthly_amount_due, $marketer->monthly_commission_rate),
                        'status' => Commission::STATUS_PENDING,
                    ]
                );

                if ($created->wasRecentlyCreated) {
                    $count++;
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

            foreach ($open as $i => $period) {
                $amount = $base + ($i === $last ? $remainder : 0);
                $this->confirmMonthlyPayment($tenant, $period, $confirmedBy, $notes, $amount);
                $created++;
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

    private function markCommissionOwed(Tenant $tenant, string $type, ?string $period, BillingPayment $billing, int $amountPaid): void
    {
        if (! $tenant->marketer_id) {
            return;
        }

        $marketer = Marketer::find($tenant->marketer_id);
        if (! $marketer) {
            return;
        }

        $rate = $type === Commission::TYPE_SETUP
            ? $marketer->setup_commission_rate
            : $marketer->monthly_commission_rate;

        $lookup = [
            'marketer_id' => $marketer->id,
            'tenant_id' => $tenant->id,
            'type' => $type,
            'period' => $period,
        ];

        $existing = Commission::where($lookup)->first();

        // Already paid: leave the ledger row alone (status AND amounts).
        // Re-confirming setup at a different figure must not rewrite a payout
        // that was already recorded (e.g. ৳1000 paid → still shows ৳1000).
        if ($existing && $existing->status === Commission::STATUS_PAID) {
            return;
        }

        Commission::updateOrCreate($lookup, [
            'billing_payment_id' => $billing->id,
            'base_amount' => $amountPaid,
            'rate' => $rate,
            'commission_amount' => $this->commissionAmount($amountPaid, $rate),
            'status' => Commission::STATUS_OWED,
        ]);
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
                        $q2->where('type', Commission::TYPE_SETUP)
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
