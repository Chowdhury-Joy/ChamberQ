<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\ScheduleSession;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class VoucherService
{
    public function assignIfNeeded(Booking $booking): void
    {
        if (! tenant()?->hasStations()) {
            return;
        }

        $booking->loadMissing('bookable');
        $session = $booking->bookable;
        if ($session instanceof ScheduleSession && $session->isFreeKind()) {
            return;
        }

        if ($booking->voucher_number !== null) {
            return;
        }

        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                DB::transaction(function () use ($booking): void {
                    $locked = Booking::query()->whereKey($booking->id)->lockForUpdate()->first();
                    if ($locked === null) {
                        return;
                    }

                    if ($locked->voucher_number !== null) {
                        $booking->voucher_number = $locked->voucher_number;

                        return;
                    }

                    $date = $locked->booking_date->toDateString();
                    $max = Booking::query()
                        ->where('booking_date', $date)
                        ->whereNotNull('voucher_number')
                        ->lockForUpdate()
                        ->max('voucher_number');

                    $locked->voucher_number = ((int) $max) + 1;
                    $locked->save();
                    $booking->voucher_number = $locked->voucher_number;
                });

                return;
            } catch (UniqueConstraintViolationException) {
                $booking->refresh();
                if ($booking->voucher_number !== null) {
                    return;
                }
            }
        }
    }
}
