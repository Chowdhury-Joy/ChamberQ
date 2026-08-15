<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\LiveSession;
use App\Models\ScheduleSession;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Compact queue snapshot for offline Call next on this computer.
 */
class OfflineQueueBagService
{
    public function __construct(
        private readonly LiveSessionService $liveSessionService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(ScheduleSession $session, ?string $sessionDate = null): array
    {
        $date = $sessionDate ?? Carbon::today()->toDateString();

        $liveSession = LiveSession::query()
            ->where('schedule_session_id', $session->id)
            ->where('session_date', $date)
            ->first();

        if (! $liveSession) {
            throw ValidationException::withMessages([
                'session' => 'No live session for today — start the queue first.',
            ]);
        }

        $bookings = $liveSession->bookings()
            ->orderBy('serial_number')
            ->get()
            ->map(fn (Booking $booking): array => [
                'id' => $booking->id,
                'serial_number' => (int) $booking->serial_number,
                'patient_name' => $booking->patient_name,
                'status' => $booking->status,
                'skip_count' => (int) $booking->skip_count,
                'is_overflow' => (bool) $booking->is_overflow,
                'retry_queue_position' => $booking->retry_queue_position !== null
                    ? (int) $booking->retry_queue_position
                    : null,
            ])
            ->values()
            ->all();

        $publishedStillActive = $liveSession->bookings()
            ->where('is_overflow', false)
            ->whereIn('status', ['waiting', 'called', 'skipped', 'in_chamber'])
            ->exists();

        $current = $liveSession->currentBooking;

        return [
            'packed_at' => now()->toIso8601String(),
            'live_session_id' => $liveSession->id,
            'schedule_session_id' => $session->id,
            'session_date' => $date,
            'status' => $liveSession->status,
            'current_booking_id' => $liveSession->current_booking_id,
            'current_called_at' => $liveSession->current_called_at?->toIso8601String(),
            'published_still_active' => $publishedStillActive,
            'bookings' => $bookings,
            'screen' => $this->screenFields($liveSession, $session, $date, $current),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function screenFields(
        LiveSession $liveSession,
        ScheduleSession $session,
        string $date,
        ?Booking $current,
    ): array {
        $next = $this->nextWaitingBooking($liveSession, $current);

        return [
            'status' => $liveSession->status,
            'session_date' => $date,
            'now_serving' => $current?->serial_number,
            'now_serving_name' => $current?->patient_name,
            'is_called' => $current?->status === 'called',
            'called_at' => $current?->called_at?->toIso8601String(),
            'pause_reason' => $liveSession->pause_reason,
            'estimated_resume_time' => $liveSession->paused_at
                ? $liveSession->paused_at->copy()->addMinutes($liveSession->estimated_pause_minutes)->format('h:i A')
                : null,
            'next_booking' => $next?->serial_number,
            'next_estimated_time' => $this->tvEstimatedTime($next),
        ];
    }

    private function nextWaitingBooking(LiveSession $liveSession, ?Booking $current): ?Booking
    {
        return $liveSession->bookings()
            ->where('status', 'waiting')
            ->when($current, fn ($q) => $q->where('serial_number', '>', $current->serial_number))
            ->orderBy('serial_number')
            ->first();
    }

    private function tvEstimatedTime(?Booking $booking): ?string
    {
        if (! $booking) {
            return null;
        }

        $estimate = $this->liveSessionService->estimatedTimeForBooking($booking);
        $actual = $estimate['actual_estimate'] ?? null;

        if (! $actual instanceof Carbon) {
            return null;
        }

        return $actual->copy()
            ->subMinutes(\App\Http\Controllers\ScreenController::TV_NEXT_ETA_LEAD_MINUTES)
            ->format('h:i A');
    }
}
