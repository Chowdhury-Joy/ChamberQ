<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Chamber;
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

    public const CHAMBER_TV_MAX_ROOMS = 6;

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
        return response()->json($this->sessionScreenState($session, $this->today()));
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
        return response()->json($this->sessionScreenState($session, $this->normalizeDate($date)));
    }

    public function showChamberToday(Chamber $chamber): View
    {
        return view('tenant.screen-chamber', [
            'chamber' => $chamber,
            'sessionDate' => $this->today(),
        ]);
    }

    public function apiChamberToday(Chamber $chamber): JsonResponse
    {
        $date = $this->today();
        $sessions = ScheduleSession::with(['chamber', 'doctor'])
            ->where('chamber_id', $chamber->id)
            ->where('day_of_week', Carbon::today()->dayOfWeek)
            ->orderBy('start_time')
            ->get();

        $liveBySession = LiveSession::query()
            ->with('currentBooking')
            ->where('session_date', $date)
            ->whereIn('schedule_session_id', $sessions->pluck('id'))
            ->get()
            ->keyBy('schedule_session_id');

        $rooms = [];
        foreach ($sessions as $session) {
            $liveSession = $liveBySession->get($session->id);

            if (! $liveSession) {
                continue;
            }

            if (! in_array($liveSession->status, ['active', 'paused', 'delayed'], true)) {
                continue;
            }

            $rooms[] = $this->roomTileState($session, $liveSession);

            if (count($rooms) >= self::CHAMBER_TV_MAX_ROOMS) {
                break;
            }
        }

        return response()->json([
            'session_date' => $date,
            'rooms' => $rooms,
        ]);
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

    /**
     * @return array<string, mixed>
     */
    private function sessionScreenState(ScheduleSession $session, string $date): array
    {
        $liveSession = LiveSession::where('schedule_session_id', $session->id)
            ->where('session_date', $date)
            ->first();

        if (! $liveSession) {
            return [
                'status' => 'scheduled',
                'session_date' => $date,
            ];
        }

        $currentBooking = $liveSession->currentBooking;
        $next = $this->nextWaitingBooking($liveSession);

        return [
            'status' => $liveSession->status,
            'session_date' => $date,
            'now_serving' => $currentBooking ? $currentBooking->serial_number : null,
            'now_serving_name' => $currentBooking ? $currentBooking->patient_name : null,
            'is_called' => $currentBooking ? $currentBooking->status === 'called' : false,
            'called_at' => $currentBooking ? $currentBooking->called_at : null,
            'pause_reason' => $liveSession->pause_reason,
            'estimated_resume_time' => $this->estimatedResumeLabel($liveSession),
            'next_booking' => $next?->serial_number,
            'next_estimated_time' => $this->tvEstimatedTime($next),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function roomTileState(ScheduleSession $session, LiveSession $liveSession): array
    {
        $currentBooking = $liveSession->currentBooking;
        $waiting = $this->nextWaitingBookings($liveSession, 3);

        $calledAt = $currentBooking?->called_at;

        return [
            'session_id' => $session->id,
            'label' => $session->screenLabel(),
            'kind' => $session->kind,
            'now_serving' => $currentBooking ? $currentBooking->serial_number : null,
            'now_serving_name' => $currentBooking ? $currentBooking->patient_name : null,
            'announce_key' => $currentBooking && $calledAt
                ? $session->id.'|'.$currentBooking->serial_number.'|'.$calledAt->timestamp
                : null,
            'is_called' => $currentBooking ? $currentBooking->status === 'called' : false,
            'next' => $waiting->map(fn (Booking $booking): array => [
                'serial' => $booking->serial_number,
                'name' => $booking->patient_name,
            ])->values()->all(),
            'status' => $liveSession->status,
            'pause_reason' => $liveSession->pause_reason,
            'estimated_resume_time' => $this->estimatedResumeLabel($liveSession),
            'delay_minutes' => (int) ($liveSession->delay_minutes ?: 0),
        ];
    }

    private function estimatedResumeLabel(LiveSession $liveSession): ?string
    {
        $ends = $liveSession->pauseEndsAt();

        return $ends?->format('h:i A');
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
        return $this->nextWaitingBookings($liveSession, 1)->first();
    }

    /**
     * @return \Illuminate\Support\Collection<int, Booking>
     */
    private function nextWaitingBookings(LiveSession $liveSession, int $limit)
    {
        $current = $liveSession->currentBooking;

        return $liveSession->bookings()
            ->where('status', 'waiting')
            ->when($current, fn ($q) => $q->where('serial_number', '>', $current->serial_number))
            ->orderBy('serial_number')
            ->limit($limit)
            ->get();
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
