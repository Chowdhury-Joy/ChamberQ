<?php

namespace App\Services;

use App\Models\BillingPayment;
use App\Models\Commission;
use App\Models\DiscountCode;
use App\Models\Marketer;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CommissionService
{
    public function __construct(
        private readonly PlanPricingService $planPricing,
        private readonly DiscountCalculator $discountCalculator,
    ) {}

    /**
     * Snapshot plan prices and apply discount when a tenant is onboarded.
     */
    public function applyPricingToTenant(Tenant $tenant, ?DiscountCode $code = null, bool $countRedemption = false): Tenant
    {
        $list = $this->planPricing->listPricesForTier((string) $tenant->plan_tier);
        $amounts = $this->discountCalculator->calculate($list['setup'], $list['monthly'], $code);

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

        return $tenant;
    }

    /**
     * Create pending setup commission when tenant gets a marketer.
     */
    public function createPendingSetupCommission(Tenant $tenant): ?Commission
    {
        if (! $tenant->marketer_id || ! $tenant->setup_amount_due) {
            return null;
        }

        $marketer = Marketer::find($tenant->marketer_id);
        if (! $marketer) {
            return null;
        }

        return Commission::firstOrCreate(
            [
                'marketer_id' => $marketer->id,
                'tenant_id' => $tenant->id,
                'type' => Commission::TYPE_SETUP,
                'period' => null,
            ],
            [
                'base_amount' => (int) $tenant->setup_amount_due,
                'rate' => $marketer->setup_commission_rate,
                'commission_amount' => $this->commissionAmount((int) $tenant->setup_amount_due, $marketer->setup_commission_rate),
                'status' => Commission::STATUS_PENDING,
            ]
        );
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

        $attributes = [
            'billing_payment_id' => $billing->id,
            'base_amount' => $amountPaid,
            'rate' => $rate,
            'commission_amount' => $this->commissionAmount($amountPaid, $rate),
        ];

        // Re-confirming a payment must not downgrade an already-paid commission.
        if (! $existing || $existing->status !== Commission::STATUS_PAID) {
            $attributes['status'] = Commission::STATUS_OWED;
        }

        Commission::updateOrCreate($lookup, $attributes);
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
