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
    /**
     * Visit / consult / OT sittings — the clinic clock. Lab, report and
     * counseling lists follow this day; they are not sittings of their own.
     *
     * @var list<string>
     */
    public const CLINIC_SITTING_KINDS = [
        ScheduleSession::KIND_VISIT,
        ScheduleSession::KIND_CONSULT,
        ScheduleSession::KIND_INTERVENTION,
    ];

    /**
     * @var list<string>
     */
    public const FLOOR_ROOM_KINDS = [
        ScheduleSession::KIND_MSK,
        ScheduleSession::KIND_REPORT,
        ScheduleSession::KIND_COUNSELING,
    ];

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

        if (! $this->isVisitSitting($visitBooking) && ! $this->isMskSitting($visitBooking)) {
            return false;
        }

        if (! in_array(ScheduleSession::KIND_INTERVENTION, $this->nextRoomKinds($visitBooking), true)) {
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

        if (! in_array(ScheduleSession::KIND_COUNSELING, $this->nextRoomKinds($procedure), true)) {
            return false;
        }

        $session = $procedure->bookable;
        if ($session instanceof ScheduleSession && $session->isInterventionKind()
            && $procedure->procedure_status !== Booking::PROCEDURE_DONE) {
            return false;
        }

        $date = $procedure->booking_date?->toDateString();
        if ($date === null) {
            return false;
        }

        return $this->openLinkedOfKind($procedure, ScheduleSession::KIND_COUNSELING) === null;
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

    public function canSendToLab(Booking $booking): bool
    {
        return $this->labTypeOptions($booking) !== [];
    }

    public function canSendToMsk(Booking $booking): bool
    {
        return $this->canSendToFreeRoom($booking, ScheduleSession::KIND_MSK);
    }

    /**
     * Floor lab destinations this visit can still be sent to.
     *
     * @return array<string, string>
     */
    public function labTypeOptions(Booking $booking): array
    {
        $options = [];

        if ($this->canSendToMsk($booking)) {
            $options[ScheduleSession::KIND_MSK] = __('MSK scan');
        }

        return $options;
    }

    public function sendToLab(Booking $from, string $labType): Booking
    {
        if ($labType !== ScheduleSession::KIND_MSK) {
            throw new InvalidArgumentException(__('Choose a lab type.'));
        }

        return $this->sendToMsk($from);
    }

    public function canSendToReport(Booking $booking): bool
    {
        if (! tenant()?->hasStations()) {
            return false;
        }

        if (in_array($booking->status, ['cancelled', 'no_show'], true)) {
            return false;
        }

        if (! in_array(ScheduleSession::KIND_REPORT, $this->nextRoomKinds($booking), true)) {
            return false;
        }

        $session = $booking->bookable;
        if ($session instanceof ScheduleSession && $session->isInterventionKind()
            && $booking->procedure_status !== Booking::PROCEDURE_DONE) {
            return false;
        }

        return $this->openLinkedOfKind($booking, ScheduleSession::KIND_REPORT) === null;
    }

    /**
     * @return list<string>
     */
    public function nextRoomKinds(Booking $booking): array
    {
        if (! tenant()?->hasStations()) {
            return [];
        }

        if (in_array($booking->status, ['cancelled', 'no_show'], true)) {
            return [];
        }

        $session = $booking->bookable;
        if (! $session instanceof ScheduleSession) {
            return [];
        }

        $date = $booking->booking_date?->toDateString();
        if ($date === null) {
            return [];
        }

        $path = $this->pathOf($booking);
        $branch = $this->branchOf($booking);
        $current = filled($session->kind) ? $session->kind : ScheduleSession::KIND_VISIT;

        if ($this->isVisitSitting($booking) && ! filled($branch)) {
            $options = [];
            if ($this->hasRoom($session, ScheduleSession::KIND_MSK, $date)) {
                $options[] = ScheduleSession::KIND_MSK;
            } elseif ($path === CarePath::FOLLOW_UP && $this->hasRoom($session, ScheduleSession::KIND_REPORT, $date)) {
                $options[] = ScheduleSession::KIND_REPORT;
            }
            if ($this->hasRoom($session, ScheduleSession::KIND_INTERVENTION, $date)) {
                $options[] = ScheduleSession::KIND_INTERVENTION;
            }

            if ($options !== []) {
                return $options;
            }
        }

        $seq = CarePath::sequence($path, $branch);
        $idx = array_search($current, $seq, true);
        if ($idx === false) {
            return [];
        }

        $existing = [];
        foreach (array_slice($seq, $idx + 1) as $kind) {
            if ($this->hasRoom($session, $kind, $date)) {
                $existing[] = $kind;
            }
        }

        return $existing === [] ? [] : [$existing[0]];
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
        return $this->sittingIsKind($booking, ScheduleSession::KIND_VISIT);
    }

    public function isMskSitting(Booking $booking): bool
    {
        return $this->sittingIsKind($booking, ScheduleSession::KIND_MSK);
    }

    public function isReportSitting(Booking $booking): bool
    {
        return $this->sittingIsKind($booking, ScheduleSession::KIND_REPORT);
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

        $date = $booking->booking_date?->toDateString();
        $originId = $this->originId($booking);

        $query = Booking::query()
            ->with('bookable')
            ->where('bookable_type', ScheduleSession::class)
            ->where('status', '!=', 'cancelled')
            ->where(function ($q) use ($booking, $originId): void {
                $q->where('care_origin_id', $originId)
                    ->orWhere('related_booking_id', $booking->id)
                    ->orWhere('id', $originId);
            });

        if (filled($date)) {
            // Procedures may be booked for a later sitting than today's visit.
        }

        return $query->get()->first(fn (Booking $row): bool => $this->isOpenProcedure($row));
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

        $visitDate = $booking->booking_date?->toDateString() ?? Carbon::today()->toDateString();
        $this->ensureRoomQueue($fromSession, ScheduleSession::KIND_INTERVENTION, $visitDate);

        $sessions = $this->interventionSessions($fromSession);
        if ($sessions->isEmpty()) {
            return [];
        }

        $horizon = PlatformSetting::platformHorizonDays();
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

    public function sendVisitToIntervention(Booking $visitBooking, string $bookingDate, ?int $sessionId = null, ?int $feeCatalogItemId = null, ?string $remarks = null): Booking
    {
        if (! tenant()?->hasStations()) {
            throw new InvalidArgumentException(__('Stations module is not enabled.'));
        }

        if (! $this->canSendVisit($visitBooking)) {
            throw new InvalidArgumentException(__('Send to intervention is only available from a visit or MSK sitting.'));
        }

        $fromSession = $this->sittingSession($visitBooking);
        $interventionSession = $this->resolveInterventionSession($fromSession, $bookingDate, $sessionId);

        return DB::transaction(function () use ($visitBooking, $interventionSession, $bookingDate, $feeCatalogItemId, $remarks) {
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
                    feeCatalogItemId: $feeCatalogItemId,
                    remarks: filled($remarks) ? $remarks : $visitBooking->remarks,
                );
            } catch (BookingUnavailableException $e) {
                throw new InvalidArgumentException($e->getMessage(), 0, $e);
            }

            $branch = $this->pathOf($visitBooking) === CarePath::FOLLOW_UP
                && $this->isVisitSitting($visitBooking)
                ? CarePath::BRANCH_INTERVENTION
                : null;

            $this->stampLinked($procedureBooking, $visitBooking, $branch);
            $procedureBooking->update([
                'procedure_status' => Booking::PROCEDURE_LOGGED,
            ]);

            $this->voucherService->assignIfNeeded($procedureBooking);

            if ($visitBooking->voucher_number === null) {
                $this->voucherService->assignIfNeeded($visitBooking);
            }

            return $procedureBooking->fresh(['bookable']);
        });
    }

    public function sendToCounseling(Booking $from): Booking
    {
        if (! $this->canSendToCounseling($from)) {
            throw new InvalidArgumentException(__('Send to counseling is only available from a finished procedure or a report sitting.'));
        }

        return $this->sendToFreeRoom(
            $from,
            ScheduleSession::KIND_COUNSELING,
            __('This patient is already on today\'s counseling list.'),
            __('Counseling is not open — this clinic is not sitting that day, or counseling is not a room here.'),
        );
    }

    public function sendToMsk(Booking $from): Booking
    {
        if (! $this->canSendToMsk($from)) {
            throw new InvalidArgumentException(__('Send to lab is only available from a visit sitting.'));
        }

        $branch = $this->pathOf($from) === CarePath::FOLLOW_UP
            ? CarePath::BRANCH_MSK
            : null;

        return $this->sendToFreeRoom(
            $from,
            ScheduleSession::KIND_MSK,
            __('This patient is already on today\'s MSK list.'),
            __('Lab is not open — this clinic is not sitting that day, or there is no lab room.'),
            $branch,
        );
    }

    public function sendToReport(Booking $from): Booking
    {
        if (! $this->canSendToReport($from)) {
            throw new InvalidArgumentException(__('Send to report is only available after MSK or a finished procedure.'));
        }

        return $this->sendToFreeRoom(
            $from,
            ScheduleSession::KIND_REPORT,
            __('This patient is already on today\'s report list.'),
            __('Report is not open — this clinic is not sitting that day, or there is no report room.'),
        );
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
                $visitSession = $this->sittingSession($visit);
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

    private function sittingSession(Booking $booking): ScheduleSession
    {
        $session = $booking->bookable;
        if (! $session instanceof ScheduleSession) {
            throw new InvalidArgumentException(__('Only sitting bookings can be sent to intervention.'));
        }

        return $session;
    }

    private function visitSession(Booking $visitBooking): ScheduleSession
    {
        return $this->sittingSession($visitBooking);
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
            $match = $this->ensureRoomQueue($visitSession, ScheduleSession::KIND_INTERVENTION, $bookingDate);
        }

        if (! $match) {
            throw new InvalidArgumentException(__('Intervention is not open — this clinic is not sitting that day.'));
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
        return $this->findSession($procedureSession, ScheduleSession::KIND_COUNSELING, $bookingDate);
    }

    private function resolveCounselingSession(ScheduleSession $procedureSession, string $bookingDate): ScheduleSession
    {
        $match = $this->findSession($procedureSession, ScheduleSession::KIND_COUNSELING, $bookingDate);
        if (! $match) {
            throw new InvalidArgumentException(__('No counseling sitting is scheduled for this doctor on that date.'));
        }

        return $match;
    }

    public function provisionFloorRoomsForDate(string $date): void
    {
        if (! tenant()?->hasStations()) {
            return;
        }

        $dow = Carbon::parse($date)->dayOfWeek;
        $sittings = ScheduleSession::query()
            ->whereIn('kind', self::CLINIC_SITTING_KINDS)
            ->where('day_of_week', $dow)
            ->get()
            ->unique(fn (ScheduleSession $session): string => $session->chamber_id.'|'.$session->doctor_id);

        foreach ($sittings as $from) {
            foreach (self::FLOOR_ROOM_KINDS as $kind) {
                if ($this->shouldProvisionFloorRoom($kind)) {
                    $this->findSession($from, $kind, $date);
                }
            }

            $this->findSession($from, ScheduleSession::KIND_INTERVENTION, $date);
        }
    }

    public function ensureRoomQueue(ScheduleSession $from, string $kind, string $bookingDate): ?ScheduleSession
    {
        return $this->findSession($from, $kind, $bookingDate);
    }

    private function findSession(ScheduleSession $from, string $kind, string $bookingDate): ?ScheduleSession
    {
        $date = Carbon::parse($bookingDate);

        $existing = ScheduleSession::query()
            ->where('chamber_id', $from->chamber_id)
            ->where('doctor_id', $from->doctor_id)
            ->where('kind', $kind)
            ->where('day_of_week', $date->dayOfWeek)
            ->orderBy('start_time')
            ->first();

        if ($existing) {
            return $existing;
        }

        if (! $this->shouldProvisionFloorRoom($kind) || ! $this->clinicIsOpenOn($from, $bookingDate)) {
            return null;
        }

        return $this->provisionRoomQueue($from, $kind, $bookingDate);
    }

    private function shouldProvisionFloorRoom(string $kind): bool
    {
        if ($kind === ScheduleSession::KIND_INTERVENTION) {
            return tenant()?->hasStations() === true;
        }

        if (! in_array($kind, self::FLOOR_ROOM_KINDS, true)) {
            return false;
        }

        if (! PracticeRules::hasFloorRoom($kind)) {
            return false;
        }

        if ($kind === ScheduleSession::KIND_COUNSELING && PracticeRules::counselingIsOwnSession()) {
            return false;
        }

        return true;
    }

    private function clinicSittingsOnDate(ScheduleSession $from, string $date)
    {
        $dow = Carbon::parse($date)->dayOfWeek;

        return ScheduleSession::query()
            ->where('chamber_id', $from->chamber_id)
            ->where('doctor_id', $from->doctor_id)
            ->whereIn('kind', self::CLINIC_SITTING_KINDS)
            ->where('day_of_week', $dow)
            ->get();
    }

    private function clinicIsOpenOn(ScheduleSession $from, string $date): bool
    {
        if ($this->clinicSittingsOnDate($from, $date)->isNotEmpty()) {
            return true;
        }

        return in_array($from->kind, self::CLINIC_SITTING_KINDS, true)
            && (int) $from->day_of_week === Carbon::parse($date)->dayOfWeek;
    }

    private function provisionRoomQueue(ScheduleSession $from, string $kind, string $bookingDate): ScheduleSession
    {
        $sittings = $this->clinicSittingsOnDate($from, $bookingDate);
        if ($sittings->isEmpty() && in_array($from->kind, self::CLINIC_SITTING_KINDS, true)) {
            $sittings = collect([$from]);
        }

        $starts = $sittings->pluck('start_time')->filter()->sort()->values();
        $ends = $sittings->pluck('end_time')->filter()->sort()->values();

        $name = match ($kind) {
            ScheduleSession::KIND_MSK => __('Lab'),
            ScheduleSession::KIND_REPORT => __('Report'),
            ScheduleSession::KIND_COUNSELING => __('Counseling'),
            ScheduleSession::KIND_INTERVENTION => __('Intervention'),
            default => $kind,
        };

        return ScheduleSession::create([
            'chamber_id' => $from->chamber_id,
            'doctor_id' => $from->doctor_id,
            'day_of_week' => Carbon::parse($bookingDate)->dayOfWeek,
            'session_name' => $name,
            'kind' => $kind,
            'start_time' => $starts->first() ?? $from->start_time,
            'end_time' => $ends->last() ?? $from->end_time,
            'slot_cap' => 40,
            'walk_in_overflow_cap' => in_array($kind, [ScheduleSession::KIND_MSK, ScheduleSession::KIND_INTERVENTION], true) ? 6 : 0,
        ]);
    }

    private function hasRoom(ScheduleSession $from, string $kind, string $date): bool
    {
        if ($kind === ScheduleSession::KIND_INTERVENTION) {
            $weekday = Carbon::parse($date)->dayOfWeek;
            $hasSitting = $this->interventionSessions($from)
                ->contains(fn (ScheduleSession $session): bool => (int) $session->day_of_week === $weekday);

            return $hasSitting || $this->clinicIsOpenOn($from, $date);
        }

        if (in_array($kind, self::FLOOR_ROOM_KINDS, true)) {
            if (! PracticeRules::hasFloorRoom($kind)) {
                return false;
            }

            if ($kind === ScheduleSession::KIND_COUNSELING && PracticeRules::counselingIsOwnSession()) {
                return $this->lookupSession($from, $kind, $date) !== null;
            }

            return $this->clinicIsOpenOn($from, $date);
        }

        return $this->lookupSession($from, $kind, $date) !== null;
    }

    private function lookupSession(ScheduleSession $from, string $kind, string $bookingDate): ?ScheduleSession
    {
        $date = Carbon::parse($bookingDate);

        return ScheduleSession::query()
            ->where('chamber_id', $from->chamber_id)
            ->where('doctor_id', $from->doctor_id)
            ->where('kind', $kind)
            ->where('day_of_week', $date->dayOfWeek)
            ->orderBy('start_time')
            ->first();
    }

    private function sittingIsKind(Booking $booking, string $kind): bool
    {
        if ($booking->bookable_type !== ScheduleSession::class) {
            return false;
        }

        $session = $booking->bookable;

        return $session instanceof ScheduleSession && $session->kind === $kind;
    }

    private function canSendToFreeRoom(Booking $booking, string $kind): bool
    {
        if (! tenant()?->hasStations()) {
            return false;
        }

        if (in_array($booking->status, ['cancelled', 'no_show'], true)) {
            return false;
        }

        if (! in_array($kind, $this->nextRoomKinds($booking), true)) {
            return false;
        }

        return $this->openLinkedOfKind($booking, $kind) === null;
    }

    private function sendToFreeRoom(
        Booking $from,
        string $kind,
        string $alreadyMessage,
        string $missingMessage,
        ?string $branch = null,
    ): Booking {
        if (! tenant()?->hasStations()) {
            throw new InvalidArgumentException(__('Stations module is not enabled.'));
        }

        $session = $from->bookable;
        if (! $session instanceof ScheduleSession) {
            throw new InvalidArgumentException(__('Only sitting bookings can be handed off.'));
        }

        if ($this->openLinkedOfKind($from, $kind) !== null) {
            throw new InvalidArgumentException($alreadyMessage);
        }

        $date = $from->booking_date->toDateString();
        $target = $this->findSession($session, $kind, $date);
        if (! $target) {
            throw new InvalidArgumentException($missingMessage);
        }

        return DB::transaction(function () use ($from, $target, $date, $branch) {
            try {
                $row = $this->bookingService->createBookingForBookable(
                    $target,
                    $date,
                    $from->patient_name,
                    $from->patient_phone,
                    sendSms: false,
                    patientId: $from->patient_id,
                    whatsappPhone: $from->whatsapp_phone,
                    allowOverflow: true,
                    allowEndedToday: true,
                    allowStaffHandoff: true,
                    remarks: $from->remarks,
                );
            } catch (BookingUnavailableException $e) {
                throw new InvalidArgumentException($e->getMessage(), 0, $e);
            }

            $this->stampLinked($row, $from, $branch);

            return $row->fresh(['bookable']);
        });
    }

    private function stampLinked(Booking $child, Booking $from, ?string $branch = null): void
    {
        $originId = $this->originId($from);

        if ($from->care_origin_id === null) {
            $from->update([
                'care_origin_id' => $originId,
                'care_path' => $from->care_path ?: CarePath::VISIT,
            ]);
        }

        if ($branch !== null) {
            Booking::query()
                ->where(function ($q) use ($originId): void {
                    $q->where('id', $originId)->orWhere('care_origin_id', $originId);
                })
                ->update(['care_branch' => $branch]);
        }

        $origin = Booking::query()->find($originId);
        $child->update([
            'related_booking_id' => $from->id,
            'care_path' => $origin?->care_path ?? $from->care_path ?? CarePath::VISIT,
            'care_branch' => $branch ?? $origin?->care_branch ?? $from->care_branch,
            'care_origin_id' => $originId,
        ]);
    }

    private function originId(Booking $booking): string
    {
        if (filled($booking->care_origin_id)) {
            return (string) $booking->care_origin_id;
        }

        $walk = $booking;
        $guard = 0;
        while (filled($walk->related_booking_id) && $guard < 8) {
            $parent = $walk->relatedBooking;
            if (! $parent) {
                break;
            }
            $walk = $parent;
            $guard++;
        }

        return (string) $walk->id;
    }

    private function pathOf(Booking $booking): string
    {
        if (filled($booking->care_path)) {
            return $booking->care_path;
        }

        $origin = $booking->careOrigin ?? ($booking->care_origin_id ? Booking::query()->find($booking->care_origin_id) : null);
        if ($origin && filled($origin->care_path)) {
            return $origin->care_path;
        }

        $session = $booking->bookable;

        return $session instanceof ScheduleSession && $session->isInterventionKind()
            ? CarePath::INTERVENTION
            : ($session instanceof ScheduleSession && $session->kind === ScheduleSession::KIND_MSK
                ? CarePath::MSK
                : CarePath::VISIT);
    }

    private function branchOf(Booking $booking): ?string
    {
        if (filled($booking->care_branch)) {
            return $booking->care_branch;
        }

        $origin = $booking->careOrigin ?? ($booking->care_origin_id ? Booking::query()->find($booking->care_origin_id) : null);

        return $origin?->care_branch;
    }

    private function openLinkedOfKind(Booking $booking, string $kind): ?Booking
    {
        $originId = $this->originId($booking);
        $date = $booking->booking_date?->toDateString();

        $query = Booking::query()
            ->with('bookable')
            ->where('bookable_type', ScheduleSession::class)
            ->where(function ($q) use ($originId, $booking): void {
                    $q->where('care_origin_id', $originId)
                    ->orWhere('related_booking_id', $booking->id)
                    ->orWhere('id', $originId);
            });

        if ($date !== null) {
            $query->where('booking_date', $date);
        }

        return $query->get()->first(function (Booking $row) use ($kind): bool {
            if (in_array($row->status, ['cancelled', 'no_show'], true)) {
                return false;
            }

            $session = $row->bookable;

            return $session instanceof ScheduleSession && $session->kind === $kind;
        });
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
