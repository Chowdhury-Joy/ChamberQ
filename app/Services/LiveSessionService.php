<?php

namespace App\Services;

use App\Models\LiveSession;
use App\Models\ScheduleSession;
use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LiveSessionService
{
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

            if ($liveSession->status === 'scheduled' || $liveSession->status === 'delayed') {
                $liveSession->update([
                    'status' => 'active',
                    'started_at' => now(),
                ]);
            }

            // Call first patient if none is called
            if (!$liveSession->current_booking_id) {
                $this->callNextPatient($liveSession);
            }

            return $liveSession;
        });
    }

    /**
     * Call the next available patient in the queue
     */
    public function callNextPatient(LiveSession $liveSession): ?Booking
    {
        return DB::transaction(function () use ($liveSession) {
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
        });
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
        $booking = $liveSession->currentBooking;
        if ($booking) {
            $booking->update([
                'status' => 'in_chamber',
                'in_chamber_at' => now(),
            ]);
        }
    }

    public function completeCurrentPatient(LiveSession $liveSession)
    {
        return DB::transaction(function () use ($liveSession) {
            $booking = $liveSession->currentBooking;
            if ($booking) {
                $booking->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);
            }

            return $this->callNextPatient($liveSession);
        });
    }

    public function skipPatient(LiveSession $liveSession)
    {
        return DB::transaction(function () use ($liveSession) {
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

            return $this->callNextPatient($liveSession);
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

    public function endSession(LiveSession $liveSession)
    {
        $liveSession->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
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
            $liveSession->update([
                'status' => 'cancelled',
                'cancellation_reason' => $reason,
                'completed_at' => now(),
            ]);

            $liveSession->bookings()
                ->whereNotIn('status', ['completed', 'cancelled'])
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
        if (!$liveSession) return null;

        $session = $liveSession->scheduleSession;
        $tenant = tenant();
        
        $avgMins = $liveSession->avgConsultationMinutes();
        
        $totalPause = $liveSession->total_pause_minutes;
        if ($liveSession->status === 'paused') {
            $totalPause += $liveSession->estimated_pause_minutes;
        }

        $start = Carbon::parse($session->start_time)->setDateFrom($booking->booking_date);
        $start->addMinutes($liveSession->delay_minutes);
        $start->addMinutes($totalPause);
        
        $actualEstimateOfFirstPatient = $start->copy();
        
        $actualEstimate = $start->copy()->addMinutes(($booking->serial_number - 1) * $avgMins);

        $firstN = $tenant?->first_n_patients ?? 2;
        $offsetMins = $tenant?->first_n_arrival_offset_minutes ?? 15;
        $bufferMins = $tenant?->estimated_time_buffer_minutes ?? 30;

        if ($booking->serial_number <= $firstN) {
            // Universal rule: shown_time = actual_estimate_of_first_patient - offset
            $shownTime = $actualEstimateOfFirstPatient->copy()->subMinutes($offsetMins);
        } else {
            $shownTime = $actualEstimate->copy()->subMinutes($bufferMins);
        }

        return [
            'actual_estimate' => $actualEstimate,
            'shown_time' => $shownTime,
        ];
    }
}
