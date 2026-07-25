<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\ScheduleSession;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class QueueStatusController extends Controller
{
    public function show(Request $request, string $sessionId, string $date): JsonResponse
    {
        $session = ScheduleSession::findOrFail($sessionId);
        
        $nowServing = Booking::where('bookable_type', ScheduleSession::class)
            ->where('bookable_id', $session->id)
            ->where('booking_date', $date)
            ->where('status', 'in_chamber')
            ->min('serial_number');
            
        return response()->json([
            'now_serving' => $nowServing, // Returns null if no patient is currently in the chamber
        ]);
    }
}
