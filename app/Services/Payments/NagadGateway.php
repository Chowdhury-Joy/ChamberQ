<?php

namespace App\Services\Payments;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Nagad payment verification.
 *
 * Nagad returns the customer to a callback carrying `payment_ref_id`. As with
 * bKash, the callback itself is not trustworthy, so the reference is verified
 * server-to-server against Nagad's verification endpoint.
 *
 * NOTE: needs exercising against the Nagad sandbox before go-live. The endpoint
 * and field names follow the merchant docs but have not been run against a live
 * sandbox in this repository.
 */
class NagadGateway implements PaymentGateway
{
    /** @var array<string, mixed>|null */
    private ?array $verification = null;

    public function verify(Request $request): bool
    {
        return $this->isSuccessful($request);
    }

    public function transactionId(Request $request): ?string
    {
        return $this->verification($request)['issuerPaymentRefNo'] ?? null;
    }

    public function bookingReference(Request $request): ?string
    {
        // Our booking UUID is sent as the merchant order id.
        return $this->verification($request)['orderId'] ?? null;
    }

    public function amount(Request $request): ?string
    {
        return $this->verification($request)['amount'] ?? null;
    }

    public function isSuccessful(Request $request): bool
    {
        return ($this->verification($request)['status'] ?? null) === 'Success';
    }

    /**
     * @return array<string, mixed>
     */
    private function verification(Request $request): array
    {
        if ($this->verification !== null) {
            return $this->verification;
        }

        $config = config('services.nagad');
        $reference = $request->input('payment_ref_id');

        foreach (['base_url', 'merchant_id'] as $key) {
            if (blank($config[$key] ?? null)) {
                Log::warning('Nagad webhook rejected: missing configuration.', ['missing' => $key]);

                return $this->verification = [];
            }
        }

        if (blank($reference)) {
            return $this->verification = [];
        }

        $response = Http::acceptJson()
            ->withHeaders([
                'X-KM-Api-Version' => 'v-0.2.0',
                'X-KM-MC-Id' => $config['merchant_id'],
            ])
            ->get(rtrim($config['base_url'], '/') . '/api/dfs/verify/payment/' . urlencode($reference));

        return $this->verification = $response->successful() ? (array) $response->json() : [];
    }
}
