<?php

namespace App\Services;

use App\Exceptions\OfflineQueueConflictException;
use App\Models\Booking;
use App\Models\LiveSession;
use App\Models\OfflineQueueEvent;
use App\Models\ScheduleSession;
use App\Models\User;
use App\Models\VisitRecord;
use App\Support\BdPhone;
use App\Support\StaffDeskJobs;
use App\Support\StaffDeskScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Applies work staff did on this computer while the line was down.
 *
 * Allowed: save a prescription onto an existing booking; record a visiting /
 * camp walk-in; replay Call next / arrived / skip / complete-without-advance
 * from the machine that tapped (conflict stops the rest). Walk-in onto the
 * live queue and SMS stay frozen until the line is back.
 */
class OfflineSyncService
{
    public const TYPE_RX_SAVE = 'rx_save';

    public const TYPE_VISITING_VISIT = 'visiting_visit';

    public const TYPE_QUEUE_CALL_NEXT = 'queue_call_next';

    public const TYPE_QUEUE_PATIENT_ARRIVED = 'queue_patient_arrived';

    public const TYPE_QUEUE_SKIP = 'queue_skip';

    public const TYPE_QUEUE_COMPLETE_WITHOUT_ADVANCE = 'queue_complete_without_advance';

    public const MAX_ITEMS = 40;

