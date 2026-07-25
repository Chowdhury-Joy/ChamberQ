<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\ScheduleSession;
use App\Models\SlotBlock;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Exception;

class BookingService
{
    public function createBookingForSession(
        ScheduleSession $session, 
        string $bookingDate, 
        string $patientName, 
        string $patientPhone
    ): Booking {
        return DB::transaction(function () use ($session, $bookingDate, $patientName, $patientPhone) {
            // Pessimistic lock on the session
            $lockedSession = ScheduleSession::where('id', $session->id)->lockForUpdate()->first();

            $date = Carbon::parse($bookingDate);

            // 1. Validate day of week
            if ($date->dayOfWeek !== $lockedSession->day_of_week) {
                throw new Exception("The requested date does not match the session's configured day of week.");
            }

            // 2. Validate against SlotBlocks
            $isBlocked = SlotBlock::where('date', $bookingDate)
                ->where(function ($query) use ($lockedSession) {
                    $query->whereNull('chamber_id')->whereNull('doctor_id') // Global block
                        ->orWhere(function ($q) use ($lockedSession) {
                            $q->where('chamber_id', $lockedSession->chamber_id)->whereNull('doctor_id');
                        })
                        ->orWhere('doctor_id', $lockedSession->doctor_id);
                })
                ->exists();

            if ($isBlocked) {
                throw new Exception("The requested date is blocked for this session.");
            }

            // 3. Enforce capacity
            $tenant = tenant();
            $capMode = $tenant->slot_cap_mode ?? 'per_session';

            $currentBookingsCountQuery = Booking::where('booking_date', $bookingDate)
                ->where('status', '!=', 'cancelled');

            if ($capMode === 'per_session') {
                $currentBookingsCountQuery->where('bookable_type', ScheduleSession::class)
                                          ->where('bookable_id', $lockedSession->id);
                $capLimit = $lockedSession->slot_cap;
            } else {
                $sessionIds = ScheduleSession::where('chamber_id', $lockedSession->chamber_id)
                                             ->where('doctor_id', $lockedSession->doctor_id)
                                             ->pluck('id');
                $currentBookingsCountQuery->where('bookable_type', ScheduleSession::class)
                                          ->whereIn('bookable_id', $sessionIds);
                $capLimit = $lockedSession->slot_cap; 
            }

            $currentBookingsCount = $currentBookingsCountQuery->count();

            if ($currentBookingsCount >= $capLimit) {
                throw new Exception("Capacity exceeded for the requested date.");
            }

            // 4. Allocate MAX(serial_number) + 1 (including cancelled bookings)
            $maxSerial = Booking::where('bookable_type', ScheduleSession::class)
                ->where('bookable_id', $lockedSession->id)
                ->where('booking_date', $bookingDate)
                ->max('serial_number');

            $nextSerial = $maxSerial ? $maxSerial + 1 : 1;

            return Booking::create([
                'bookable_type' => ScheduleSession::class,
                'bookable_id' => $lockedSession->id,
                'booking_date' => $bookingDate,
                'patient_name' => $patientName,
                'patient_phone' => $patientPhone,
                'serial_number' => $nextSerial,
                'status' => 'waiting',
                'payment_status' => 'unpaid',
            ]);
        });
    }
}
