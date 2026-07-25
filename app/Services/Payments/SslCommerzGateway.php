<?php

namespace App\Services\Payments;

use Illuminate\Http\Request;

/**
 * SSLCommerz IPN validation.
 *
 * SSLCommerz signs its IPN with `verify_sign` (an MD5 hash) over the fields
 * listed in `verify_key`, with the MD5 of the store password appended. This is
 * their documented scheme and is verifiable offline, so no callback to the
 * gateway is required.
 */
class SslCommerzGateway implements PaymentGateway
{
    public function verify(Request $request): bool
    {
        $storePassword = config('services.sslcommerz.store_password');

        // Fail closed. A missing secret must reject, never bypass.
        if (blank($storePassword)) {
            return false;
        }

        $signature = $request->input('verify_sign');
        $keyList = $request->input('verify_key');

        if (blank($signature) || blank($keyList)) {
            return false;
        }

        $parts = [];

        foreach (explode(',', $keyList) as $key) {
            $key = trim($key);
            $parts[$key] = $key . '=' . $request->input($key);
        }

        ksort($parts);

        $parts['store_passwd'] = 'store_passwd=' . md5($storePassword);
        ksort($parts);

        $expected = md5(implode('&', $parts));

        return hash_equals($expected, (string) $signature);
    }

    public function transactionId(Request $request): ?string
    {
        return $request->input('bank_tran_id') ?: $request->input('tran_id');
    }

    public function bookingReference(Request $request): ?string
    {
        return $request->input('tran_id');
    }

    public function amount(Request $request): ?string
    {
        return $request->input('amount');
    }

    public function isSuccessful(Request $request): bool
    {
        return in_array($request->input('status'), ['VALID', 'VALIDATED'], true);
    }
}
