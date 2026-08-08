<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\LiveSession;
use App\Models\ScheduleSession;
use App\Services\LiveSessionService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class ScreenController extends Controller
{
    /**
     * How many minutes before the raw ETA the waiting-room TV shows.
     * Patients glance at the board and start walking; five minutes is enough
     * to reach the door without using the ticket's larger "come early" buffer.
     */
    public const TV_NEXT_ETA_LEAD_MINUTES = 5;

    public function __construct(
        private LiveSessionService $liveSessionService,
    ) {}

    /**
     * Stable outdoor-TV URL: always resolves to the chamber's "today"
     * (APP_TIMEZONE). Staff bookmark this once; no daily paste.
     */
    public function showToday(ScheduleSession $session): View
    {
        return $this->renderScreen($session, $this->today(), liveToday: true);
    }

    public function apiToday(ScheduleSession $session): JsonResponse
    {
        return $this->screenPayload($session, $this->today());
    }

    /**
     * Dated URL kept for old bookmarks and deep links.
     */
    public function show(ScheduleSession $session, string $date): View
    {
        return $this->renderScreen($session, $this->normalizeDate($date), liveToday: false);
    }

    public function api(ScheduleSession $session, string $date): JsonResponse
    {
        return $this->screenPayload($session, $this->normalizeDate($date));
    }

    private function renderScreen(ScheduleSession $session, string $date, bool $liveToday): View
    {
        $liveSession = LiveSession::where('schedule_session_id', $session->id)
            ->where('session_date', $date)
            ->first();

        return view('tenant.screen', [
            'scheduleSession' => $session,
            'sessionDate' => $date,
            'liveSession' => $liveSession,
            'liveToday' => $liveToday,
        ]);
    }

    private function screenPayload(ScheduleSession $session, string $date): JsonResponse
    {
        $liveSession = LiveSession::where('schedule_session_id', $session->id)
            ->where('session_date', $date)
            ->first();

        if (! $liveSession) {
            return response()->json([
                'status' => 'scheduled',
                'session_date' => $date,
            ]);
        }

        $currentBooking = $liveSession->currentBooking;
        $next = $this->nextWaitingBooking($liveSession);

        return response()->json([
            'status' => $liveSession->status,
            'session_date' => $date,
            'now_serving' => $currentBooking ? $currentBooking->serial_number : null,
            'now_serving_name' => $currentBooking ? $currentBooking->patient_name : null,
            'is_called' => $currentBooking ? $currentBooking->status === 'called' : false,
            'called_at' => $currentBooking ? $currentBooking->called_at : null,
            'pause_reason' => $liveSession->pause_reason,
            'estimated_resume_time' => $liveSession->paused_at
                ? $liveSession->paused_at->copy()->addMinutes($liveSession->estimated_pause_minutes)->format('h:i A')
                : null,
            'next_booking' => $next?->serial_number,
            'next_estimated_time' => $this->tvEstimatedTime($next),
        ]);
    }

    private function today(): string
    {
        return Carbon::today()->toDateString();
    }

    private function normalizeDate(string $date): string
    {
        return Carbon::parse($date)->toDateString();
    }

    private function nextWaitingBooking(LiveSession $liveSession): ?Booking
    {
        $current = $liveSession->currentBooking;

        return $liveSession->bookings()
            ->where('status', 'waiting')
            ->when($current, fn ($q) => $q->where('serial_number', '>', $current->serial_number))
            ->orderBy('serial_number')
            ->first();
    }

    /**
     * TV display time = engine's actual_estimate minus a fixed lead.
     * Deliberately not the ticket's shown_time (which uses a larger buffer).
     */
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
            ->subMinutes(self::TV_NEXT_ETA_LEAD_MINUTES)
            ->format('h:i A');
    }
}
