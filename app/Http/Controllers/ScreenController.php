<?php

namespace App\Http\Controllers;

use App\Models\ScheduleSession;
use App\Models\LiveSession;
use Illuminate\Http\Request;

class ScreenController extends Controller
{
    public function show(ScheduleSession $session, $date)
    {
        $liveSession = LiveSession::where('schedule_session_id', $session->id)
            ->whereDate('session_date', $date)
            ->first();

        return view('tenant.screen', [
            'scheduleSession' => $session,
            'sessionDate' => $date,
            'liveSession' => $liveSession,
        ]);
    }

    public function api(ScheduleSession $session, $date)
    {
        $liveSession = LiveSession::where('schedule_session_id', $session->id)
            ->whereDate('session_date', $date)
            ->first();

        if (!$liveSession) {
            return response()->json(['status' => 'scheduled']);
        }

        $currentBooking = $liveSession->currentBooking;

        return response()->json([
            'status' => $liveSession->status,
            'now_serving' => $currentBooking ? $currentBooking->serial_number : null,
            'now_serving_name' => $currentBooking ? $currentBooking->patient_name : null,
            'is_called' => $currentBooking ? $currentBooking->status === 'called' : false,
            'called_at' => $currentBooking ? $currentBooking->called_at : null,
            'pause_reason' => $liveSession->pause_reason,
            'estimated_resume_time' => $liveSession->paused_at 
                ? $liveSession->paused_at->copy()->addMinutes($liveSession->estimated_pause_minutes)->format('h:i A')
                : null,
            'next_booking' => $this->getNextBookingSerial($liveSession),
        ]);
    }

    private function getNextBookingSerial(LiveSession $liveSession)
    {
        $current = $liveSession->currentBooking;
        $next = $liveSession->bookings()
            ->where('status', 'waiting')
            ->when($current, fn($q) => $q->where('serial_number', '>', $current->serial_number))
            ->orderBy('serial_number')
            ->first();

        return $next ? $next->serial_number : null;
    }
}
