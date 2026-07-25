<?php

namespace App\Services\Payments;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * bKash tokenized checkout.
 *
 * bKash does not sign its callback, so a signature check is not possible and
 * inventing one would be security theatre. The correct verification is
 * server-to-server: take the paymentID from the callback and ask bKash directly
 * what happened. The callback is treated purely as a hint that something
 * changed — the gateway's own answer is the only thing trusted.
 *
 * NOTE: needs exercising against the bKash sandbox before go-live. The flow and
 * field names follow the tokenized checkout docs but have not been run against
 * a live sandbox in this repository.
 */
class BkashGateway implements PaymentGateway
{
    /** @var array<string, mixed>|null */
    private ?array $status = null;

    public function verify(Request $request): bool
    {
        return $this->isSuccessful($request);
    }

    public function transactionId(Request $request): ?string
    {
        return $this->status($request)['trxID'] ?? null;
    }

    public function bookingReference(Request $request): ?string
    {
        // Our booking UUID is sent as the merchant invoice number.
        return $this->status($request)['merchantInvoiceNumber'] ?? null;
    }

    public function amount(Request $request): ?string
    {
        return $this->status($request)['amount'] ?? null;
    }

    public function isSuccessful(Request $request): bool
    {
        $status = $this->status($request);

        return ($status['transactionStatus'] ?? null) === 'Completed'
            && ! blank($status['trxID'] ?? null);
    }

    /**
     * Ask bKash for the authoritative state of this payment.
     *
     * @return array<string, mixed>
     */
    private function status(Request $request): array
    {
        if ($this->status !== null) {
            return $this->status;
        }

        $config = config('services.bkash');
        $paymentId = $request->input('paymentID');

        // Fail closed on missing credentials or a callback with no payment id.
        foreach (['base_url', 'app_key', 'app_secret', 'username', 'password'] as $key) {
            if (blank($config[$key] ?? null)) {
                Log::warning('bKash webhook rejected: missing configuration.', ['missing' => $key]);

                return $this->status = [];
            }
        }

        if (blank($paymentId)) {
            return $this->status = [];
        }

        $token = Http::asJson()
            ->withHeaders([
                'username' => $config['username'],
                'password' => $config['password'],
            ])
            ->post(rtrim($config['base_url'], '/') . '/tokenized/checkout/token/grant', [
                'app_key' => $config['app_key'],
                'app_secret' => $config['app_secret'],
            ])
            ->json('id_token');

        if (blank($token)) {
            Log::warning('bKash webhook rejected: could not obtain an id_token.');

            return $this->status = [];
        }

        $response = Http::asJson()
            ->withHeaders([
                'Authorization' => $token,
                'X-APP-Key' => $config['app_key'],
            ])
            ->post(rtrim($config['base_url'], '/') . '/tokenized/checkout/payment/status', [
                'paymentID' => $paymentId,
            ]);

        return $this->status = $response->successful() ? (array) $response->json() : [];
    }
}
