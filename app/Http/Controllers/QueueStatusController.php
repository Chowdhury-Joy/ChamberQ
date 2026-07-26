<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Services\LiveSessionService;
use Illuminate\Http\JsonResponse;

class QueueStatusController extends Controller
{
    protected LiveSessionService $liveSessionService;

    public function __construct(LiveSessionService $liveSessionService)
    {
        $this->liveSessionService = $liveSessionService;
    }

    public function show(Booking $booking): JsonResponse
    {
        $liveSession = $booking->liveSession();
        
        $queue = Booking::where('bookable_type', $booking->bookable_type)
            ->where('bookable_id', $booking->bookable_id)
            ->whereDate('booking_date', $booking->booking_date);

        $nowServing = null;
        if ($liveSession && $liveSession->currentBooking) {
            $nowServing = $liveSession->currentBooking->serial_number;
        } else {
            // Fallback for sessions not managed via LiveQueue yet
            $nowServing = (clone $queue)
                ->where('status', 'in_chamber')
                ->min('serial_number');
        }

        $aheadOfYou = (clone $queue)
            ->whereIn('status', ['waiting', 'in_chamber', 'called', 'skipped']) // Including skipped in case they are re-inserted ahead? Wait, simple count is fine.
            ->where('serial_number', '<', $booking->serial_number)
            ->whereNotIn('status', ['completed', 'cancelled', 'no_show'])
            ->count();
            
        $estimateData = $this->liveSessionService->estimatedTimeForBooking($booking);

        return response()->json([
            'now_serving' => $nowServing,
            'your_serial' => $booking->serial_number,
            'ahead_of_you' => $aheadOfYou,
            'status' => $booking->status,
            'session_status' => $liveSession ? $liveSession->status : 'scheduled',
            'delay_minutes' => $liveSession ? $liveSession->delay_minutes : 0,
            'is_paused' => $liveSession && $liveSession->status === 'paused',
            'pause_reason' => $liveSession ? $liveSession->pause_reason : null,
            'estimated_pause_minutes' => $liveSession ? $liveSession->estimated_pause_minutes : null,
            'shown_time' => $estimateData ? $estimateData['shown_time']->toIso8601String() : null,
            'is_called' => $booking->status === 'called',
        ]);
    }
}
