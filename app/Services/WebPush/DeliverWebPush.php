<?php

namespace App\Services\WebPush;

use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

class DeliverWebPush
{
    /**
     * @param  array{title: string, body: string, url: string}  $payload
     * @return 'ok'|'gone'|'fail'
     */
    public static function send(string $endpoint, string $p256dh, string $authToken, array $payload): string
    {
        $public = (string) config('webpush.vapid.public_key');
        $private = (string) config('webpush.vapid.private_key');

        if ($public === '' || $private === '') {
            return 'fail';
        }

        try {
            $webPush = new WebPush([
                'VAPID' => [
                    'subject' => (string) config('webpush.vapid.subject'),
                    'publicKey' => $public,
                    'privateKey' => $private,
                ],
            ]);

            $report = $webPush->sendOneNotification(
                Subscription::create([
                    'endpoint' => $endpoint,
                    'publicKey' => $p256dh,
                    'authToken' => $authToken,
                ]),
                json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            );

            if ($report->isSubscriptionExpired()) {
                return 'gone';
            }

            return $report->isSuccess() ? 'ok' : 'fail';
        } catch (Throwable $e) {
            Log::warning('webpush.send_failed', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);

            return 'fail';
        }
    }
}
