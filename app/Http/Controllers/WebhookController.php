<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\PaymentTransaction;
use App\Scopes\TenantScope;
use App\Services\Payments\BkashGateway;
use App\Services\Payments\NagadGateway;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\SslCommerzGateway;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    private const GATEWAYS = [
        'bkash' => BkashGateway::class,
        'nagad' => NagadGateway::class,
        'sslcommerz' => SslCommerzGateway::class,
    ];

    public function payment(Request $request, string $gateway): JsonResponse
    {
        if (! array_key_exists($gateway, self::GATEWAYS)) {
            return response()->json(['error' => 'Unknown gateway'], 404);
        }

        /** @var PaymentGateway $driver */
        $driver = app(self::GATEWAYS[$gateway]);

        // Fail closed. A missing secret, an unverifiable payload, or a payment
        // the gateway does not confirm as complete is rejected outright.
        if (! $driver->verify($request)) {
            Log::warning('Rejected unverified payment webhook.', [
                'gateway' => $gateway,
                'ip' => $request->ip(),
            ]);

            return response()->json(['error' => 'Verification failed'], 403);
        }

        $transactionId = $driver->transactionId($request);
        $bookingReference = $driver->bookingReference($request);

        if (blank($transactionId) || blank($bookingReference)) {
            return response()->json(['error' => 'Incomplete payload'], 422);
        }

        // The tenant is derived from the booking we already hold, never from
        // anything the caller sent. The scope is lifted deliberately: this is a
        // central route with no tenant context, and the lookup is by UUID.
        $booking = Booking::withoutGlobalScope(TenantScope::class)
            ->whereKey($bookingReference)
            ->first();

        if (! $booking) {
            Log::warning('Payment webhook referenced an unknown booking.', [
                'gateway' => $gateway,
                'reference' => $bookingReference,
            ]);

            return response()->json(['error' => 'Booking not found'], 404);
        }

        try {
            DB::transaction(function () use ($booking, $driver, $request, $gateway, $transactionId) {
                // The unique (gateway, transaction_id) index is the idempotency
                // gate: a retried delivery collides here and is swallowed below,
                // so a booking is never credited twice.
                $transaction = new PaymentTransaction([
                    'booking_id' => $booking->id,
                    'gateway' => $gateway,
                    'transaction_id' => $transactionId,
                    'amount' => $driver->amount($request),
                    'status' => 'verified',
                    'payload' => $request->all(),
                    'verified_at' => now(),
                ]);

                // tenant_id is intentionally not fillable; it is taken from the
                // booking rather than from anything the caller could influence.
                $transaction->tenant_id = $booking->tenant_id;
                $transaction->save();

                $booking->forceFill([
                    'payment_status' => 'paid',
                    'payment_reference' => $transactionId,
                ])->save();
            });
        } catch (QueryException $e) {
            if ($this->isDuplicate($e)) {
                // Gateways retry as normal behaviour; a replay is success.
                return response()->json(['success' => true, 'duplicate' => true]);
            }

            throw $e;
        }

        return response()->json(['success' => true]);
    }

    private function isDuplicate(QueryException $e): bool
    {
        return in_array($e->getCode(), ['23000', '23505'], true);
    }
}
