<?php

namespace App\Services;

use App\Models\Booking;
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
            $today = Carbon::today();
            
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

            return $liveSession->fresh();
        });
    }

    /**
     * Call the next available patient in the queue
     */
    public function callNextPatient(LiveSession $liveSession): ?Booking
    {
        return DB::transaction(function () use ($liveSession) {
            $liveSession = $this->lockSession($liveSession);

            return $this->advanceQueue($liveSession);
        });
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
        ]);

        return $booking;
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

            return $this->advanceQueue($liveSession);
        });
    }

    /**
     * Roster shortcut: move a specific waiting patient into the chamber and
     * sync live-session pointers (Daily Roster must not bypass this).
     */
    public function bringBookingToChamber(Booking $booking): void
    {
        if ($booking->bookable_type !== ScheduleSession::class) {
            $booking->update([
                'status' => 'in_chamber',
                'in_chamber_at' => now(),
            ]);

            return;
        }

        DB::transaction(function () use ($booking) {
            $now = now();
            $liveSession = LiveSession::firstOrCreate([
                'tenant_id' => tenant('id'),
                'schedule_session_id' => $booking->bookable_id,
                'session_date' => $booking->booking_date,
            ], [
                'status' => 'active',
                'started_at' => $now,
            ]);

            $liveSession = $this->lockSession($liveSession);

            if (in_array($liveSession->status, ['scheduled', 'delayed'], true)) {
                $liveSession->update([
                    'status' => 'active',
                    'started_at' => $liveSession->started_at ?? $now,
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

                    return;
                }
            }

            $booking->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);
        });
    }

    public function skipPatient(LiveSession $liveSession)
    {
        return DB::transaction(function () use ($liveSession) {
            $liveSession = $this->lockSession($liveSession);
            $booking = $liveSession->currentBooking;
            if ($booking) {
                $skipCount = $booking->skip_count + 1;
                
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

            return $this->advanceQueue($liveSession);
        });
    }

    public function reinstatePatient(Booking $booking)
    {
        $liveSession = $booking->liveSession();
        if (!$liveSession) return;
        
        $current = $liveSession->currentBooking;
        $currentSerial = $current ? $current->serial_number : $booking->serial_number;

        $booking->update([
            'status' => 'skipped', // Using skipped so it gets picked up by retry logic
            'skip_count' => 0, // Reset so they get 2 more chances
            'retry_queue_position' => $currentSerial + 2,
        ]);
    }

    public function endSession(LiveSession $liveSession, string $reason = 'Session ended')
    {
        DB::transaction(function () use ($liveSession, $reason) {
            $liveSession = $this->lockSession($liveSession);
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
        });
    }

    public function markDelay(LiveSession $liveSession, int $minutes)
    {
        $liveSession->update([
            'status' => 'delayed',
            'delay_minutes' => $minutes,
        ]);
    }

    public function markAbsent(LiveSession $liveSession, string $reason = 'Doctor unavailable')
    {
        DB::transaction(function () use ($liveSession, $reason) {
            $liveSession = $this->lockSession($liveSession);

            $liveSession->update([
                'status' => 'cancelled',
                'cancellation_reason' => $reason,
                'completed_at' => now(),
            ]);

            $liveSession->bookings()
                ->whereNotIn('status', ['completed', 'cancelled', 'no_show'])
                ->update([
                    'status' => 'cancelled',
                    'cancellation_reason' => $reason,
                    'cancelled_at' => now(),
                ]);
        });
    }

    public function pauseSession(LiveSession $liveSession, string $reason, int $estimatedMinutes)
    {
        $liveSession->update([
            'status' => 'paused',
            'paused_at' => now(),
            'pause_reason' => $reason,
            'estimated_pause_minutes' => $estimatedMinutes,
        ]);
    }

    public function resumeSession(LiveSession $liveSession)
    {
        if ($liveSession->paused_at) {
            $actualPauseMinutes = (int) $liveSession->paused_at->diffInMinutes(now());
            
            $liveSession->update([
                'status' => 'active',
                'total_pause_minutes' => $liveSession->total_pause_minutes + $actualPauseMinutes,
                'paused_at' => null,
                'pause_reason' => null,
                'estimated_pause_minutes' => 0,
            ]);
        }
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
        $session = $liveSession->scheduleSession;
        $totalPause = $liveSession->total_pause_minutes;
        if ($liveSession->status === 'paused') {
            $totalPause += $liveSession->estimated_pause_minutes;
        }

        $start = Carbon::parse($session->start_time)->setDateFrom($booking->booking_date);
        $start->addMinutes($liveSession->delay_minutes);
        $start->addMinutes($totalPause);

        return $start;
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

    private function aheadInQueue(Booking $booking, LiveSession $liveSession): int
    {
        return $liveSession->bookings()
            ->whereIn('status', ['waiting', 'called', 'in_chamber'])
            ->where('serial_number', '<', $booking->serial_number)
            ->count();
    }
}