    public function __construct(
        private readonly VisitRecordService $visitRecordService,
        private readonly LiveSessionService $liveSessionService,
        private readonly PatientService $patientService,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array{id: string, ok: bool, error?: string, booking_id?: string}>
     */
    public function apply(User $user, array $items): array
    {
        $results = [];

        foreach (array_slice($items, 0, self::MAX_ITEMS) as $item) {
            $id = (string) ($item['id'] ?? '');
            $type = (string) ($item['type'] ?? '');

            try {
                if ($this->isQueueEvent($type)) {
                    if (! StaffDeskJobs::canRunQueue($user) || ! $user->belongsToCurrentTenant()) {
                        throw ValidationException::withMessages([
                            'type' => 'You cannot replay queue actions for this chamber.',
                        ]);
                    }

                    $this->applyQueueEvent($user, $item);
                    $results[] = ['id' => $id, 'ok' => true];
                } else {
                    if (! $user->canRecordVisitNotes() || ! $user->belongsToCurrentTenant()) {
                        throw ValidationException::withMessages([
                            'type' => 'You cannot upload offline visits for this chamber.',
                        ]);
                    }

                    $bookingId = match ($type) {
                        self::TYPE_RX_SAVE => $this->applyRxSave($user, $item),
                        self::TYPE_VISITING_VISIT => $this->applyVisitingVisit($user, $item),
                        default => throw ValidationException::withMessages([
                            'type' => 'Unknown offline item.',
                        ]),
                    };

                    $results[] = ['id' => $id, 'ok' => true, 'booking_id' => $bookingId];
                }
            } catch (OfflineQueueConflictException $e) {
                $results[] = [
                    'id' => $id,
                    'ok' => false,
                    'error' => $e->getMessage(),
                    'conflict' => true,
                    'halt' => true,
                ];

                break;
            } catch (ValidationException $e) {
                $results[] = [
                    'id' => $id,
                    'ok' => false,
                    'error' => collect($e->errors())->flatten()->first() ?: 'Could not save.',
                ];
            } catch (\Throwable $e) {
                report($e);
                $results[] = [
                    'id' => $id,
                    'ok' => false,
                    'error' => 'Could not save this visit. It is still on this computer.',
                ];
            }
        }

        return $results;
    }

    private function isQueueEvent(string $type): bool
    {
        return in_array($type, [
            self::TYPE_QUEUE_CALL_NEXT,
            self::TYPE_QUEUE_PATIENT_ARRIVED,
            self::TYPE_QUEUE_SKIP,
            self::TYPE_QUEUE_COMPLETE_WITHOUT_ADVANCE,
        ], true);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function applyQueueEvent(User $user, array $item): void
    {
        $syncId = (string) ($item['id'] ?? '');
        $liveSessionId = (int) ($item['live_session_id'] ?? 0);

        if ($syncId === '' || $liveSessionId < 1) {
            throw ValidationException::withMessages([
                'live_session_id' => 'Missing queue session.',
            ]);
        }

        DB::transaction(function () use ($user, $item, $syncId, $liveSessionId): void {
            if (OfflineQueueEvent::query()->whereKey($syncId)->lockForUpdate()->exists()) {
                return;
            }

            $liveSession = LiveSession::query()->with('scheduleSession')->whereKey($liveSessionId)->lockForUpdate()->first();

            if (! $liveSession) {
                throw ValidationException::withMessages([
                    'live_session_id' => 'That session is no longer live.',
                ]);
            }

            if ($liveSession->scheduleSession) {
                StaffDeskScope::assertCanAccessSession($user, $liveSession->scheduleSession);
            }

            $expected = filled($item['expected_current_booking_id'] ?? null)
                ? (string) $item['expected_current_booking_id']
                : null;

            if ($liveSession->current_booking_id !== $expected) {
                throw new OfflineQueueConflictException(
                    'Refresh — the line moved elsewhere.',
                );
            }

            $type = (string) ($item['type'] ?? '');
            $current = $liveSession->currentBooking;

            if ($type === self::TYPE_QUEUE_PATIENT_ARRIVED
                && (! $current || $current->status !== 'called')) {
                throw new OfflineQueueConflictException(
                    'Refresh — that visit is no longer waiting to arrive.',
                );
            }

            if ($type === self::TYPE_QUEUE_SKIP
                && $current
                && in_array($current->status, ['in_chamber', 'completed', 'no_show'], true)) {
                throw new OfflineQueueConflictException(
                    'Refresh — that patient is already with the doctor or finished.',
                );
            }

            match ($type) {
                self::TYPE_QUEUE_CALL_NEXT => $this->liveSessionService->callNextPatient($liveSession),
                self::TYPE_QUEUE_PATIENT_ARRIVED => $this->liveSessionService->patientArrived($liveSession),
                self::TYPE_QUEUE_SKIP => $this->liveSessionService->skipPatient($liveSession),
                self::TYPE_QUEUE_COMPLETE_WITHOUT_ADVANCE => $this->liveSessionService->completeCurrentPatientWithoutAdvancing($liveSession),
                default => throw ValidationException::withMessages([
                    'type' => 'Unknown queue action.',
                ]),
            };

            OfflineQueueEvent::query()->create([
                'id' => $syncId,
                'tenant_id' => (string) tenant('id'),
                'live_session_id' => $liveSession->id,
                'event_type' => $type,
                'applied_at' => now(),
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function applyRxSave(User $doctor, array $item): string
    {
        $booking = Booking::query()->find($item['booking_id'] ?? null);

        if (! $booking) {
            throw ValidationException::withMessages(['booking_id' => 'That booking is no longer on this chamber.']);
        }

        if (in_array($booking->status, ['cancelled', 'no_show'], true)) {
            throw ValidationException::withMessages(['booking_id' => 'That booking was cancelled.']);
        }

        StaffDeskScope::assertCanAccessBooking($doctor, $booking);

        $this->saveNotes($doctor, $booking, $item);

        return $booking->id;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function applyVisitingVisit(User $doctor, array $item): string
    {
        $session = ScheduleSession::query()->find($item['schedule_session_id'] ?? null);

        if (! $session) {
            throw ValidationException::withMessages(['schedule_session_id' => 'Pick a chamber session for this visit.']);
        }

        StaffDeskScope::assertCanAccessSession($doctor, $session);

        $name = trim((string) ($item['patient_name'] ?? ''));
        $phone = BdPhone::normalize((string) ($item['patient_phone'] ?? ''));

        if ($name === '' || ! BdPhone::isValid($phone)) {
            throw ValidationException::withMessages(['patient_phone' => 'Need a name and a valid Bangladeshi mobile.']);
        }

        $date = filled($item['visit_date'] ?? null)
            ? (string) $item['visit_date']
            : now()->toDateString();

        $booking = $this->findOrCreateVisitingBooking($session, $name, $phone, $date);
        $this->saveNotes($doctor, $booking, $item);

        $queueOwns = in_array($booking->status, ['waiting', 'called', 'in_chamber'], true)
            && $booking->liveSession();

        if (! $queueOwns && $booking->status !== 'completed') {
            $this->liveSessionService->completeBooking($booking->fresh());
        }

        return $booking->id;
    }

    /**
     * A camp visit is not a public serial. Skip capacity, day-of-week and
     * "session already ended" — the consult already happened on this laptop.
     */
    private function findOrCreateVisitingBooking(
        ScheduleSession $session,
        string $name,
        string $phone,
        string $date,
    ): Booking {
        return DB::transaction(function () use ($session, $name, $phone, $date): Booking {
            $locked = ScheduleSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();
            $patient = $this->patientService->resolveForBooking($phone, $name);

            $existing = Booking::query()
                ->where('bookable_type', ScheduleSession::class)
                ->where('bookable_id', $locked->id)
                ->where('booking_date', $date)
                ->where('patient_id', $patient->id)
                ->whereNotIn('status', ['cancelled'])
                ->orderByDesc('created_at')
                ->first();

            if ($existing) {
                return $existing;
            }

            $maxSerial = Booking::query()
                ->where('bookable_type', ScheduleSession::class)
                ->where('bookable_id', $locked->id)
                ->where('booking_date', $date)
                ->max('serial_number');

            return Booking::create([
                'bookable_type' => ScheduleSession::class,
                'bookable_id' => $locked->id,
                'booking_date' => $date,
                'patient_id' => $patient->id,
                'patient_name' => $patient->name,
                'patient_phone' => $phone,
                'serial_number' => ((int) $maxSerial) + 1,
                'status' => 'waiting',
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function saveNotes(User $doctor, Booking $booking, array $item): void
    {
        $syncId = filled($item['id'] ?? null) ? (string) $item['id'] : null;

        if ($syncId) {
            $already = VisitRecord::query()->where('offline_sync_id', $syncId)->first();

            if ($already) {
                return;
            }
        }

        $data = is_array($item['data'] ?? null) ? $item['data'] : [];
        $record = $this->visitRecordService->saveForCompletedBooking($booking, $doctor, $data);

        if ($record && $syncId) {
            $record->update(['offline_sync_id' => $syncId]);
        }
    }
}
