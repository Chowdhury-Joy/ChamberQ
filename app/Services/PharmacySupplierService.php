<?php

namespace App\Services;

use App\Models\ChamberCashEntry;
use App\Models\PharmacyDelivery;
use App\Models\PharmacyDoctorCommission;
use App\Models\PharmacySupplierSettlement;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PharmacySupplierService
{
    /**
     * @return array{owed: int, refund_due: int, doctor_pending: int}
     */
    public function shopBalance(): array
    {
        $owed = 0;
        $refundDue = 0;

        foreach (PharmacyDelivery::query()->get() as $delivery) {
            $owed += $delivery->owedTaka();
            $refundDue += $delivery->refundDueTaka();
        }

        return [
            'owed' => $owed,
            'refund_due' => $refundDue,
            'doctor_pending' => (int) PharmacyDoctorCommission::query()
                ->where('status', PharmacyDoctorCommission::STATUS_PENDING)
                ->sum('amount_taka'),
        ];
    }

    public function pay(
        User $user,
        int $amount,
        string $method,
        ?string $note = null,
        ?CarbonInterface $occurredOn = null,
        ?int $cashTaka = null,
        ?int $onlineTaka = null,
        ?string $onlineMethod = null,
    ): PharmacySupplierSettlement {
        $this->assertModule();

        if ($amount < 1) {
            throw new InvalidArgumentException(__('Pay at least ৳1.'));
        }

        $occurredOn ??= now(OperationalReportService::TIMEZONE);

        return DB::transaction(function () use ($user, $amount, $method, $note, $occurredOn, $cashTaka, $onlineTaka, $onlineMethod): PharmacySupplierSettlement {
            $remaining = $amount;
            $deliveries = PharmacyDelivery::query()->orderBy('id')->lockForUpdate()->get();

            foreach ($deliveries as $delivery) {
                if ($remaining < 1) {
                    break;
                }
                $owed = $delivery->owedTaka();
                if ($owed < 1) {
                    continue;
                }
                $take = min($remaining, $owed);
                $delivery->paid_taka += $take;
                $delivery->save();
                $remaining -= $take;
            }

            if ($remaining > 0) {
                throw new InvalidArgumentException(__('That is more than currently owed to suppliers.'));
            }

            $cash = app(ChamberCashService::class)->recordLockedExpense(
                $user,
                $amount,
                ChamberCashEntry::CATEGORY_PHARMACY_PURCHASE,
                $method,
                $occurredOn,
                $note ?? __('Pay pharmacy supplier'),
                $cashTaka,
                $onlineTaka,
                $onlineMethod,
            );

            return PharmacySupplierSettlement::create([
                'kind' => PharmacySupplierSettlement::KIND_PURCHASE,
                'amount' => $amount,
                'cash_entry_id' => $cash->id,
                'recorded_by' => $user->id,
                'occurred_on' => $occurredOn->toDateString(),
                'note' => $note,
            ]);
        });
    }

    public function recordRefund(
        User $user,
        int $amount,
        string $method,
        ?string $note = null,
        ?CarbonInterface $occurredOn = null,
        ?int $cashTaka = null,
        ?int $onlineTaka = null,
        ?string $onlineMethod = null,
    ): PharmacySupplierSettlement {
        $this->assertModule();

        if ($amount < 1) {
            throw new InvalidArgumentException(__('Refund must be at least ৳1.'));
        }

        $occurredOn ??= now(OperationalReportService::TIMEZONE);

        return DB::transaction(function () use ($user, $amount, $method, $note, $occurredOn, $cashTaka, $onlineTaka, $onlineMethod): PharmacySupplierSettlement {
            $remaining = $amount;
            $deliveries = PharmacyDelivery::query()->orderBy('id')->lockForUpdate()->get();

            foreach ($deliveries as $delivery) {
                if ($remaining < 1) {
                    break;
                }
                $due = $delivery->refundDueTaka();
                if ($due < 1) {
                    continue;
                }
                $take = min($remaining, $due);
                $delivery->paid_taka -= $take;
                $delivery->save();
                $remaining -= $take;
            }

            if ($remaining > 0) {
                throw new InvalidArgumentException(__('That is more than the supplier refund due.'));
            }

            $cash = app(ChamberCashService::class)->recordLockedIncome(
                $user,
                $amount,
                ChamberCashEntry::CATEGORY_PHARMACY_SUPPLIER_REFUND,
                $method,
                $occurredOn,
                $note ?? __('Pharmacy supplier refund'),
                $cashTaka,
                $onlineTaka,
                $onlineMethod,
            );

            return PharmacySupplierSettlement::create([
                'kind' => PharmacySupplierSettlement::KIND_REFUND,
                'amount' => $amount,
                'cash_entry_id' => $cash->id,
                'recorded_by' => $user->id,
                'occurred_on' => $occurredOn->toDateString(),
                'note' => $note,
            ]);
        });
    }

    private function assertModule(): void
    {
        if (! tenant()?->hasPharmacy()) {
            throw new InvalidArgumentException(__('Pharmacy is not enabled for this chamber.'));
        }
    }
}
