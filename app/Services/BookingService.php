<?php

namespace App\Services;

use App\Exceptions\BookingUnavailableException;
use App\Models\Booking;
use App\Models\ScheduleSession;
use App\Models\LabCollectionSlot;
use App\Models\LabTest;
use App\Models\SlotBlock;
use App\Support\BdPhone;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;

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
        // Legacy alias from early migration comment (`per_day`).
        if ($capMode === 'per_day') {
            $capMode = 'per_doctor_chamber';
        }

        return $this->availabilitySnapshot($bookable, $bookingDate, $capMode);
    }

    /**
     * @param  array<int, int|string>  $labTestIds  Line items for a lab booking.
     * @param  bool  $sendSms  False for sample/dev seed bookings that must not burn credits.
     */
    public function createBookingForBookable(
        Model $bookable,
        string $bookingDate,
        string $patientName,
        string $patientPhone,
        array $labTestIds = [],
        bool $sendSms = true,
        ?string $patientId = null,
    ): Booking {
        $patientPhone = $this->normalizeBdPhone($patientPhone);

        $booking = DB::transaction(function () use ($bookable, $bookingDate, $patientName, $patientPhone, $labTestIds, $patientId) {
            $tenant = tenant();
            $capMode = $tenant->slot_cap_mode ?? 'per_session';
            if ($capMode === 'per_day') {
                $capMode = 'per_doctor_chamber';
            }

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

            $patient = app(PatientService::class)->resolveForBooking(
                $patientPhone,
                $patientName,
                $patientId,
            );

            $duplicateQuery = Booking::where('bookable_type', get_class($lockedBookable))
                ->where('bookable_id', $lockedBookable->id)
                ->where('booking_date', $bookingDate)
                ->where('status', '!=', 'cancelled')
                ->where(function ($query) use ($patient, $patientPhone) {
                    $query->where('patient_id', $patient->id)
                        // Legacy fallback for pre-backfill rows with no
                        // patient_id. Matched against the resolved record's
                        // name, since the request's may be blank when an
                        // existing household member was picked.
                        ->orWhere(function ($legacy) use ($patientPhone, $patient) {
                            $legacy->whereNull('patient_id')
                                ->where('patient_phone', $patientPhone)
                                ->whereRaw('LOWER(TRIM(patient_name)) = ?', [
                                    strtolower(trim((string) $patient->name)),
                                ]);
                        });
                });

            if ($duplicateQuery->exists()) {
                throw BookingUnavailableException::duplicateBooking();
            }

            $maxSerial = Booking::where('bookable_type', get_class($lockedBookable))
                ->where('bookable_id', $lockedBookable->id)
                ->where('booking_date', $bookingDate)
                ->max('serial_number');

            $nextSerial = ($maxSerial ?? 0) + 1;

            $booking = Booking::create([
                'bookable_type' => get_class($lockedBookable),
                'bookable_id' => $lockedBookable->id,
                'booking_date' => $bookingDate,
                'patient_id' => $patient->id,
                // The resolved record's name, not the request's. When an
                // existing household member was picked, the public wizard only
                // ever saw masked initials — denormalising those onto the
                // booking would put "F. R." on the ticket and in the SMS.
                'patient_name' => $patient->name,
                'patient_phone' => $patientPhone,
                'serial_number' => $nextSerial,
                'status' => 'waiting',
            ]);

            if ($labTestIds !== []) {
                $this->attachLabTests($booking, $labTestIds);
            }

            return $booking;
        });

        if ($sendSms) {
            // After commit: never roll back a serial because SMS failed.
            app(SmsService::class)->sendBookingConfirmation($booking);
        }

        return $booking;
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
        if ($this->sessionAlreadyEndedToday($bookable, $bookingDate)) {
            return true;
        }

        if ($bookable instanceof ScheduleSession) {
            return SlotBlock::where('date', $bookingDate)
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
            return SlotBlock::where('date', $bookingDate)
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

    /**
     * A session or lab window that already ended earlier today must not still
     * be offered — nothing but day-of-week and capacity were ever checked, so
     * a patient could book (and get a confirmation SMS for) a slot that
     * finished hours ago.
     */
    private function sessionAlreadyEndedToday(Model $bookable, string $bookingDate): bool
    {
        if (blank($bookable->end_time)) {
            return false;
        }

        if (! Carbon::parse($bookingDate)->isToday()) {
            return false;
        }

        return Carbon::parse($bookingDate.' '.$bookable->end_time)->isPast();
    }

    private function bookedCount(Model $bookable, string $bookingDate, string $capMode): int
    {
        $query = Booking::where('booking_date', $bookingDate)
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

    /**
     * Store as 01XXXXXXXXX so portal lookup with/without +88 still matches.
     */
    public function normalizeBdPhone(string $phone): string
    {
        return BdPhone::normalize($phone);
    }
}
