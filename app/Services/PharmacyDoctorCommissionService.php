<?php

namespace App\Services;

use App\Models\ChamberCashEntry;
use App\Models\Doctor;
use App\Models\PharmacyDoctorCommission;
use App\Models\PharmacySale;
use App\Models\PharmacySaleItem;
use App\Models\Prescription;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PharmacyDoctorCommissionService
{
    public function accrueForLine(PharmacySale $sale, PharmacySaleItem $saleItem, ?Prescription $prescription): ?PharmacyDoctorCommission
    {
        if ($saleItem->shop_cut_taka < 1 || $prescription === null) {
            return null;
        }

        $doctor = $this->prescribingDoctor($prescription);

        if (! $doctor) {
            return null;
        }

        $percent = $doctor->pharmacyCutPercent();

        if ($percent < 1) {
            return null;
        }

        $amount = intdiv($saleItem->shop_cut_taka * $percent, 100);

        if ($amount < 1) {
            return null;
        }

        return PharmacyDoctorCommission::create([
            'doctor_id' => $doctor->id,
            'pharmacy_sale_id' => $sale->id,
            'pharmacy_sale_item_id' => $saleItem->id,
            'shop_cut_taka' => $saleItem->shop_cut_taka,
            'percent' => $percent,
            'amount_taka' => $amount,
            'status' => PharmacyDoctorCommission::STATUS_PENDING,
            'occurred_on' => $sale->occurred_on?->toDateString() ?? now(OperationalReportService::TIMEZONE)->toDateString(),
        ]);
    }

    public function voidForSale(PharmacySale $sale): void
    {
        PharmacyDoctorCommission::query()
            ->where('pharmacy_sale_id', $sale->id)
            ->where('status', PharmacyDoctorCommission::STATUS_PENDING)
            ->update(['status' => PharmacyDoctorCommission::STATUS_VOID]);
    }

    /**
     * @param  Collection<int, PharmacyDoctorCommission>  $commissions
     */
    public function markPaid(Collection $commissions, User $user, string $method, ?string $note = null): ChamberCashEntry
    {
        $ids = $commissions->pluck('id')->filter()->unique()->values()->all();

        if ($ids === []) {
            throw new InvalidArgumentException(__('Pick at least one pending cut.'));
        }

        return DB::transaction(function () use ($ids, $user, $method, $note): ChamberCashEntry {
            $rows = PharmacyDoctorCommission::query()
                ->whereIn('id', $ids)
                ->where('status', PharmacyDoctorCommission::STATUS_PENDING)
                ->lockForUpdate()
                ->get();

            if ($rows->count() !== count($ids)) {
                throw new InvalidArgumentException(__('Some of those cuts are no longer pending.'));
            }

            $total = (int) $rows->sum('amount_taka');

            if ($total < 1) {
                throw new InvalidArgumentException(__('Nothing to pay.'));
            }

            $cash = app(ChamberCashService::class)->recordLockedExpense(
                $user,
                $total,
                ChamberCashEntry::CATEGORY_PHARMACY_DOCTOR_PAYOUT,
                $method,
                now(OperationalReportService::TIMEZONE),
                $note ?? __('Doctor pharmacy cut payout'),
            );

            foreach ($rows as $row) {
                $row->status = PharmacyDoctorCommission::STATUS_PAID;
                $row->paid_at = now();
                $row->paid_by = $user->id;
                $row->payout_cash_entry_id = $cash->id;
                $row->save();
            }

            return $cash;
        });
    }

    private function prescribingDoctor(Prescription $prescription): ?Doctor
    {
        $prescription->loadMissing('prescribedBy.doctorProfile', 'visitRecord.booking.bookable');

        $fromUser = $prescription->prescribedBy?->doctorProfile;
        if ($fromUser instanceof Doctor) {
            return $fromUser;
        }

        $bookable = $prescription->visitRecord?->booking?->bookable;

        return $bookable?->doctor ?? null;
    }
}
