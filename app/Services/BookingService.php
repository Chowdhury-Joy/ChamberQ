<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\ScheduleSession;
use App\Models\LabCollectionSlot;
use App\Models\SlotBlock;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Exception;

class BookingService
{
    public function createBookingForBookable(
        Model $bookable, 
        string $bookingDate, 
        string $patientName, 
        string $patientPhone
    ): Booking {
        return DB::transaction(function () use ($bookable, $bookingDate, $patientName, $patientPhone) {
            // Pessimistic lock
            $lockedBookable = get_class($bookable)::where('id', $bookable->id)->lockForUpdate()->first();

            $date = Carbon::parse($bookingDate);
            $dayOfWeek = strtolower($date->format('l'));

            // 1. Validate day of week
            if ($bookable instanceof ScheduleSession) {
                 if ($date->dayOfWeek !== $lockedBookable->day_of_week) {
                     throw new Exception("The requested date does not match the session's configured day of week.");
                 }
            } else if ($bookable instanceof LabCollectionSlot) {
                 if ($dayOfWeek !== strtolower($lockedBookable->day_of_week)) {
                     throw new Exception("The requested date does not match the slot's configured day of week.");
                 }
            }

            // 2. Validate against SlotBlocks
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

                if ($isBlocked) {
                    throw new Exception("The requested date is blocked for this session.");
                }
            }

            // 3. Enforce capacity
            $tenant = tenant();
            $capMode = $tenant->slot_cap_mode ?? 'per_session';

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
                throw new Exception("Capacity exceeded for the requested date.");
            }

            // 4. Allocate MAX(serial_number) + 1
            $maxSerial = Booking::where('bookable_type', get_class($bookable))
                ->where('bookable_id', $lockedBookable->id)
                ->where('booking_date', $bookingDate)
                ->max('serial_number');

            $nextSerial = $maxSerial ? $maxSerial + 1 : 1;

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
