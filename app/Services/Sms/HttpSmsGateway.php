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
                self::redact($response->body())
            ));
        }
    }

    /**
     * A gateway's error body, safe to store and log.
     *
     * This string is written to `sms_messages.error` and to the log by
     * `SmsService`, and neither is somewhere anyone looks routinely. Aggregators
     * commonly echo the request back in an error ("invalid api_key: abc123"), so
     * the reply can carry the account key that authenticates every message this
     * clinic sends. The key is a known value, so redact it by match rather than
     * guessing at field names, and keep the rest — a gateway failure is
     * undiagnosable without the message it actually returned.
     */
    private static function redact(string $body): string
    {
        foreach (['sms.http.api_key', 'sms.http.sender'] as $secret) {
            $value = (string) config($secret);

            // Short values would match far too much of an ordinary sentence.
            if (mb_strlen($value) >= 6) {
                $body = str_replace($value, '[redacted]', $body);
            }
        }

        return mb_substr($body, 0, 200);
    }
}
