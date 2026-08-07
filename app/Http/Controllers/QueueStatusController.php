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
            ->where('booking_date', $booking->booking_date->toDateString());

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
            // Only people still in line ahead of Fatima — not skipped serials
            // that already passed (those inflate “2 ahead” and confuse patients).
            ->whereIn('status', ['waiting', 'called', 'in_chamber'])
            ->where('serial_number', '<', $booking->serial_number)
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
