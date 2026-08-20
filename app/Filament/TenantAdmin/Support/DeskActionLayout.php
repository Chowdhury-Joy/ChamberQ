<?php

namespace App\Filament\TenantAdmin\Support;

use App\Models\Booking;
use App\Models\Doctor;
use App\Models\LiveSession;
use App\Models\ScheduleSession;
use App\Models\User;
use App\Services\MedicineService;
use App\Support\StaffDeskJobs;
use Carbon\Carbon;

/**
 * Which desk buttons sit in the open (max two) vs under More.
 *
 * Live Queue is the counter while a sitting runs. Daily Roster is the
 * opening board before Start and the leftover list after. Collect fee is
 * after checkup unless the chamber turns on check-in fee.
 */
final class DeskActionLayout
{
    public const SURFACE_ROSTER = 'roster';

    public const SURFACE_QUEUE = 'queue';

    public const SLOT_PRIMARY = 'primary';

    public const SLOT_MORE = 'more';

    public const PHASE_COUNTER = 'counter';

    public const PHASE_OPENING = 'opening';

    public const PHASE_RUNNING = 'running';

    public const PHASE_CLOSING = 'closing';

    public const KEY_FOLLOW_UP = 'toggleSeenBeforeSoftware';

    public const KEY_SCAN = 'scanPapers';

    public const KEY_CALL = 'call';

    public const KEY_ARRIVED = 'arrived';

    public const KEY_NO_SHOW = 'noShow';

    public const KEY_COMPLETE = 'complete';

    public const KEY_ENTER_RX = 'enterPrescription';

    public const KEY_VITALS = 'recordVitals';

    public const KEY_BOOK_INTERVENTION = 'sendToIntervention';

    public const KEY_MOVE_INTERVENTION = 'moveIntervention';

    public const KEY_PREPPED = 'markPrepped';

    public const KEY_CALL_DOCTOR = 'callDoctorForProcedure';

    public const KEY_PROCEDURE_DONE = 'procedureDone';

    public const KEY_COUNSELING = 'sendToCounseling';

    public const KEY_SEND_MSK = 'sendToMsk';

    public const KEY_SEND_REPORT = 'sendToReport';

    public const KEY_COLLECT_FEE = 'collectFee';

    public const KEY_PRINT_RECEIPT = 'printFeeReceipt';

    public const KEY_REPEAT = 'repeatSerial';

    public const KEY_CANCEL_REPEAT = 'cancelRepeatRemainder';

    public const KEY_CALL_NOW = 'callNow';

    public const KEY_REINSTATE = 'reinstate';

    public const KEY_REVIEW_WHATSAPP = 'askReviewWhatsapp';

    public const KEY_REVIEW_SMS = 'askReviewSms';

    public const KEY_SERIAL_WHATSAPP = 'confirmSerialWhatsapp';

    public const KEY_SERIAL_SMS = 'confirmSerialSms';

    public static function actionName(string $key, string $slot): string
    {
        return $slot === self::SLOT_MORE ? $key : $key.'Primary';
    }

    public static function shows(Booking $record, string $key, string $slot, string $surface): bool
    {
        $isPrimary = in_array($key, self::primaryKeys($record, $surface), true);

        return $slot === self::SLOT_PRIMARY ? $isPrimary : ! $isPrimary;
    }

