<?php

namespace App\Services;

use App\Models\ChamberCashEntry;
use App\Models\Doctor;
use App\Support\TakaWords;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class FeeReceiptService
{
    public function assignIfNeeded(ChamberCashEntry $entry): void
    {
        if (! $entry->isPatientFeeReceipt()) {
            return;
        }

        if ($entry->receipt_number !== null) {
            return;
        }

        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                DB::transaction(function () use ($entry): void {
                    $locked = ChamberCashEntry::query()->whereKey($entry->id)->lockForUpdate()->first();
                    if ($locked === null) {
                        return;
                    }

                    if ($locked->receipt_number !== null) {
                        $entry->receipt_number = $locked->receipt_number;

                        return;
                    }

                    $date = $locked->occurred_on->toDateString();
                    ChamberCashEntry::query()
                        ->where('occurred_on', $date)
                        ->whereNotNull('receipt_number')
                        ->lockForUpdate()
                        ->get(['id']);

                    $max = ChamberCashEntry::query()
                        ->where('occurred_on', $date)
                        ->whereNotNull('receipt_number')
                        ->max('receipt_number');

                    $locked->receipt_number = ((int) $max) + 1;
                    $locked->save();
                    $entry->receipt_number = $locked->receipt_number;
                });

                return;
            } catch (UniqueConstraintViolationException) {
                $entry->refresh();
                if ($entry->receipt_number !== null) {
                    return;
                }
            }
        }

        throw new \RuntimeException('Could not assign a receipt number. Try again.');
    }

    /** @return array<string, mixed> */
    public function viewData(ChamberCashEntry $entry): array
    {
        $this->assignIfNeeded($entry);
        $entry->refresh();
        $entry->load([
            'booking.bookable',
            'booking.patient',
            'feeCatalogItem',
            'chamber',
            'doctor',
            'recorder',
        ]);

        $booking = $entry->booking;
        $doctor = $entry->doctor ?? ($booking ? Doctor::resolveForBooking($booking) : null);
        $chamber = $entry->chamber;
        $patient = $booking?->patient;
        $gross = (int) ($entry->list_price_taka ?? 0);
        if ($gross < 1) {
            $gross = (int) $entry->amount + (int) ($entry->discount_taka ?? 0);
        }

        return [
            'entry' => $entry,
            'booking' => $booking,
            'tenant' => tenant(),
            'doctor' => $doctor,
            'chamber' => $chamber,
            'patient' => $patient,
            'gross' => $gross,
            'discount' => (int) ($entry->discount_taka ?? 0),
            'amountInWords' => TakaWords::english((int) $entry->amount),
        ];
    }
}
