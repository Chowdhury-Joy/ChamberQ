<?php

namespace App\Services\Sms;

use App\Contracts\SmsGateway;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Generic HTTP SMS API: POST JSON { api_key, to, message, sender }.
 *
 * Point SMS_HTTP_URL at your BD aggregator (or a thin proxy). Expect HTTP 2xx
 * for success; non-2xx throws so the wallet credit is refunded.
 */
class HttpSmsGateway implements SmsGateway
{
    public function send(string $to, string $message): void
    {
        $url = config('sms.http.url');

        if (! filled($url)) {
            throw new RuntimeException('SMS_HTTP_URL is not configured.');
        }

        $response = Http::timeout((int) config('sms.http.timeout', 10))
            ->acceptJson()
            ->asJson()
            ->post($url, [
                'api_key' => config('sms.http.api_key'),
                'to' => $to,
                'message' => $message,
                'sender' => config('sms.http.sender'),
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(sprintf(
                'SMS gateway HTTP %s: %s',
                $response->status(),
                mb_substr($response->body(), 0, 500)
            ));
        }
    }
}