    /**
     * At most two keys — the third visible control is always More.
     *
     * @return list<string>
     */
    public static function primaryKeys(Booking $record, string $surface): array
    {
        $ordered = [];

        if (self::canRecordVitals($record) && self::needsOutdoorVitals($record)) {
            $ordered[] = self::KEY_VITALS;
        }

        foreach ([self::KEY_PREPPED, self::KEY_CALL_DOCTOR, self::KEY_PROCEDURE_DONE] as $step) {
            if (self::canAdvanceProcedure($record, $step)) {
                $ordered[] = $step;
                break;
            }
        }

        if (self::feeIsPrimary($record)) {
            $ordered[] = self::KEY_COLLECT_FEE;
        }

        if ($surface === self::SURFACE_QUEUE) {
            if (self::canCallNow($record)) {
                $ordered[] = self::KEY_CALL_NOW;
            }
            if (self::canReinstate($record)) {
                $ordered[] = self::KEY_REINSTATE;
            }

            return array_values(array_slice(array_unique($ordered), 0, 2));
        }

        $phase = self::phaseFor($record);

        if ($phase === self::PHASE_OPENING) {
            return array_values(array_slice(array_unique($ordered), 0, 2));
        }

        if ($phase === self::PHASE_RUNNING) {
            if (self::canCallOnRoster($record) && ! self::needsOutdoorVitals($record)) {
                $ordered[] = self::KEY_CALL;
            }
            if (self::canComplete($record) && in_array($record->status, ['called', 'in_chamber'], true)) {
                $ordered[] = self::KEY_COMPLETE;
            }

            return array_values(array_slice(array_unique($ordered), 0, 2));
        }

        if ($phase === self::PHASE_CLOSING) {
            if (self::canCallOnRoster($record)) {
                $ordered[] = self::KEY_CALL;
            }
            if (self::canComplete($record)) {
                $ordered[] = self::KEY_COMPLETE;
            }
            if (self::canEnterPrescription($record)) {
                $ordered[] = self::KEY_ENTER_RX;
            }

            return array_values(array_slice(array_unique($ordered), 0, 2));
        }

        // No live queue: Roster is the counter all day.
        if (self::canArrived($record)) {
            $ordered[] = self::KEY_ARRIVED;
        }
        if (self::canCallOnRoster($record)) {
            $ordered[] = self::KEY_CALL;
        }
        if (self::canComplete($record) && in_array($record->status, ['waiting', 'called', 'in_chamber'], true)) {
            $ordered[] = self::KEY_COMPLETE;
        }

        return array_values(array_slice(array_unique($ordered), 0, 2));
    }

    public static function phaseFor(Booking $record): string
    {
        if (! (tenant()?->hasLiveQueue() ?? false)) {
            return self::PHASE_COUNTER;
        }

        $status = self::liveStatusFor($record);

        if ($status === null || in_array($status, ['scheduled', 'delayed'], true)) {
            return self::PHASE_OPENING;
        }

        if (in_array($status, ['active', 'paused'], true)) {
            return self::PHASE_RUNNING;
        }

        return self::PHASE_CLOSING;
    }

    public static function feeIsDue(Booking $record): bool
    {
        if ($record->cashEntry === null) {
            return true;
        }

        if ($record->cashEntry->isWaived() || $record->cashEntry->amount < 1) {
            return false;
        }

        return app(\App\Services\PatientFeeRefundService::class)->hasOpenRefund($record);
    }

    public static function collectsFeeAtCheckin(Booking $record): bool
    {
        $record->loadMissing('bookable.doctor');
        $doctor = Doctor::resolveForBooking($record);

        if ($doctor) {
            return $doctor->collectsFeeAtCheckin();
        }

        return (bool) (tenant()?->collectsFeeAtCheckin());
    }

    public static function canCollectFee(Booking $record): bool
    {
        $user = auth()->user();
        if (! $user instanceof User || ! StaffDeskJobs::canCollectFee($user)) {
            return false;
        }

        if (in_array($record->status, ['cancelled', 'no_show'], true)) {
            return false;
        }

        return ! self::shouldHideCollectFee($record);
    }

    public static function shouldHideCollectFee(Booking $booking): bool
    {
        if (! tenant()?->hasStations()) {
            return false;
        }

        if ($booking->bookable_type !== ScheduleSession::class) {
            return false;
        }

        $session = $booking->bookable;

        return $session instanceof ScheduleSession
            && \App\Services\PracticeRules::bookingIsFeeExempt($booking);
    }

    public static function canRecordVitals(Booking $record): bool
    {
        $user = auth()->user();

        return (tenant()?->hasStations() ?? false)
            && $user instanceof User
            && StaffDeskJobs::canRecordPrep($user)
            && $record->status === 'waiting';
    }

    public static function needsOutdoorVitals(Booking $record): bool
    {
        if (! self::canRecordVitals($record)) {
            return false;
        }

        return ! ($record->visitRecord?->hasOutdoorVitals() ?? false);
    }

    public static function canCallOnRoster(Booking $record): bool
    {
        $user = auth()->user();

        return (tenant()?->hasLiveQueue() ?? false)
            && $user instanceof User
            && StaffDeskJobs::canRunQueue($user)
            && $record->status === 'waiting';
    }

