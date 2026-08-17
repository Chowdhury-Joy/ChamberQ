<?php

namespace App\Services;

use App\Exceptions\BookingUnavailableException;
use App\Models\Booking;
use App\Models\LiveSession;
use App\Models\PlatformSetting;
use App\Models\ScheduleSession;
use App\Models\ScheduleSessionOverride;
use App\Models\SlotBlock;
use App\Models\User;
use App\Support\ScheduleSessionPace;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class StationsHandoffService
{
    public function __construct(
        private readonly BookingService $bookingService,
        private readonly VoucherService $voucherService,
    ) {}

    public function actorMaySend(?User $user): bool
    {
        return $user !== null && ($user->isDoctor() || $user->canWorkDesk());
    }

    public function canSendVisit(Booking $visitBooking): bool
    {
        if (! tenant()?->hasStations()) {
            return false;
        }

        if (in_array($visitBooking->status, ['cancelled', 'no_show'], true)) {
            return false;
        }

        if (! $this->isVisitSitting($visitBooking)) {
            return false;
        }

        return $this->openProcedureFor($visitBooking) === null;
    }

    public function canMove(Booking $booking): bool
    {
        if (! tenant()?->hasStations()) {
            return false;
        }

        $procedure = $this->openProcedureFor($booking);

        return $procedure !== null && $procedure->procedure_status !== Booking::PROCEDURE_DONE;
    }

    public function canSendToCounseling(Booking $procedure): bool
    {
        if (! tenant()?->hasStations()) {
            return false;
        }

        if (in_array($procedure->status, ['cancelled', 'no_show'], true)) {
            return false;
        }

        if ($procedure->bookable_type !== ScheduleSession::class) {
            return false;
        }

        $session = $procedure->bookable;
        if (! $session instanceof ScheduleSession || ! $session->isInterventionKind()) {
            return false;
        }

        if ($procedure->procedure_status !== Booking::PROCEDURE_DONE) {
            return false;
        }

        // Visibility has to match capability. sendToCounseling() resolves a
        // counseling sitting for this chamber, doctor and weekday and throws
        // when there is none, so without this the button was offered on every
        // finished procedure and only failed after staff had confirmed the
        // modal. The intervention actions get away without the check because
        // their modal renders a placeholder instead of the picker; this one is
        // a plain confirm, so there is nowhere to say why.
        $date = $procedure->booking_date?->toDateString();
        if ($date === null || $this->findCounselingSession($session, $date) === null) {
            return false;
        }

        return $this->openCounselingFor($procedure) === null;
    }

    public function isOpenCounseling(Booking $booking): bool
    {
        if ($booking->bookable_type !== ScheduleSession::class) {
            return false;
        }

        if (in_array($booking->status, ['cancelled', 'no_show'], true)) {
            return false;
        }

        $session = $booking->bookable;

        return $session instanceof ScheduleSession
            && $session->kind === ScheduleSession::KIND_COUNSELING;
    }

    public function openCounselingFor(Booking $booking): ?Booking
    {
        if ($this->isOpenCounseling($booking)) {
            return $booking;
        }

        $date = $booking->booking_date?->toDateString();
        if ($date === null) {
            return null;
        }

        $query = Booking::query()
            ->with('bookable')
            ->where('booking_date', $date)
            ->where('bookable_type', ScheduleSession::class);

        if (filled($booking->patient_id)) {
            $query->where('patient_id', $booking->patient_id);
        } else {
            $query->where('patient_phone', $booking->patient_phone);
        }

        return $query->get()->first(fn (Booking $row): bool => $this->isOpenCounseling($row));
    }

    /**
     * Unfinished intervention rows whose date has already passed.
     * Nothing auto-cancels — the list is the prompt.
     *
     * @return \Illuminate\Database\Eloquent\Builder<Booking>
     */
    public function overdueProceduresQuery()
    {
        $interventionIds = ScheduleSession::query()
            ->where('kind', ScheduleSession::KIND_INTERVENTION)
            ->pluck('id');

        return Booking::query()
            ->with(['bookable.chamber', 'bookable.doctor', 'relatedBooking'])
            ->where('bookable_type', ScheduleSession::class)
            ->whereIn('bookable_id', $interventionIds)
            ->where('booking_date', '<', Carbon::today()->toDateString())
            ->where('status', '!=', 'cancelled')
            ->where(function ($query): void {
                $query->whereNull('procedure_status')
                    ->orWhere('procedure_status', '!=', Booking::PROCEDURE_DONE);
            })
            ->orderBy('booking_date')
            ->orderBy('serial_number');
    }

    public function isVisitSitting(Booking $booking): bool
    {
        if ($booking->bookable_type !== ScheduleSession::class) {
            return false;
        }

        $session = $booking->bookable;

        return $session instanceof ScheduleSession && $session->kind === ScheduleSession::KIND_VISIT;
    }

    public function isOpenProcedure(Booking $booking): bool
    {
        if ($booking->bookable_type !== ScheduleSession::class) {
            return false;
        }

        if (in_array($booking->status, ['cancelled'], true)) {
            return false;
        }

        $session = $booking->bookable;

        return $session instanceof ScheduleSession
            && $session->isInterventionKind()
            && $booking->procedure_status !== Booking::PROCEDURE_DONE;
    }

    public function openProcedureFor(Booking $booking): ?Booking
    {
        if ($this->isOpenProcedure($booking)) {
            return $booking;
        }

        if (! $this->isVisitSitting($booking)) {
            return null;
        }

        $related = $booking->relationLoaded('procedureBookings')
            ? $booking->procedureBookings
            : $booking->procedureBookings()->with('bookable')->get();

        return $related->first(fn (Booking $row): bool => $this->isOpenProcedure($row));
    }

    /**
     * Upcoming intervention sittings for this visit's doctor and chamber.
     *
     * Same-day stays on the list even when OT was this morning — staff/doctor
     * can still put the patient on today's procedure list. The default pick
     * is the next sitting whose end time has not passed.
     *
     * @return list<array{
     *     key: string,
     *     date: string,
     *     session_id: int,
     *     label: string,
     *     description: string,
     *     is_same_day: bool,
     *     sitting_ended: bool,
     *     is_default: bool
     * }>
     */
    public function sittingOptions(Booking $booking): array
    {
        $fromSession = $booking->bookable;
        if (! $fromSession instanceof ScheduleSession) {
            return [];
        }

        $sessions = $this->interventionSessions($fromSession);
        if ($sessions->isEmpty()) {
            return [];
        }

        $horizon = PlatformSetting::patientBookingHorizonDays();
        $startDate = Carbon::today();
        $endDate = $startDate->copy()->addDays($horizon);
        $overrides = ScheduleSessionOverride::query()
            ->whereIn('schedule_session_id', $sessions->pluck('id'))
            ->where('override_date', '>=', $startDate->toDateString())
            ->where('override_date', '<=', $endDate->toDateString())
            ->get()
            ->groupBy(fn (ScheduleSessionOverride $row): string => $row->schedule_session_id.'|'.$row->override_date->toDateString());
        $blocks = SlotBlock::query()
            ->where('date', '>=', $startDate->toDateString())
            ->where('date', '<=', $endDate->toDateString())
            ->get();

        $options = [];
        for ($cursor = $startDate->copy(); $cursor->lte($endDate); $cursor->addDay()) {
            $ymd = $cursor->toDateString();

            foreach ($sessions as $session) {
                if ((int) $session->day_of_week !== $cursor->dayOfWeek) {
                    continue;
                }

                if ($this->dateIsBlocked($session, $ymd, $blocks)) {
                    continue;
                }

                $hours = $this->hoursForDate($session, $ymd, $overrides);
                $ended = $cursor->isToday()
                    && filled($hours['end'])
                    && Carbon::parse($ymd.' '.$hours['end'])->isPast();
                $beforeVisit = $fromSession->kind === ScheduleSession::KIND_VISIT
                    && filled($fromSession->start_time)
                    && filled($hours['start'])
                    && $this->minutes($hours['start']) < $this->minutes($fromSession->start_time);

                $options[] = [
                    'key' => $ymd.'|'.$session->id,
                    'date' => $ymd,
                    'session_id' => (int) $session->id,
                    'label' => $this->optionLabel($cursor, $hours, $ended, $beforeVisit),
                    'description' => $this->optionDescription($ended, $beforeVisit, $cursor->isToday()),
                    'is_same_day' => $cursor->isToday(),
                    'sitting_ended' => $ended,
                    'is_default' => false,
                ];
            }
        }

        $default = collect($options)->first(fn (array $option): bool => ! $option['sitting_ended'])
            ?? $options[0]
            ?? null;

        if ($default !== null) {
            foreach ($options as $i => $option) {
                $options[$i]['is_default'] = $option['key'] === $default['key'];
            }
        }

        return $options;
    }

    public function sendVisitToIntervention(Booking $visitBooking, string $bookingDate, ?int $sessionId = null): Booking
    {
        if (! tenant()?->hasStations()) {
            throw new InvalidArgumentException(__('Stations module is not enabled.'));
        }

        if (! $this->isVisitSitting($visitBooking)) {
            throw new InvalidArgumentException(__('Send to intervention is only available from a visit sitting.'));
        }

        if (in_array($visitBooking->status, ['cancelled', 'no_show'], true)) {
            throw new InvalidArgumentException(__('That visit cannot be sent to intervention.'));
        }

        if ($this->openProcedureFor($visitBooking) !== null) {
            throw new InvalidArgumentException(__('This patient already has an intervention sitting booked.'));
        }

        $visitSession = $this->visitSession($visitBooking);
        $interventionSession = $this->resolveInterventionSession($visitSession, $bookingDate, $sessionId);

        return DB::transaction(function () use ($visitBooking, $interventionSession, $bookingDate) {
            try {
                $procedureBooking = $this->bookingService->createBookingForBookable(
                    $interventionSession,
                    $bookingDate,
                    $visitBooking->patient_name,
                    $visitBooking->patient_phone,
                    sendSms: false,
                    patientId: $visitBooking->patient_id,
                    whatsappPhone: $visitBooking->whatsapp_phone,
                    allowOverflow: true,
                    allowEndedToday: Carbon::parse($bookingDate)->isToday(),
                );
            } catch (BookingUnavailableException $e) {
                throw new InvalidArgumentException($e->getMessage(), 0, $e);
            }

            $procedureBooking->update([
                'related_booking_id' => $visitBooking->id,
                'procedure_status' => Booking::PROCEDURE_LOGGED,
            ]);

            $this->voucherService->assignIfNeeded($procedureBooking);

            if ($visitBooking->voucher_number === null) {
                $this->voucherService->assignIfNeeded($visitBooking);
            }

            return $procedureBooking->fresh(['bookable']);
        });
    }

    public function sendToCounseling(Booking $procedure): Booking
    {
        if (! tenant()?->hasStations()) {
            throw new InvalidArgumentException(__('Stations module is not enabled.'));
        }

        $session = $procedure->bookable;
        if (! $session instanceof ScheduleSession || ! $session->isInterventionKind()) {
            throw new InvalidArgumentException(__('Send to counseling is only available from a finished procedure.'));
        }

        if (in_array($procedure->status, ['cancelled', 'no_show'], true)) {
            throw new InvalidArgumentException(__('That procedure cannot be sent to counseling.'));
        }

        if ($procedure->procedure_status !== Booking::PROCEDURE_DONE) {
            throw new InvalidArgumentException(__('Finish the procedure before sending them to counseling.'));
        }

        if ($this->openCounselingFor($procedure) !== null) {
            throw new InvalidArgumentException(__('This patient is already on today\'s counseling list.'));
        }

        $date = $procedure->booking_date->toDateString();
        $counselingSession = $this->resolveCounselingSession($session, $date);

        return DB::transaction(function () use ($procedure, $counselingSession, $date) {
            try {
                $counseling = $this->bookingService->createBookingForBookable(
                    $counselingSession,
                    $date,
                    $procedure->patient_name,
                    $procedure->patient_phone,
                    sendSms: false,
                    patientId: $procedure->patient_id,
                    whatsappPhone: $procedure->whatsapp_phone,
                    allowOverflow: true,
                    allowEndedToday: true,
                    allowCounselingHandoff: true,
                );
            } catch (BookingUnavailableException $e) {
                throw new InvalidArgumentException($e->getMessage(), 0, $e);
            }

            $counseling->update([
                'related_booking_id' => $procedure->id,
            ]);

            return $counseling->fresh(['bookable']);
        });
    }

    public function moveProcedure(Booking $booking, string $bookingDate, ?int $sessionId = null): Booking
    {
        $procedure = $this->openProcedureFor($booking);
        if (! $procedure) {
            throw new InvalidArgumentException(__('No intervention sitting is booked for this patient.'));
        }

        if ($procedure->procedure_status === Booking::PROCEDURE_DONE) {
            throw new InvalidArgumentException(__('A finished procedure cannot be moved.'));
        }

        $sourceSession = $procedure->bookable;
        if (! $sourceSession instanceof ScheduleSession) {
            throw new InvalidArgumentException(__('Procedure status applies to sitting bookings only.'));
        }

        $visitSession = $sourceSession;
        if ($procedure->related_booking_id) {
            $visit = $procedure->relatedBooking;
            if ($visit && $this->isVisitSitting($visit)) {
                $visitSession = $this->visitSession($visit);
            }
        }

        $interventionSession = $this->resolveInterventionSession($visitSession, $bookingDate, $sessionId);
        $currentDate = $procedure->booking_date?->toDateString();

        if ($currentDate === $bookingDate && (int) $procedure->bookable_id === (int) $interventionSession->id) {
            throw new InvalidArgumentException(__('That patient is already on this intervention sitting.'));
        }

        if ($this->dateIsBlocked(
            $interventionSession,
            $bookingDate,
            SlotBlock::query()->where('date', $bookingDate)->get(),
        )) {
            throw new InvalidArgumentException(__('The clinic is closed on the date you chose. Please pick another date.'));
        }

        return DB::transaction(function () use ($procedure, $interventionSession, $bookingDate) {
            $lockedSession = ScheduleSession::query()
                ->whereKey($interventionSession->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedProcedure = Booking::query()
                ->whereKey($procedure->id)
                ->lockForUpdate()
                ->firstOrFail();

            $availability = $this->bookingService->availabilityFor(
                $lockedSession,
                $bookingDate,
                allowOverflow: true,
            );

            if ($availability['remaining'] <= 0 || $availability['blocked'] || $availability['day_mismatch']) {
                throw new InvalidArgumentException(
                    $availability['blocked']
                        ? __('The clinic is closed on the date you chose. Please pick another date.')
                        : __('That intervention sitting is full.')
                );
            }

            LiveSession::query()
                ->where('current_booking_id', $lockedProcedure->id)
                ->get()
                ->each(function (LiveSession $live): void {
                    $live->update(['current_booking_id' => null]);
                });

            $maxSerial = Booking::query()
                ->where('bookable_type', ScheduleSession::class)
                ->where('bookable_id', $lockedSession->id)
                ->where('booking_date', $bookingDate)
                ->where('id', '!=', $lockedProcedure->id)
                ->max('serial_number');
            $nextSerial = ($maxSerial ?? 0) + 1;
            $publishedCap = ScheduleSessionPace::publishedCap($lockedSession);

            $lockedProcedure->update([
                'bookable_id' => $lockedSession->id,
                'booking_date' => $bookingDate,
                'serial_number' => $nextSerial,
                'is_overflow' => $nextSerial > $publishedCap,
                'status' => 'waiting',
                'procedure_status' => Booking::PROCEDURE_LOGGED,
                'called_at' => null,
                'in_chamber_at' => null,
                'completed_at' => null,
                'skip_count' => 0,
                'retry_queue_position' => null,
            ]);

            return $lockedProcedure->fresh(['bookable']);
        });
    }

    public function advanceProcedureStatus(Booking $booking, string $status): void
    {
        if ($booking->bookable_type !== ScheduleSession::class) {
            throw new InvalidArgumentException(__('Procedure status applies to sitting bookings only.'));
        }

        $session = $booking->bookable;
        if (! $session instanceof ScheduleSession || $session->kind !== ScheduleSession::KIND_INTERVENTION) {
            throw new InvalidArgumentException(__('Procedure status applies to intervention rows only.'));
        }

        $allowed = [
            Booking::PROCEDURE_LOGGED,
            Booking::PROCEDURE_PREPPED,
            Booking::PROCEDURE_DOCTOR_CALLED,
            Booking::PROCEDURE_DONE,
        ];

        if (! in_array($status, $allowed, true)) {
            throw new InvalidArgumentException(__('Unknown procedure status.'));
        }

        $booking->update(['procedure_status' => $status]);
    }

    /**
     * @param  array{date: string, session_id: int}  $choice
     */
    public function parseSittingKey(string $key): array
    {
        $parts = explode('|', $key, 2);
        if (count($parts) !== 2 || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $parts[0]) || ! ctype_digit($parts[1])) {
            throw new InvalidArgumentException(__('Choose an intervention sitting.'));
        }

        return [
            'date' => $parts[0],
            'session_id' => (int) $parts[1],
        ];
    }

    private function visitSession(Booking $visitBooking): ScheduleSession
    {
        $session = $visitBooking->bookable;
        if (! $session instanceof ScheduleSession) {
            throw new InvalidArgumentException(__('Only sitting bookings can be sent to intervention.'));
        }

        return $session;
    }

    /**
     * @return \Illuminate\Support\Collection<int, ScheduleSession>
     */
    private function interventionSessions(ScheduleSession $visitSession)
    {
        return ScheduleSession::query()
            ->where('chamber_id', $visitSession->chamber_id)
            ->where('doctor_id', $visitSession->doctor_id)
            ->where('kind', ScheduleSession::KIND_INTERVENTION)
            ->orderBy('start_time')
            ->get();
    }

    private function resolveInterventionSession(ScheduleSession $visitSession, string $bookingDate, ?int $sessionId): ScheduleSession
    {
        $date = Carbon::parse($bookingDate);
        $query = $this->interventionSessions($visitSession)
            ->where('day_of_week', $date->dayOfWeek);

        if ($sessionId !== null) {
            $match = $query->firstWhere('id', $sessionId);
            if (! $match) {
                throw new InvalidArgumentException(__('That intervention sitting is not available for this doctor.'));
            }

            return $match;
        }

        // Two intervention sittings the same day: pick the earlier start.
        // Staff who want the later one pass sessionId from the picker.
        $match = $query->first();
        if (! $match) {
            throw new InvalidArgumentException(__('No intervention sitting is scheduled for this doctor on that date.'));
        }

        return $match;
    }

    /**
     * The counseling sitting this procedure would hand off to, or null.
     *
     * Split out of resolveCounselingSession() so canSendToCounseling() can ask
     * the same question without catching an exception to answer it.
     */
    private function findCounselingSession(ScheduleSession $procedureSession, string $bookingDate): ?ScheduleSession
    {
        $date = Carbon::parse($bookingDate);

        return ScheduleSession::query()
            ->where('chamber_id', $procedureSession->chamber_id)
            ->where('doctor_id', $procedureSession->doctor_id)
            ->where('kind', ScheduleSession::KIND_COUNSELING)
            ->where('day_of_week', $date->dayOfWeek)
            ->orderBy('start_time')
            ->first();
    }

    private function resolveCounselingSession(ScheduleSession $procedureSession, string $bookingDate): ScheduleSession
    {
        $date = Carbon::parse($bookingDate);
        $match = ScheduleSession::query()
            ->where('chamber_id', $procedureSession->chamber_id)
            ->where('doctor_id', $procedureSession->doctor_id)
            ->where('kind', ScheduleSession::KIND_COUNSELING)
            ->where('day_of_week', $date->dayOfWeek)
            ->orderBy('start_time')
            ->first();

        if (! $match) {
            throw new InvalidArgumentException(__('No counseling sitting is scheduled for this doctor on that date.'));
        }

        return $match;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, SlotBlock>  $blocks
     */
    private function dateIsBlocked(ScheduleSession $session, string $bookingDate, $blocks): bool
    {
        return $blocks->contains(function (SlotBlock $block) use ($session, $bookingDate): bool {
            $date = $block->date instanceof Carbon
                ? $block->date->toDateString()
                : (string) $block->date;

            if ($date !== $bookingDate) {
                return false;
            }

            if (is_null($block->chamber_id) && is_null($block->doctor_id)) {
                return true;
            }

            if ($block->chamber_id === $session->chamber_id && is_null($block->doctor_id)) {
                return true;
            }

            return $block->doctor_id === $session->doctor_id;
        });
    }

    /**
     * @param  \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<int, ScheduleSessionOverride>>  $overrides
     * @return array{start: ?string, end: ?string}
     */
    private function hoursForDate(ScheduleSession $session, string $date, $overrides): array
    {
        $row = $overrides->get($session->id.'|'.$date)?->first();

        return [
            'start' => $row?->start_time ?? $session->start_time,
            'end' => $row?->end_time ?? $session->end_time,
        ];
    }

    /**
     * @param  array{start: ?string, end: ?string}  $hours
     */
    private function optionLabel(Carbon $date, array $hours, bool $ended, bool $beforeVisit): string
    {
        $when = $date->isToday()
            ? __('Today')
            : ($date->isTomorrow() ? __('Tomorrow') : $date->isoFormat('ddd D MMM'));
        $window = $this->clockRange($hours['start'], $hours['end']);

        $label = $when.' · '.$window;
        if ($ended) {
            return $label.' · '.__('sitting time has passed');
        }
        if ($date->isToday() && $beforeVisit) {
            return $label.' · '.__('before visit hours');
        }

        return $label;
    }

    private function optionDescription(bool $ended, bool $beforeVisit, bool $sameDay): string
    {
        if ($ended) {
            return __('List them on today\'s intervention list anyway — they stay or come back today.');
        }

        if ($sameDay && $beforeVisit) {
            return __('OT is this morning, before visiting hours. Use this only if they will still be done today.');
        }

        if ($sameDay) {
            return __('Same-day procedure.');
        }

        return __('They take a serial on that intervention sitting.');
    }

    private function clockRange(?string $start, ?string $end): string
    {
        if (blank($start) || blank($end)) {
            return __('Intervention');
        }

        return Carbon::parse($start)->format('g:i A').' – '.Carbon::parse($end)->format('g:i A');
    }

    private function minutes(string $time): int
    {
        return Carbon::parse($time)->hour * 60 + Carbon::parse($time)->minute;
    }
}
