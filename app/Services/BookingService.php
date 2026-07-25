<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\ScheduleSession;
use App\Models\LabCollectionSlot;
use App\Models\SlotBlock;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use App\Exceptions\BookingUnavailableException;

class BookingService
{
    public function createBookingForBookable(
        Model $bookable, 
        string $bookingDate, 
        string $patientName, 
        string $patientPhone
    ): Booking {
        return DB::transaction(function () use ($bookable, $bookingDate, $patientName, $patientPhone) {
            $tenant = tenant();
            $capMode = $tenant->slot_cap_mode ?? 'per_session';

            // Pessimistic lock
            if ($bookable instanceof ScheduleSession && $capMode === 'per_doctor_chamber') {
                \App\Models\Doctor::where('id', $bookable->doctor_id)->lockForUpdate()->first();
                $lockedBookable = get_class($bookable)::find($bookable->id);
            } else {
                $lockedBookable = get_class($bookable)::where('id', $bookable->id)->lockForUpdate()->first();
            }

            $date = Carbon::parse($bookingDate);

            // 1. Validate day of week. Both bookable types store Carbon's
            // dayOfWeek (0 = Sunday .. 6 = Saturday), so one comparison covers both.
            if ($date->dayOfWeek !== $lockedBookable->day_of_week) {
                throw BookingUnavailableException::dayOfWeekMismatch(
                    $bookable instanceof LabCollectionSlot
                );
            }

            // 2. Validate against SlotBlocks
            $isBlocked = false;
            if ($bookable instanceof ScheduleSession) {
                $isBlocked = SlotBlock::where('date', $bookingDate)
                    ->where(function ($query) use ($lockedBookable) {
                        $query->whereNull('chamber_id')->whereNull('doctor_id')
                            ->orWhere(function ($q) use ($lockedBookable) {
                                $q->where('chamber_id', $lockedBookable->chamber_id)->whereNull('doctor_id');
                            })
                            ->orWhere('doctor_id', $lockedBookable->doctor_id);
                    })
                    ->exists();
            } elseif ($bookable instanceof LabCollectionSlot) {
                $isBlocked = SlotBlock::where('date', $bookingDate)
                    ->where(function ($query) use ($lockedBookable) {
                        $query->whereNull('chamber_id')->whereNull('doctor_id')
                            ->orWhere(function ($q) use ($lockedBookable) {
                                $q->where('chamber_id', $lockedBookable->chamber_id)->whereNull('doctor_id');
                            });
                    })
                    ->exists();
            }

            if ($isBlocked) {
                throw BookingUnavailableException::dateBlocked();
            }

            // 3. Enforce capacity
            $currentBookingsCountQuery = Booking::where('booking_date', $bookingDate)
                ->where('status', '!=', 'cancelled');

            if ($bookable instanceof ScheduleSession && $capMode === 'per_doctor_chamber') {
                $sessionIds = ScheduleSession::where('chamber_id', $lockedBookable->chamber_id)
                                             ->where('doctor_id', $lockedBookable->doctor_id)
                                             ->pluck('id');
                $currentBookingsCountQuery->where('bookable_type', ScheduleSession::class)
                                          ->whereIn('bookable_id', $sessionIds);
            } else {
                $currentBookingsCountQuery->where('bookable_type', get_class($bookable))
                                          ->where('bookable_id', $lockedBookable->id);
            }

            $capLimit = $lockedBookable->slot_cap; 
            $currentBookingsCount = $currentBookingsCountQuery->count();

            if ($currentBookingsCount >= $capLimit) {
                throw BookingUnavailableException::capacityExceeded();
            }

            // 4. Allocate MAX(serial_number) + 1
            $maxSerial = Booking::where('bookable_type', get_class($bookable))
                ->where('bookable_id', $lockedBookable->id)
                ->where('booking_date', $bookingDate)
                ->max('serial_number');

            $nextSerial = ($maxSerial ?? 0) + 1;

            return Booking::create([
                'bookable_type' => get_class($bookable),
                'bookable_id' => $lockedBookable->id,
                'booking_date' => $bookingDate,
                'patient_name' => $patientName,
                'patient_phone' => $patientPhone,
                'serial_number' => $nextSerial,
                'status' => 'waiting',
                'payment_status' => 'unpaid',
            ]);
        });
    }

    // Keep the old method for backwards compatibility
    public function createBookingForSession(
        ScheduleSession $session, 
        string $bookingDate, 
        string $patientName, 
        string $patientPhone
    ): Booking {
        return $this->createBookingForBookable($session, $bookingDate, $patientName, $patientPhone);
    }
}
