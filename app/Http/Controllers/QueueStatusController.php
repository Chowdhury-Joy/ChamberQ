<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\ScheduleSession;
use App\Models\LabCollectionSlot;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class QueueStatusController extends Controller
{
    public function show(Request $request, string $type, string $bookableId, string $date): JsonResponse
    {
        $bookableClass = $type === 'lab' ? LabCollectionSlot::class : ScheduleSession::class;
        
        $nowServing = Booking::where('bookable_type', $bookableClass)
            ->where('bookable_id', $bookableId)
            ->where('booking_date', $date)
            ->where('status', 'in_chamber')
            ->min('serial_number');
            
        return response()->json([
            'now_serving' => $nowServing,
        ]);
    }
}
