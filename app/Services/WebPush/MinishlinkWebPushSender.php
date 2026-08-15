<?php

namespace App\Services\WebPush;

use App\Contracts\WebPushSender;
use App\Models\BookingPushSubscription;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

class MinishlinkWebPushSender implements WebPushSender
{
    public function send(BookingPushSubscription $subscription, array $payload): string
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
                    'endpoint' => $subscription->endpoint,
                    'publicKey' => $subscription->p256dh,
                    'authToken' => $subscription->auth_token,
                ]),
                json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            );

            if ($report->isSubscriptionExpired()) {
                return 'gone';
            }

            return $report->isSuccess() ? 'ok' : 'fail';
        } catch (Throwable $e) {
            Log::warning('webpush.send_failed', [
                'booking_id' => $subscription->booking_id,
                'error' => $e->getMessage(),
            ]);

            return 'fail';
        }
    }
}
