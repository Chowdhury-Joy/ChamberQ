<?php

namespace App\Services;

use App\Exceptions\BookingUnavailableException;
use App\Jobs\SendBookingConfirmation;
use App\Models\Booking;
use App\Models\Doctor;
use App\Models\LabCollectionSlot;
use App\Models\LabTest;
use App\Models\PlatformSetting;
use App\Models\ScheduleSession;
use App\Models\SlotBlock;
use App\Support\BdPhone;
use App\Support\ScheduleSessionPace;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BookingService
{
    /**
     * Preview seats / blocks for a bookable on a date (no lock — UI only).
     *
     * @return array{available: bool, blocked: bool, day_mismatch: bool, cap: int, booked: int, remaining: int}
     */
    public function availabilityFor(Model $bookable, string $bookingDate, bool $allowOverflow = false): array
    {
        $capMode = tenant()->slot_cap_mode ?? 'per_session';
        // Legacy alias from early migration comment (`per_day`).
        if ($capMode === 'per_day') {
            $capMode = 'per_doctor_chamber';
        }

        return $this->availabilitySnapshot($bookable, $bookingDate, $capMode, $allowOverflow);
    }

    /**
     * Open bookable+date pairs within the platform booking window, soonest first.
     *
     * Uses three queries (bookings grouped, slot blocks, bookables already loaded)
     * rather than one availability check per candidate date.
     *
     * @param  iterable<ScheduleSession|LabCollectionSlot>  $bookables
     * @return list<array{bookable_id: int|string, date: string, remaining: int, cap: int, booked: int}>
     */
    public function openDatesFor(iterable $bookables, ?int $withinDays = null, int $limit = 20): array
    {
        $bookables = collect($bookables)->filter();
        if ($bookables->isEmpty()) {
            return [];
        }

        $withinDays ??= PlatformSetting::patientBookingHorizonDays();
        $withinDays = max(PlatformSetting::MIN_HORIZON_DAYS, min(PlatformSetting::MAX_HORIZON_DAYS, $withinDays));

        $capMode = tenant()->slot_cap_mode ?? 'per_session';
        if ($capMode === 'per_day') {
            $capMode = 'per_doctor_chamber';
        }

        $startDate = Carbon::today();
        $endDate = $startDate->copy()->addDays($withinDays);
        $bookableClass = $bookables->first()::class;

        $slotBlocks = SlotBlock::query()
            ->where('date', '>=', $startDate->toDateString())
            ->where('date', '<=', $endDate->toDateString())
            ->get();

        $bookingCounts = $this->bulkBookingCounts($bookables, $bookableClass, $capMode, $startDate, $endDate);

        $open = [];

        foreach ($bookables as $bookable) {
            $cursor = $this->firstCandidateDate($bookable, $startDate);

            while ($cursor->lte($endDate)) {
                $ymd = $cursor->toDateString();

                if (! $this->isDateBlockedWithBlocks($bookable, $ymd, $slotBlocks)) {
                    $booked = $this->bookedCountFromIndex($bookable, $ymd, $capMode, $bookingCounts);
                    $cap = max(0, (int) $bookable->slot_cap);
                    $remaining = max(0, $cap - $booked);

                    if ($remaining > 0) {
                        $open[] = [
                            'bookable_id' => $bookable->id,
                            'date' => $ymd,
                            'remaining' => $remaining,
                            'cap' => $cap,
                            'booked' => $booked,
                        ];
                    }
                }

                $cursor->addWeek();
            }
        }

        usort($open, function (array $a, array $b) use ($bookables): int {
            $dateCmp = strcmp($a['date'], $b['date']);
            if ($dateCmp !== 0) {
                return $dateCmp;
            }

            $bookableA = $bookables->firstWhere('id', $a['bookable_id']);
            $bookableB = $bookables->firstWhere('id', $b['bookable_id']);

            return strcmp((string) ($bookableA->start_time ?? ''), (string) ($bookableB->start_time ?? ''));
        });

        return array_slice($open, 0, $limit);
    }

    /**
     * @param  array<int, int|string>  $labTestIds  Line items for a lab booking.
     * @param  bool  $sendSms  False for sample/dev seed bookings that must not burn credits.
     * @param  int|null  $yearOfBirth  Optional calendar year of birth for the patient row.
     */
    public function createBookingForBookable(
        Model $bookable,
        string $bookingDate,
        string $patientName,
        string $patientPhone,
        array $labTestIds = [],
        bool $sendSms = true,
        ?string $patientId = null,
        bool $wantsEarlierDate = false,
        ?string $whatsappPhone = null,
        ?bool $shareClinicalHistory = null,
        ?string $nid = null,
        ?int $yearOfBirth = null,
        bool $allowOverflow = false,
        ?string $repeatSeriesId = null,
        bool $allowEndedToday = false,
        ?bool $seenBeforeSoftware = null,
        bool $allowCounselingHandoff = false,
        bool $allowStaffHandoff = false,
        bool $allowMskWalkIn = false,
        ?int $referringDoctorId = null,
    ): Booking {
        $patientPhone = $this->normalizeBdPhone($patientPhone);
        $whatsappPhone = filled($whatsappPhone) ? $this->normalizeBdPhone($whatsappPhone) : null;
        if ($whatsappPhone === $patientPhone) {
            $whatsappPhone = null;
        }

        $booking = DB::transaction(function () use ($bookable, $bookingDate, $patientName, $patientPhone, $labTestIds, $patientId, $wantsEarlierDate, $whatsappPhone, $shareClinicalHistory, $nid, $yearOfBirth, $allowOverflow, $repeatSeriesId, $allowEndedToday, $seenBeforeSoftware, $allowCounselingHandoff, $allowStaffHandoff, $allowMskWalkIn, $referringDoctorId) {
            $tenant = tenant();
            $capMode = $tenant->slot_cap_mode ?? 'per_session';
            if ($capMode === 'per_day') {
                $capMode = 'per_doctor_chamber';
            }

            // Pessimistic lock — Fatima and Rahim cannot both take the last seat.
            if ($bookable instanceof ScheduleSession && $capMode === 'per_doctor_chamber') {
                Doctor::where('id', $bookable->doctor_id)->lockForUpdate()->first();
                $lockedBookable = get_class($bookable)::where('id', $bookable->id)->lockForUpdate()->first();
            } else {
                $lockedBookable = get_class($bookable)::where('id', $bookable->id)->lockForUpdate()->first();
            }

            if (! $lockedBookable) {
                throw BookingUnavailableException::bookableUnavailable();
            }

            $staffHandoff = $allowStaffHandoff || $allowCounselingHandoff;
            $mskDeskWalkIn = $allowMskWalkIn
                && $lockedBookable instanceof ScheduleSession
                && $lockedBookable->kind === ScheduleSession::KIND_MSK;

            if (
                $lockedBookable instanceof ScheduleSession
                && $lockedBookable->isStaffPushedKind()
                && ! $staffHandoff
                && ! $mskDeskWalkIn
            ) {
                throw BookingUnavailableException::staffHandoffWalkIn();
            }

            $date = Carbon::parse($bookingDate);

            // Both bookable types store Carbon's dayOfWeek (0 = Sunday .. 6 = Saturday).
            if ($date->dayOfWeek !== (int) $lockedBookable->day_of_week) {
                throw BookingUnavailableException::dayOfWeekMismatch(
                    $lockedBookable instanceof LabCollectionSlot
                );
            }

            $availability = $this->availabilitySnapshot($lockedBookable, $bookingDate, $capMode, $allowOverflow, $allowEndedToday);

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
                $shareClinicalHistory,
                $nid,
                $yearOfBirth,
                $seenBeforeSoftware,
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

            $publishedCap = $lockedBookable instanceof ScheduleSession
                ? ScheduleSessionPace::publishedCap($lockedBookable)
                : PHP_INT_MAX;
            $isOverflow = $lockedBookable instanceof ScheduleSession && $nextSerial > $publishedCap;

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
                'whatsapp_phone' => $whatsappPhone,
                'serial_number' => $nextSerial,
                'is_overflow' => $isOverflow,
                'status' => 'waiting',
                'wants_earlier_date' => $wantsEarlierDate,
                'repeat_series_id' => $repeatSeriesId,
                'referring_doctor_id' => $referringDoctorId,
            ]);

            if ($tenant?->hasStations() && $lockedBookable instanceof ScheduleSession && (! $staffHandoff || $mskDeskWalkIn)) {
                $booking->update([
                    'care_path' => CarePath::forNewSitting($lockedBookable, $patient),
                    'care_origin_id' => $booking->id,
                ]);
            }

            if ($labTestIds !== []) {
                $this->attachLabTests($booking, $labTestIds);
            }

            return $booking;
        });

        // Past the commit above, so the serial is already the patient's. A
        // voucher is a desk convenience, not part of the booking: letting its
        // failure escape here meant a 500 on a booking that had in fact been
        // taken, and — because the throw lands before the block below — no
        // confirmation SMS either. The patient would then be told to rebook and
        // hit the duplicate guard. Assignment is idempotent, so leaving the
        // number null is recoverable; losing the serial is not.
        try {
            app(VoucherService::class)->assignIfNeeded($booking);
        } catch (\Throwable $e) {
            report($e);
        }

        if ($sendSms) {
            // After commit, and after the response: never roll back a serial
            // because SMS failed, and never make the patient watch a spinner
            // while the aggregator is asked. `->afterResponse()` rather than the
            // queue is deliberate — no worker runs, so a queued confirmation
            // would never be sent. See App\Jobs\SendBookingConfirmation.
            SendBookingConfirmation::dispatch(
                (string) $booking->tenant_id,
                (string) $booking->id,
            )->afterResponse();
        }

        return $booking;
    }

    /**
     * @return array{available: bool, blocked: bool, day_mismatch: bool, cap: int, booked: int, remaining: int}
     */
    private function availabilitySnapshot(Model $bookable, string $bookingDate, string $capMode, bool $allowOverflow = false, bool $allowEndedToday = false): array
    {
        $date = Carbon::parse($bookingDate);
        $dayMismatch = $date->dayOfWeek !== (int) $bookable->day_of_week;
        $blocked = $this->isDateBlocked($bookable, $bookingDate, $allowEndedToday);
        $cap = $bookable instanceof ScheduleSession
            ? ($allowOverflow ? ScheduleSessionPace::staffCap($bookable) : ScheduleSessionPace::publishedCap($bookable))
            : max(0, (int) $bookable->slot_cap);
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

    private function isDateBlocked(Model $bookable, string $bookingDate, bool $allowEndedToday = false): bool
    {
        if (! $allowEndedToday && $this->sessionAlreadyEndedToday($bookable, $bookingDate)) {
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

    private function firstCandidateDate(Model $bookable, Carbon $from): Carbon
    {
        $date = $from->copy();
        while ($date->dayOfWeek !== (int) $bookable->day_of_week) {
            $date->addDay();
        }

        if ($this->sessionAlreadyEndedToday($bookable, $date->toDateString())) {
            $date->addWeek();
        }

        return $date;
    }

    /**
     * @param  Collection<int, ScheduleSession|LabCollectionSlot>  $bookables
     * @return array{per_session: array<string, int>, per_doctor_chamber: array<string, int>}
     */
    private function bulkBookingCounts(
        Collection $bookables,
        string $bookableClass,
        string $capMode,
        Carbon $startDate,
        Carbon $endDate,
    ): array {
        $rows = Booking::query()
            ->selectRaw('bookable_id, booking_date, COUNT(*) as cnt')
            ->where('booking_date', '>=', $startDate->toDateString())
            ->where('booking_date', '<=', $endDate->toDateString())
            ->where('status', '!=', 'cancelled')
            ->where('bookable_type', $bookableClass)
            ->whereIn('bookable_id', $bookables->pluck('id'))
            ->groupBy('bookable_id', 'booking_date')
            ->get();

        $perSession = [];
        foreach ($rows as $row) {
            $date = $row->booking_date instanceof Carbon
                ? $row->booking_date->toDateString()
                : (string) $row->booking_date;
            $perSession["{$row->bookable_id}:{$date}"] = (int) $row->cnt;
        }

        $perDoctorChamber = [];
        if ($capMode === 'per_doctor_chamber' && $bookableClass === ScheduleSession::class) {
            $sessionMap = $bookables->mapWithKeys(fn (Model $b) => [
                $b->id => $b->doctor_id.':'.$b->chamber_id,
            ]);

            foreach ($perSession as $key => $cnt) {
                [$sessionId, $date] = explode(':', $key, 2);
                $dc = $sessionMap->get((int) $sessionId);
                if ($dc) {
                    $dcKey = "{$dc}:{$date}";
                    $perDoctorChamber[$dcKey] = ($perDoctorChamber[$dcKey] ?? 0) + $cnt;
                }
            }
        }

        return [
            'per_session' => $perSession,
            'per_doctor_chamber' => $perDoctorChamber,
        ];
    }

    /**
     * @param  array{per_session: array<string, int>, per_doctor_chamber: array<string, int>}  $bookingCounts
     */
    private function bookedCountFromIndex(
        Model $bookable,
        string $bookingDate,
        string $capMode,
        array $bookingCounts,
    ): int {
        if ($bookable instanceof ScheduleSession && $capMode === 'per_doctor_chamber') {
            $key = $bookable->doctor_id.':'.$bookable->chamber_id.':'.$bookingDate;

            return $bookingCounts['per_doctor_chamber'][$key] ?? 0;
        }

        $key = $bookable->id.':'.$bookingDate;

        return $bookingCounts['per_session'][$key] ?? 0;
    }

    private function isDateBlockedWithBlocks(Model $bookable, string $bookingDate, Collection $slotBlocks): bool
    {
        if ($this->sessionAlreadyEndedToday($bookable, $bookingDate)) {
            return true;
        }

        $blocksOnDate = $slotBlocks->filter(function ($block) use ($bookingDate) {
            $date = $block->date instanceof Carbon
                ? $block->date->toDateString()
                : (string) $block->date;

            return $date === $bookingDate;
        });

        if ($bookable instanceof ScheduleSession) {
            return $blocksOnDate->contains(function ($block) use ($bookable) {
                if (is_null($block->chamber_id) && is_null($block->doctor_id)) {
                    return true;
                }
                if ($block->chamber_id === $bookable->chamber_id && is_null($block->doctor_id)) {
                    return true;
                }

                return $block->doctor_id === $bookable->doctor_id;
            });
        }

        if ($bookable instanceof LabCollectionSlot) {
            return $blocksOnDate->contains(function ($block) use ($bookable) {
                if (is_null($block->chamber_id) && is_null($block->doctor_id)) {
                    return true;
                }

                return $block->chamber_id === $bookable->chamber_id && is_null($block->doctor_id);
            });
        }

        return false;
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

    /**
     * Four-argument convenience wrapper, used only by tests.
     *
     * Despite the old "backwards compatibility" note this replaced, nothing in
     * app/ calls this — it exists so booking tests can create a plain session
     * booking without restating the whole optional tail of
     * createBookingForBookable(). Safe to change shape with the tests.
     */
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
