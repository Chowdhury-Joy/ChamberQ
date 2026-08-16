<?php

namespace App\Services;

use App\Models\Booking;
use Illuminate\Support\Facades\DB;

class VoucherService
{
    public function assignIfNeeded(Booking $booking): void
    {
        if (! tenant()?->hasStations()) {
            return;
        }

        if ($booking->voucher_number !== null) {
            return;
        }

        $booking->voucher_number = $this->nextNumberForDate($booking->booking_date->toDateString());
        $booking->save();
    }

    public function nextNumberForDate(string $bookingDate): int
    {
        return (int) DB::transaction(function () use ($bookingDate): int {
            $max = Booking::query()
                ->where('booking_date', $bookingDate)
                ->whereNotNull('voucher_number')
                ->lockForUpdate()
                ->max('voucher_number');

            return ((int) $max) + 1;
        });
    }
}
