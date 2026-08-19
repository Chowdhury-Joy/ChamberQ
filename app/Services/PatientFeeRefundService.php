<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\ChamberCashEntry;
use Illuminate\Support\Facades\DB;

class PatientFeeRefundService
{
    public function refundIfMissed(Booking $booking): ?ChamberCashEntry
    {
        if (! in_array($booking->status, ['no_show', 'cancelled'], true)) {
            return null;
        }

        return DB::transaction(function () use ($booking): ?ChamberCashEntry {
            $income = $this->incomeQuery($booking)->lockForUpdate()->first();

            if (! $income || $income->isWaived() || $income->amount < 1) {
                return null;
            }

            $existing = $this->refundQuery($booking)->lockForUpdate()->first();
            if ($existing) {
                return $existing;
            }

            $userId = auth()->id() ?: $income->recorded_by;

            $refund = ChamberCashEntry::create([
                'direction' => ChamberCashEntry::DIRECTION_EXPENSE,
                'amount' => $income->amount,
                'cash_taka' => $income->cash_taka,
                'mobile_taka' => $income->mobile_taka,
                'mobile_method' => $income->mobile_method,
                'category' => ChamberCashEntry::CATEGORY_PATIENT_REFUND,
                'method' => $income->method ?: ChamberCashEntry::METHOD_CASH,
                'booking_id' => $booking->id,
                'chamber_id' => $income->chamber_id,
                'doctor_id' => $income->doctor_id,
                'recorded_by' => $userId,
                'occurred_on' => now(OperationalReportService::TIMEZONE)->toDateString(),
                'note' => __('Refund: missed visit (:status)', [
                    'status' => $booking->status,
                ]),
            ]);

            app(ReferralCommissionService::class)->voidForBooking($booking);

            return $refund;
        });
    }

    public function discardRefundOnRecollect(Booking $booking): void
    {
        $this->refundQuery($booking)->delete();
    }

    public function hasOpenRefund(Booking $booking): bool
    {
        return $this->refundQuery($booking)->exists();
    }

    private function incomeQuery(Booking $booking)
    {
        return ChamberCashEntry::query()
            ->where('booking_id', $booking->id)
            ->where('direction', ChamberCashEntry::DIRECTION_INCOME);
    }

    private function refundQuery(Booking $booking)
    {
        return ChamberCashEntry::query()
            ->where('booking_id', $booking->id)
            ->where('direction', ChamberCashEntry::DIRECTION_EXPENSE)
            ->where('category', ChamberCashEntry::CATEGORY_PATIENT_REFUND);
    }
}