    public static function canArrived(Booking $record): bool
    {
        $user = auth()->user();

        return ! (tenant()?->hasLiveQueue() ?? true)
            && $user instanceof User
            && (StaffDeskJobs::canRunQueue($user)
                || (! (tenant()?->hasLiveQueue() ?? true) && $user->canManageQueue()))
            && $record->status === 'waiting';
    }

    public static function canComplete(Booking $record): bool
    {
        if (! in_array($record->status, ['waiting', 'in_chamber', 'called'], true)) {
            return false;
        }

        return self::canCompleteFromRoster($record);
    }

    public static function canCompleteFromRoster(Booking $booking): bool
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return false;
        }

        if (tenant()?->hasLiveQueue()) {
            return StaffDeskJobs::canRunQueue($user);
        }

        return $user->isStaff()
            ? StaffDeskJobs::hasJob($user, StaffDeskJobs::JOB_QUEUE) && $user->canManageQueue()
            : $user->canManageQueue();
    }

    public static function canEnterPrescription(Booking $booking): bool
    {
        if (! tenant()?->hasPrescription()) {
            return false;
        }

        $user = auth()->user();

        if (! $user?->isStaff() || $booking->status !== 'completed') {
            return false;
        }

        return $user->canEnterPrescriptionFor(
            app(MedicineService::class)->resolvePrescribingDoctor($booking)
        );
    }

    public static function canAdvanceProcedure(Booking $booking, string $key): bool
    {
        $expected = match ($key) {
            self::KEY_PREPPED => Booking::PROCEDURE_LOGGED,
            self::KEY_CALL_DOCTOR => Booking::PROCEDURE_PREPPED,
            self::KEY_PROCEDURE_DONE => Booking::PROCEDURE_DOCTOR_CALLED,
            default => null,
        };

        if ($expected === null) {
            return false;
        }

        if (! tenant()?->hasStations()) {
            return false;
        }

        $user = auth()->user();
        if (! $user instanceof User || ! StaffDeskJobs::canRecordPrep($user)) {
            return false;
        }

        if ($booking->bookable_type !== ScheduleSession::class) {
            return false;
        }

        $session = $booking->bookable;

        return $session instanceof ScheduleSession
            && $session->isInterventionKind()
            && ($booking->procedure_status ?? Booking::PROCEDURE_LOGGED) === $expected
            && ! in_array($booking->status, ['cancelled', 'no_show', 'completed'], true);
    }

    public static function canCallNow(Booking $record): bool
    {
        if (! in_array($record->status, ['waiting', 'skipped'], true)) {
            return false;
        }

        $user = auth()->user();
        if (! $user instanceof User || ! StaffDeskJobs::canRunQueue($user)) {
            return false;
        }

        $live = self::liveSessionFor($record);
        if ($live === null || $live->status !== 'active') {
            return false;
        }

        return $live->currentBooking?->status !== 'in_chamber';
    }

    public static function canReinstate(Booking $record): bool
    {
        return $record->status === 'no_show';
    }

    public static function feeIsPrimaryOnCard(Booking $record): bool
    {
        return self::feeIsPrimary($record);
    }

    private static function feeIsPrimary(Booking $record): bool
    {
        if (! self::canCollectFee($record) || ! self::feeIsDue($record)) {
            return false;
        }

        if ($record->status === 'completed') {
            return true;
        }

        if (self::collectsFeeAtCheckin($record)
            && in_array($record->status, ['waiting', 'called', 'skipped'], true)) {
            return true;
        }

        return false;
    }

    private static function liveStatusFor(Booking $record): ?string
    {
        return self::liveSessionFor($record)?->status;
    }

    private static function liveSessionFor(Booking $record): ?LiveSession
    {
        if ($record->bookable_type !== ScheduleSession::class) {
            return null;
        }

        $sessionId = (int) $record->bookable_id;
        $date = Carbon::today()->toDateString();
        $cacheKey = 'desk-live-session-'.$sessionId.'-'.$date;

        if (request()->attributes->has($cacheKey)) {
            $cached = request()->attributes->get($cacheKey);

            return $cached instanceof LiveSession ? $cached : null;
        }

        $live = LiveSession::query()
            ->with('currentBooking')
            ->where('schedule_session_id', $sessionId)
            ->where('session_date', $date)
            ->first();

        request()->attributes->set($cacheKey, $live);

        return $live;
    }
}
