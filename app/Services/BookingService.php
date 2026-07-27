<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\ScheduleSession;
use App\Models\LabCollectionSlot;
use App\Models\LabTest;
use App\Models\SlotBlock;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use App\Exceptions\BookingUnavailableException;

class BookingService
{
    /**
     * Preview seats / blocks for a bookable on a date (no lock — UI only).
     *
     * @return array{available: bool, blocked: bool, day_mismatch: bool, cap: int, booked: int, remaining: int}
     */
    public function availabilityFor(Model $bookable, string $bookingDate): array
    {
        $capMode = tenant()->slot_cap_mode ?? 'per_session';

        return $this->availabilitySnapshot($bookable, $bookingDate, $capMode);
    }

    /**
     * @param  array<int, int|string>  $labTestIds  Line items for a lab booking.
     */
    public function createBookingForBookable(
        Model $bookable,
        string $bookingDate,
        string $patientName,
        string $patientPhone,
        array $labTestIds = []
    ): Booking {
        return DB::transaction(function () use ($bookable, $bookingDate, $patientName, $patientPhone, $labTestIds) {
            $tenant = tenant();
            $capMode = $tenant->slot_cap_mode ?? 'per_session';

            // Pessimistic lock — Fatima and Rahim cannot both take the last seat.
            if ($bookable instanceof ScheduleSession && $capMode === 'per_doctor_chamber') {
                \App\Models\Doctor::where('id', $bookable->doctor_id)->lockForUpdate()->first();
                $lockedBookable = get_class($bookable)::where('id', $bookable->id)->lockForUpdate()->first();
            } else {
                $lockedBookable = get_class($bookable)::where('id', $bookable->id)->lockForUpdate()->first();
            }

            if (! $lockedBookable) {
                throw BookingUnavailableException::bookableUnavailable();
            }

            $date = Carbon::parse($bookingDate);

            // Both bookable types store Carbon's dayOfWeek (0 = Sunday .. 6 = Saturday).
            if ($date->dayOfWeek !== (int) $lockedBookable->day_of_week) {
                throw BookingUnavailableException::dayOfWeekMismatch(
                    $lockedBookable instanceof LabCollectionSlot
                );
            }

            $availability = $this->availabilitySnapshot($lockedBookable, $bookingDate, $capMode);

            if ($availability['blocked']) {
                throw BookingUnavailableException::dateBlocked();
            }

            if ($availability['remaining'] <= 0) {
                throw BookingUnavailableException::capacityExceeded();
            }

            $maxSerial = Booking::where('bookable_type', get_class($lockedBookable))
                ->where('bookable_id', $lockedBookable->id)
                ->whereDate('booking_date', $bookingDate)
                ->max('serial_number');

            $nextSerial = ($maxSerial ?? 0) + 1;

            $booking = Booking::create([
                'bookable_type' => get_class($lockedBookable),
                'bookable_id' => $lockedBookable->id,
                'booking_date' => $bookingDate,
                'patient_name' => $patientName,
                'patient_phone' => $patientPhone,
                'serial_number' => $nextSerial,
                'status' => 'waiting',
            ]);

            if ($labTestIds !== []) {
                $this->attachLabTests($booking, $labTestIds);
            }

            return $booking;
        });
    }

    /**
     * @return array{available: bool, blocked: bool, day_mismatch: bool, cap: int, booked: int, remaining: int}
     */
    private function availabilitySnapshot(Model $bookable, string $bookingDate, string $capMode): array
    {
        $date = Carbon::parse($bookingDate);
        $dayMismatch = $date->dayOfWeek !== (int) $bookable->day_of_week;
        $blocked = $this->isDateBlocked($bookable, $bookingDate);
        $cap = max(0, (int) $bookable->slot_cap);
        $booked = $this->bookedCount($bookable, $bookingDate, $capMode);
        $remaining = max(0, $cap - $booked);

        return [
            'available' => ! $dayMismatch && ! $blocked && $remaining > 0,
            'blocked' => $blocked,
            'day_mismatch' => $dayMismatch,
            'cap' => $cap,
            'booked' => $booked,
            'remaining' => $remaining,
        ];
    }

    private function isDateBlocked(Model $bookable, string $bookingDate): bool
    {
        if ($bookable instanceof ScheduleSession) {
            return SlotBlock::whereDate('date', $bookingDate)
                ->where(function ($query) use ($bookable) {
                    $query->whereNull('chamber_id')->whereNull('doctor_id')
                        ->orWhere(function ($q) use ($bookable) {
                            $q->where('chamber_id', $bookable->chamber_id)->whereNull('doctor_id');
                        })
                        ->orWhere('doctor_id', $bookable->doctor_id);
                })
                ->exists();
        }

        if ($bookable instanceof LabCollectionSlot) {
            return SlotBlock::whereDate('date', $bookingDate)
                ->where(function ($query) use ($bookable) {
                    $query->whereNull('chamber_id')->whereNull('doctor_id')
                        ->orWhere(function ($q) use ($bookable) {
                            $q->where('chamber_id', $bookable->chamber_id)->whereNull('doctor_id');
                        });
                })
                ->exists();
        }

        return false;
    }

    private function bookedCount(Model $bookable, string $bookingDate, string $capMode): int
    {
        $query = Booking::whereDate('booking_date', $bookingDate)
            ->where('status', '!=', 'cancelled');

        if ($bookable instanceof ScheduleSession && $capMode === 'per_doctor_chamber') {
            $sessionIds = ScheduleSession::where('chamber_id', $bookable->chamber_id)
                ->where('doctor_id', $bookable->doctor_id)
                ->pluck('id');

            return $query->where('bookable_type', ScheduleSession::class)
                ->whereIn('bookable_id', $sessionIds)
                ->count();
        }

        return $query->where('bookable_type', get_class($bookable))
            ->where('bookable_id', $bookable->id)
            ->count();
    }

    /**
     * Attach lab tests as line items, snapshotting today's price on each.
     *
     * Runs inside the booking transaction so a booking can never be persisted
     * without the tests it was made for.
     *
     * @param  array<int, int|string>  $labTestIds
     */
    private function attachLabTests(Booking $booking, array $labTestIds): void
    {
        if (! $booking->bookable instanceof LabCollectionSlot) {
            throw BookingUnavailableException::labTestsNotBookable();
        }

        if (! tenant()->hasFeature('lab_tests')) {
            throw BookingUnavailableException::labTestsNotBookable();
        }

        $labTestIds = array_values(array_unique($labTestIds));

        // Resolved through the tenant global scope: an id belonging to another
        // tenant simply will not come back, and the count check below rejects
        // the whole booking rather than silently dropping the line item.
        $tests = LabTest::active()->whereIn('id', $labTestIds)->get();

        if ($tests->count() !== count($labTestIds)) {
            throw BookingUnavailableException::labTestUnavailable();
        }

        $booking->labTests()->attach(
            $tests->mapWithKeys(fn (LabTest $test) => [
                $test->id => [
                    'tenant_id' => $booking->tenant_id,
                    'price_at_booking' => $test->price,
                ],
            ])->all()
        );
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
