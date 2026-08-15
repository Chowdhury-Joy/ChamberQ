<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\BookingPushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QueuePushController extends Controller
{
    public function store(Request $request, Booking $booking): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'url', 'max:512'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
        ]);

        BookingPushSubscription::query()->updateOrCreate(
            [
                'tenant_id' => tenant('id'),
                'endpoint_hash' => hash('sha256', $data['endpoint']),
            ],
            [
                'booking_id' => $booking->id,
                'endpoint' => $data['endpoint'],
                'p256dh' => $data['keys']['p256dh'],
                'auth_token' => $data['keys']['auth'],
            ],
        );

        return response()->json(['ok' => true]);
    }
}
