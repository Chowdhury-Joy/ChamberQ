<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\BookingPushSubscription;
use App\Support\PushEndpoint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QueuePushController extends Controller
{
    public function store(Request $request, Booking $booking): JsonResponse
    {
        $data = $request->validate([
            // This route is unauthenticated — the ticket UUID is the whole gate,
            // and anyone can obtain one by booking a serial. `url` alone let the
            // caller choose any address the server would later POST to; see
            // App\Support\PushEndpoint.
            'endpoint' => ['required', 'string', 'max:512', PushEndpoint::rule()],
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
