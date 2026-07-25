<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Booking;

class WebhookController extends Controller
{
    public function payment(Request $request)
    {
        $tenantId = $request->input('tenant_id');
        $reference = $request->input('payment_reference');

        if (!$tenantId || !$reference) {
            return response()->json(['error' => 'Missing payload data'], 400);
        }

        $booking = Booking::withoutGlobalScope(\App\Scopes\TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->where('id', $reference)
            ->first();

        if (!$booking) {
            return response()->json(['error' => 'Booking not found or tenant mismatch'], 404);
        }

        $booking->update([
            'payment_status' => 'paid',
        ]);

        return response()->json(['success' => true]);
    }
}
