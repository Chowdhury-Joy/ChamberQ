<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\ChamberCashEntry;
use App\Models\FeeCatalogItem;
use App\Models\ScheduleSession;
use App\Models\User;
use Carbon\CarbonInterface;
use InvalidArgumentException;

class StationsTillService
{
    /**
     * @return array{
     *     list_price: int,
     *     cash: int,
     *     mobile: int,
     *     collected: int,
     *     discount: int,
     *     clinic_share: int,
     *     doctor_share: int
     * }
     */
    public function computeSplit(int $listPrice, int $cash, int $mobile, int $houseShare): array
    {
        if ($listPrice < 0 || $cash < 0 || $mobile < 0) {
            throw new InvalidArgumentException(__('Amounts cannot be negative.'));
        }

        $collected = $cash + $mobile;

        if ($collected > $listPrice) {
            throw new InvalidArgumentException(__('Cash plus mobile cannot exceed the full price.'));
        }

        $discount = $listPrice - $collected;
        $clinicShare = $collected > 0 ? min($houseShare, $collected) : 0;
        $doctorShare = max(0, $collected - $clinicShare);

        return [
            'list_price' => $listPrice,
            'cash' => $cash,
            'mobile' => $mobile,
            'collected' => $collected,
            'discount' => $discount,
            'clinic_share' => $clinicShare,
            'doctor_share' => $doctorShare,
        ];
    }

    public function recordPatientIncome(
        Booking $booking,
        User $user,
        FeeCatalogItem $catalogItem,
        int $cashTaka,
        int $mobileTaka,
        ?string $mobileMethod = null,
        ?string $note = null,
        ?CarbonInterface $occurredOn = null,
        bool $waived = false,
    ): ChamberCashEntry {
        if ($waived) {
            $cashTaka = 0;
            $mobileTaka = 0;
            $mobileMethod = null;
        }

        $split = $this->computeSplit(
            $catalogItem->list_price_taka,
            $cashTaka,
            $mobileTaka,
            $catalogItem->house_share_taka,
        );

        if (! $waived && $split['collected'] < 1 && $catalogItem->list_price_taka > 0) {
            throw new InvalidArgumentException(__('Collect at least ৳1, or waive the fee.'));
        }

        $method = $this->resolveMethod($cashTaka, $mobileTaka, $mobileMethod);

        $booking->loadMissing('bookable');
        $session = $booking->bookable_type === ScheduleSession::class
            ? $booking->bookable
            : null;

        $category = $waived || $split['collected'] === 0
            ? ChamberCashEntry::CATEGORY_WAIVED
            : ChamberCashEntry::CATEGORY_PATIENT;

        $values = [
            'direction' => ChamberCashEntry::DIRECTION_INCOME,
            'amount' => $split['collected'],
            'list_price_taka' => $catalogItem->list_price_taka,
            'cash_taka' => $cashTaka,
            'mobile_taka' => $mobileTaka,
            'mobile_method' => $mobileMethod,
            'discount_taka' => $split['discount'],
            'clinic_share_taka' => $split['clinic_share'],
            'doctor_share_taka' => $split['doctor_share'],
            'fee_catalog_item_id' => $catalogItem->id,
            'fee_type' => null,
            'category' => $category,
            'method' => $method,
            'chamber_id' => $session?->chamber_id,
            'doctor_id' => $session?->doctor_id,
            'recorded_by' => $user->id,
            'occurred_on' => ($occurredOn ?? now(OperationalReportService::TIMEZONE))->toDateString(),
            'note' => $note,
        ];

        $existing = ChamberCashEntry::query()->where('booking_id', $booking->id)->first();

        if ($existing) {
            $existing->fill($values)->save();

            return $existing;
        }

        return ChamberCashEntry::create([
            ...$values,
            'booking_id' => $booking->id,
        ]);
    }

    private function resolveMethod(int $cash, int $mobile, ?string $mobileMethod): string
    {
        if ($cash > 0 && $mobile > 0) {
            return ChamberCashEntry::METHOD_MIXED;
        }

        if ($mobile > 0) {
            return $mobileMethod ?: ChamberCashEntry::METHOD_BKASH;
        }

        return ChamberCashEntry::METHOD_CASH;
    }
}
