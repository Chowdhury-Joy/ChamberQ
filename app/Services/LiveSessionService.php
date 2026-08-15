<?php

namespace App\Services;

use App\Jobs\SendDoctorLateNotices;
use App\Jobs\SendQueueApproachPushes;
use App\Models\Booking;
use App\Models\Doctor;
use App\Models\LiveSession;
use App\Models\ScheduleSession;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LiveSessionService
{
    /**
     * Pessimistic lock — two staff pressing Call at once cannot both overwrite current_booking_id.
     */
    private function lockSession(LiveSession $liveSession): LiveSession
    {
        return LiveSession::where('id', $liveSession->id)->lockForUpdate()->firstOrFail();
    }

    /**
     * Start a new live session for a schedule session today
     */
    public function startSession(ScheduleSession $scheduleSession): LiveSession
    {
        return DB::transaction(function () use ($scheduleSession) {
            // toDateString(), not the Carbon: these attributes are the WHERE
            // clause as well as the insert payload, and a Carbon binds as
            // 'Y-m-d H:i:s' — which never matches a date-only column, so the
            // lookup misses and the insert trips the unique index.
            $today = Carbon::today()->toDateString();

            $liveSession = LiveSession::firstOrCreate([
                'tenant_id' => tenant('id'),
                'schedule_session_id' => $scheduleSession->id,
                'session_date' => $today,
            ], [
                'status' => 'active',
                'started_at' => now(),
            ]);

            $liveSession = $this->lockSession($liveSession);

            if ($liveSession->status === 'scheduled' || $liveSession->status === 'delayed') {
                $liveSession->update([
                    'status' => 'active',
                    'started_at' => now(),
                ]);
            }

            // Call first patient if none is called
            if (!$liveSession->current_booking_id) {
                $this->advanceQueue($liveSession);
            }

            $fresh = $liveSession->fresh();
            $this->dispatchApproachPushes($fresh);

            return $fresh;
        });
    }

    /**
     * Call the next available patient in the queue
     */
    public function callNextPatient(LiveSession $liveSession): ?Booking
    {
        $called = DB::transaction(function () use ($liveSession) {
            $liveSession = $this->lockSession($liveSession);

            return $this->advanceQueue($liveSession);
        });

        $this->dispatchApproachPushes($liveSession);

        return $called;
    }

    /**
     * Queue advance logic — caller must already hold a lock on the live session row.
     */
    private function advanceQueue(LiveSession $liveSession): ?Booking
    {
            $currentBooking = $liveSession->currentBooking;
            
            // First check if any skipped patients are due for retry based on serial position
            $retryQuery = $liveSession->bookings()
                ->where('status', 'skipped')
                ->whereNotNull('retry_queue_position');
                
            if ($currentBooking) {
                $retryQuery->where('retry_queue_position', '<=', $currentBooking->serial_number + 1);
            }
            
            $retryBooking = $retryQuery->orderBy('retry_queue_position')->first();
                
            if ($retryBooking) {
                return $this->setAsCurrent($liveSession, $retryBooking);
            }

            // Normal queue: next waiting patient
            $nextBookingQuery = $liveSession->bookings()
                ->where('status', 'waiting');
                
            if ($currentBooking) {
                $nextBookingQuery->where('serial_number', '>', $currentBooking->serial_number);
            }
            
            $nextBooking = $nextBookingQuery->orderBy('serial_number')->first();

            if ($nextBooking) {
                return $this->setAsCurrent($liveSession, $nextBooking);
            }
            
            // If the normal queue is empty, but there are still skipped patients waiting for their retry position
            // (e.g. they were skipped near the end of the queue and their retry position is higher than the max serial)
            // we should just call the next available skipped patient.
            $anyRemainingRetry = $liveSession->bookings()
                ->where('status', 'skipped')
                ->whereNotNull('retry_queue_position')
                ->orderBy('retry_queue_position')
                ->first();
                
            if ($anyRemainingRetry) {
                return $this->setAsCurrent($liveSession, $anyRemainingRetry);
            }

            // If no normal patient and no retries, just clear current
            $liveSession->update([
                'current_booking_id' => null,
                'current_called_at' => null,
            ]);

            return null;
    }

    private function setAsCurrent(LiveSession $liveSession, Booking $booking): Booking
    {
        $now = now();
        $liveSession->update([
            'current_booking_id' => $booking->id,
            'current_called_at' => $now,
        ]);

        $booking->update([
            'status' => 'called',
            'called_at' => $now,
            // The retry slot has now been consumed — leaving it set kept a stale
            // "back in queue after #N" hint on patients who had already been called.
            'retry_queue_position' => null,
        ]);

        return $booking;
    }

    /**
     * Call one specific patient out of turn (staff tap "Call now" on a row).
     *
     * Never interrupts a consult in progress, and a patient who was merely
     * called — not yet arrived — returns to `waiting` without a skip strike,
     * because being jumped is not their fault.
     */
    public function callSpecificPatient(LiveSession $liveSession, Booking $booking): ?Booking
    {
        return DB::transaction(function () use ($liveSession, $booking) {
            $liveSession = $this->lockSession($liveSession);
            $booking = $booking->fresh();

            if (! $booking || ! in_array($booking->status, ['waiting', 'skipped'], true)) {
                return null;
            }

            $current = $liveSession->currentBooking;

            if ($current && $current->status === 'in_chamber') {
                return null;
            }

            if ($current && $current->status === 'called' && $current->id !== $booking->id) {
                $current->update([
                    'status' => 'waiting',
                    'called_at' => null,
                ]);
            }

            $called = $this->setAsCurrent($liveSession, $booking);
            $this->dispatchApproachPushes($liveSession);

            return $called;
        });
    }

    public function patientArrived(LiveSession $liveSession)
    {
        DB::transaction(function () use ($liveSession) {
            $liveSession = $this->lockSession($liveSession);
            $booking = $liveSession->currentBooking;

            if ($booking) {
                $booking->update([
                    'status' => 'in_chamber',
                    'in_chamber_at' => now(),
                ]);
            }
        });
    }

    public function completeCurrentPatient(LiveSession $liveSession)
    {
        return DB::transaction(function () use ($liveSession) {
            $liveSession = $this->lockSession($liveSession);
            $booking = $liveSession->currentBooking;
            if ($booking) {
                $booking->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);
            }

            $next = $this->advanceQueue($liveSession);
            $this->dispatchApproachPushes($liveSession);

            return $next;
        });
    }

    /**
     * Mark the current patient completed WITHOUT advancing the queue.
     *
     * The prescription is written and handed over while the patient is still
     * in the room, so `current_booking_id` stays pointed at the now-completed
     * booking until the queue runner explicitly calls the next patient. Callers
     * must follow up with `callNextPatient()` when the room is actually clear.
     */
    public function completeCurrentPatientWithoutAdvancing(LiveSession $liveSession): ?Booking
    {
        return DB::transaction(function () use ($liveSession) {
            $liveSession = $this->lockSession($liveSession);
            $booking = $liveSession->currentBooking;

            if ($booking) {
                $booking->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);
            }

            $fresh = $booking?->fresh();
            $this->dispatchApproachPushes($liveSession);

            return $fresh;
        });
    }

    /**
     * Roster shortcut: move a specific waiting patient into the chamber and
     * sync live-session pointers (Daily Roster must not bypass this).
     *
     * Refuses while someone else is already `in_chamber`, matching
     * `callSpecificPatient()` — a consult in progress is never interrupted.
     * Without this the roster could silently take the doctor's current patient
     * off the outdoor screen and off their own ticket mid-consultation.
     *
     * @return bool False when another patient is mid-consult and nothing moved.
     */
    public function bringBookingToChamber(Booking $booking): bool
    {
        if ($booking->bookable_type !== ScheduleSession::class) {
            $booking->update([
                'status' => 'in_chamber',
                'in_chamber_at' => now(),
            ]);

            return true;
        }

        return DB::transaction(function () use ($booking) {
            $now = now();
            $liveSession = LiveSession::firstOrCreate([
                'tenant_id' => tenant('id'),
                'schedule_session_id' => $booking->bookable_id,
                // See startSession(): must be a date string, not a Carbon.
                'session_date' => $booking->booking_date->toDateString(),
            ], [
                'status' => 'active',
                'started_at' => $now,
            ]);

            $liveSession = $this->lockSession($liveSession);

            $current = $liveSession->currentBooking;

            if ($current && $current->id !== $booking->id && $current->status === 'in_chamber') {
                return false;
            }

            if (in_array($liveSession->status, ['scheduled', 'delayed'], true)) {
                $liveSession->update([
                    'status' => 'active',
                    'started_at' => $liveSession->started_at ?? $now,
                ]);
            }

            // Whoever was merely *called* goes back to waiting with no skip
            // strike — being jumped is not their fault (same rule as
            // callSpecificPatient()).
            if ($current && $current->id !== $booking->id && $current->status === 'called') {
                $current->update([
                    'status' => 'waiting',
                    'called_at' => null,
                ]);
            }

            $liveSession->update([
                'current_booking_id' => $booking->id,
                'current_called_at' => $now,
            ]);

            $booking->update([
                'status' => 'in_chamber',
                'called_at' => $booking->called_at ?? $now,
                'in_chamber_at' => $now,
            ]);

            $this->dispatchApproachPushes($liveSession);

            return true;
        });
    }

    /**
     * Complete a booking from roster or live queue, keeping session state in sync.
     */
    public function completeBooking(Booking $booking): void
    {
        DB::transaction(function () use ($booking) {
            $liveSession = $booking->liveSession();

            if ($liveSession) {
                $liveSession = $this->lockSession($liveSession);

                if ($liveSession->current_booking_id === $booking->id) {
                    $booking = $liveSession->currentBooking;

                    if ($booking) {
                        $booking->update([
                            'status' => 'completed',
                            'completed_at' => now(),
                        ]);
                    }

                    $this->advanceQueue($liveSession);
                    $this->dispatchApproachPushes($liveSession);

                    return;
                }
            }

            $booking->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            if ($liveSession) {
                $this->dispatchApproachPushes($liveSession);
            }
        });
    }

    public function skipPatient(LiveSession $liveSession)
    {
        return DB::transaction(function () use ($liveSession) {
            $liveSession = $this->lockSession($liveSession);
            $booking = $liveSession->currentBooking;
            if ($booking) {
                $skipCount = $booking->skip_count + 1;

                // Two skips, then out. `> 2` looks off by one but is not:
                // $skipCount is the count *after* this tap, so a patient on 2
                // strikes becomes 3 here and is marked no-show — which is what
                // the button already promised ("No response — mark no-show"
                // once skip_count >= 2 in live-queue-control.blade.php).
                if ($skipCount > 2) {
                    // Terminal state
                    $booking->update([
                        'status' => 'no_show',
                        'skip_count' => $skipCount,
                    ]);
                } else {
                    // Retry later
                    $booking->update([
                        'status' => 'skipped',
                        'skip_count' => $skipCount,
                        'retry_queue_position' => $booking->serial_number + 2, // Re-insert at current + 2
                    ]);
                }
            }

            $next = $this->advanceQueue($liveSession);
            $this->dispatchApproachPushes($liveSession);

            return $next;
        });
    }

    public function reinstatePatient(Booking $booking)
    {
        $liveSession = $booking->liveSession();
        if (!$liveSession) return;

        // Same lock/transaction as every other queue mutation: this reads the
        // current booking's serial and writes a retry position derived from it,
        // which is exactly the field advanceQueue() is choosing from.
        DB::transaction(function () use ($liveSession, $booking) {
            $liveSession = $this->lockSession($liveSession);

            $current = $liveSession->currentBooking;
            $currentSerial = $current ? $current->serial_number : $booking->serial_number;

            $booking->update([
                'status' => 'skipped', // Using skipped so it gets picked up by retry logic
                'skip_count' => 0, // Reset so they get 2 more chances
                'retry_queue_position' => $currentSerial + 2,
            ]);

            $this->dispatchApproachPushes($liveSession);
        });
    }

    /**
     * Bookings that ending the session right now would cancel.
     *
     * Read by the confirmation modal so the queue runner is told how many
     * people they are about to turn away, by name, before they commit.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Booking>
     */
    public function bookingsEndSessionWouldCancel(LiveSession $liveSession)
    {
        return $liveSession->bookings()
            ->whereNotIn('status', ['completed', 'cancelled', 'no_show', 'in_chamber'])
            ->orderBy('serial_number')
            ->get();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Booking> The bookings this cancelled,
     *         so the caller can offer the patients a notification. Cancelling
     *         someone's appointment without telling them is the whole failure
     *         this return value exists to prevent — see the slot-block flow,
     *         which has had a WhatsApp notify list since vacation mode shipped.
     */
    public function endSession(LiveSession $liveSession, string $reason = 'Session ended')
    {
        return DB::transaction(function () use ($liveSession, $reason) {
            $liveSession = $this->lockSession($liveSession);

            // Captured before the update: once they are `cancelled` the same
            // query no longer matches them.
            $toCancel = $this->bookingsEndSessionWouldCancel($liveSession);

            // Fatima is already with the doctor — do not cancel mid-consult.
            // Mark her completed; cancel everyone still waiting / called / skipped.
            $liveSession->bookings()
                ->where('status', 'in_chamber')
                ->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);

            $liveSession->bookings()
                ->whereNotIn('status', ['completed', 'cancelled', 'no_show', 'in_chamber'])
                ->update([
                    'status' => 'cancelled',
                    'cancellation_reason' => $reason,
                    'cancelled_at' => now(),
                ]);

            $liveSession->update([
                'status' => 'completed',
                'completed_at' => now(),
                'current_booking_id' => null,
                'current_called_at' => null,
            ]);

            return $toCancel;
        });
    }

    /**
     * Mark the session delayed, then auto-SMS waiting patients when that
     * doctor's "doctor late — SMS" preference is on.
     *
     * @return \Illuminate\Support\Collection<int, Booking> Bookings still in
     *         the queue (for optional WhatsApp hand-off on the control panel).
     */
    public function markDelay(LiveSession $liveSession, int $minutes)
    {
        DB::transaction(function () use ($liveSession, $minutes) {
            $liveSession = $this->lockSession($liveSession);

            $liveSession->update([
                'status' => 'delayed',
                'delay_minutes' => $minutes,
            ]);
        });

        $liveSession->refresh();
        $liveSession->loadMissing('scheduleSession.doctor');

        $bookings = $liveSession->bookings()
            ->whereIn('status', ['waiting', 'called', 'skipped'])
            ->orderBy('serial_number')
            ->get();

        $doctor = $liveSession->scheduleSession?->doctor;

        if ($doctor?->wantsSms(Doctor::NOTIFY_DOCTOR_LATE) && $bookings->isNotEmpty()) {
            // Handed off rather than looped here: each send waits up to ten
            // seconds on the gateway, so thirty waiting patients could hold the
            // queue screen for minutes at the exact moment staff need it to
            // respond. See SendDoctorLateNotices for why this is
            // after-response and not a queued job.
            SendDoctorLateNotices::dispatch(
                (string) tenant('id'),
                $bookings->pluck('id')->all(),
                $minutes,
            )->afterResponse();
        }

        return $bookings;
    }

    /**
     * Cancel the whole session because the doctor is not coming.
     *
     * Mirrors `endSession()` on purpose — same in-chamber rule, same return
     * value. It did neither before: it cancelled every non-terminal booking
     * *including* the patient who was `in_chamber`, so a consult that had
     * actually happened (and any notes or prescription written during it) was
     * recorded as a cancelled appointment; and it returned nothing, so the
     * queue runner got no way to tell the people they had just turned away.
     * This is the path where telling them matters most — the doctor is absent,
     * so every one of them would otherwise travel to the chamber for nothing.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Booking> The bookings
     *         this cancelled, for the caller's notification hand-off.
     */
    public function markAbsent(LiveSession $liveSession, string $reason = 'Doctor unavailable')
    {
        return DB::transaction(function () use ($liveSession, $reason) {
            $liveSession = $this->lockSession($liveSession);

            // Captured before the update: once they are `cancelled` the same
            // query no longer matches them.
            $toCancel = $this->bookingsEndSessionWouldCancel($liveSession);

            // Already with the doctor — the visit happened, so close it rather
            // than cancelling it out from under them.
            $liveSession->bookings()
                ->where('status', 'in_chamber')
                ->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);

            $liveSession->bookings()
                ->whereNotIn('status', ['completed', 'cancelled', 'no_show', 'in_chamber'])
                ->update([
                    'status' => 'cancelled',
                    'cancellation_reason' => $reason,
                    'cancelled_at' => now(),
                ]);

            $liveSession->update([
                'status' => 'cancelled',
                'cancellation_reason' => $reason,
                'completed_at' => now(),
                'current_booking_id' => null,
                'current_called_at' => null,
            ]);

            return $toCancel;
        });
    }

    public function pauseSession(LiveSession $liveSession, string $reason, int $estimatedMinutes)
    {
        DB::transaction(function () use ($liveSession, $reason, $estimatedMinutes) {
            $liveSession = $this->lockSession($liveSession);

            $liveSession->update([
                'status' => 'paused',
                'paused_at' => now(),
                'pause_reason' => $reason,
                'estimated_pause_minutes' => $estimatedMinutes,
            ]);
        });
    }

    public function resumeSession(LiveSession $liveSession)
    {
        DB::transaction(function () use ($liveSession) {
            $liveSession = $this->lockSession($liveSession);

            // A paused session with no `paused_at` used to fall through this
            // method silently, leaving Resume as a button that does nothing and
            // a queue nobody can restart. Only the elapsed-time accounting
            // needs the timestamp; coming back to `active` does not.
            $actualPauseMinutes = $liveSession->paused_at
                ? (int) $liveSession->paused_at->diffInMinutes(now())
                : 0;

            $liveSession->update([
                'status' => 'active',
                'total_pause_minutes' => $liveSession->total_pause_minutes + $actualPauseMinutes,
                'paused_at' => null,
                'pause_reason' => null,
                'estimated_pause_minutes' => 0,
            ]);
        });
    }

    public function estimatedTimeForBooking(Booking $booking)
    {
        $liveSession = $booking->liveSession();
        if (! $liveSession) {
            return null;
        }

        $tenant = tenant();
        $model = $tenant?->eta_model ?? Tenant::ETA_SCHEDULE_GUESS;

        $actualEstimate = match ($model) {
            Tenant::ETA_LIVE_AVERAGE => $this->livePaceEstimate($booking, $liveSession, trimExtremes: false),
            Tenant::ETA_LIVE_STEADY => $this->livePaceEstimate($booking, $liveSession, trimExtremes: true),
            default => $this->scheduleGuessEstimate($booking, $liveSession),
        };

        if ($actualEstimate === null) {
            $actualEstimate = $this->scheduleGuessEstimate($booking, $liveSession);
        }

        if ($actualEstimate === null) {
            return null;
        }

        $firstN = $tenant?->first_n_patients ?? 2;
        $offsetMins = $tenant?->first_n_arrival_offset_minutes ?? 15;
        $bufferMins = $tenant?->estimated_time_buffer_minutes ?? 30;

        if ($model === Tenant::ETA_SCHEDULE_GUESS) {
            $actualEstimateOfFirstPatient = $this->scheduleSessionStart($liveSession, $booking);

            if ($booking->serial_number <= $firstN) {
                $shownTime = $actualEstimateOfFirstPatient->copy()->subMinutes($offsetMins);
            } else {
                $shownTime = $actualEstimate->copy()->subMinutes($bufferMins);
            }
        } else {
            if ($booking->serial_number <= $firstN) {
                $shownTime = $actualEstimate->copy()->subMinutes($offsetMins);
            } else {
                $shownTime = $actualEstimate->copy()->subMinutes($bufferMins);
            }
        }

        return [
            'actual_estimate' => $actualEstimate,
            'shown_time' => $shownTime,
        ];
    }

    private function scheduleSessionStart(LiveSession $liveSession, Booking $booking): Carbon
    {
        return $liveSession->effectiveStartTime();
    }

    private function scheduleGuessEstimate(Booking $booking, LiveSession $liveSession): ?Carbon
    {
        $session = $liveSession->scheduleSession;
        if (! $session) {
            return null;
        }

        $start = $this->scheduleSessionStart($liveSession, $booking);
        $avgMins = $liveSession->avgConsultationMinutes();

        return $start->copy()->addMinutes(($booking->serial_number - 1) * $avgMins);
    }

    private function livePaceEstimate(Booking $booking, LiveSession $liveSession, bool $trimExtremes): ?Carbon
    {
        $durations = $this->completedConsultDurations($liveSession);
        if ($durations === []) {
            return null;
        }

        $avgMins = $trimExtremes && count($durations) >= 3
            ? $this->trimmedAverageMinutes($durations)
            : (int) round(array_sum($durations) / count($durations));

        $ahead = $this->aheadInQueue($booking, $liveSession);

        return now()->addMinutes($ahead * max(1, $avgMins));
    }

    /** @return list<int> */
    private function completedConsultDurations(LiveSession $liveSession): array
    {
        return $liveSession->bookings()
            ->where('status', 'completed')
            ->whereNotNull('in_chamber_at')
            ->whereNotNull('completed_at')
            ->get()
            ->map(fn (Booking $booking) => max(1, (int) $booking->in_chamber_at->diffInMinutes($booking->completed_at)))
            ->values()
            ->all();
    }

    /** @param list<int> $durations */
    private function trimmedAverageMinutes(array $durations): int
    {
        sort($durations);
        array_shift($durations);
        array_pop($durations);

        if ($durations === []) {
            return 1;
        }

        return (int) round(array_sum($durations) / count($durations));
    }

    public function peopleAheadOf(Booking $booking, ?LiveSession $liveSession = null): int
    {
        $session = $liveSession ?? $booking->liveSession();

        if (! $session) {
            return 0;
        }

        return $this->aheadInQueue($booking, $session);
    }

    /**
     * Pocket buzz for subscribed tickets (two away / next / called).
     *
     * After-response, not a queued worker — same reason as SendDoctorLateNotices.
     * Open-tab banners still work when VAPID keys are missing; this job then
     * no-ops through NullWebPushSender.
     */
    private function dispatchApproachPushes(LiveSession $liveSession): void
    {
        if (! tenant()?->hasLiveQueue()) {
            return;
        }

        SendQueueApproachPushes::dispatch(
            (string) tenant('id'),
            (int) $liveSession->id,
        )->afterResponse();
    }

    private function aheadInQueue(Booking $booking, LiveSession $liveSession): int
    {
        return $liveSession->bookings()
            ->whereIn('status', ['waiting', 'called', 'in_chamber'])
            ->where('serial_number', '<', $booking->serial_number)
            ->count();
    }
}
