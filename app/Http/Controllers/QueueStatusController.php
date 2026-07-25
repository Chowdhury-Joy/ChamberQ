<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use Illuminate\Http\JsonResponse;

class QueueStatusController extends Controller
{
    /**
     * Live queue state for a single booking.
     *
     * Keyed by the booking's UUID rather than a sequential bookable id, so no
     * public URL exposes an enumerable database key, and a patient can only
     * poll a queue they actually hold a place in. Route-model binding runs
     * through the tenant global scope, so a UUID from another tenant 404s.
     */
    public function show(Booking $booking): JsonResponse
    {
        $queue = Booking::where('bookable_type', $booking->bookable_type)
            ->where('bookable_id', $booking->bookable_id)
            ->whereDate('booking_date', $booking->booking_date);

        // "Now serving" is the booking currently in the chamber — never the
        // highest completed serial. If #3 is completed and #4 has not been
        // called, the screen must not claim #3 is being seen.
        $nowServing = (clone $queue)
            ->where('status', 'in_chamber')
            ->min('serial_number');

        $aheadOfYou = (clone $queue)
            ->whereIn('status', ['waiting', 'in_chamber'])
            ->where('serial_number', '<', $booking->serial_number)
            ->count();

        return response()->json([
            'now_serving' => $nowServing,
            'your_serial' => $booking->serial_number,
            'ahead_of_you' => $aheadOfYou,
            'status' => $booking->status,
        ]);
    }
}
