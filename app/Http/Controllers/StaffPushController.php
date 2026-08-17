<?php

namespace App\Http\Controllers;

use App\Models\StaffPushSubscription;
use App\Support\PushEndpoint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffPushController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            // Same gate as the patient ticket: the server POSTs to whatever is
            // stored here. See App\Support\PushEndpoint.
            'endpoint' => ['required', 'string', 'max:512', PushEndpoint::rule()],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
        ]);

        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        if (! $user->belongsToCurrentTenant()) {
            abort(403);
        }

        StaffPushSubscription::query()->updateOrCreate(
            [
                'tenant_id' => tenant('id'),
                'user_id' => $user->id,
                'endpoint_hash' => hash('sha256', $data['endpoint']),
            ],
            [
                'endpoint' => $data['endpoint'],
                'p256dh' => $data['keys']['p256dh'],
                'auth_token' => $data['keys']['auth'],
            ],
        );

        return response()->json(['ok' => true]);
    }
}
