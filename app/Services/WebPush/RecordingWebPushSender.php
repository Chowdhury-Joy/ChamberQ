<?php

namespace App\Services\WebPush;

use App\Contracts\WebPushSender;
use App\Models\BookingPushSubscription;

/**
 * Test double — records payloads and never talks to FCM.
 */
class RecordingWebPushSender implements WebPushSender
{
    /** @var list<array{subscription: BookingPushSubscription, payload: array{title: string, body: string, url: string, stage: string}}> */
    public array $sent = [];

    public function send(BookingPushSubscription $subscription, array $payload): string
    {
        $this->sent[] = [
            'subscription' => $subscription,
            'payload' => $payload,
        ];

        return 'ok';
    }
}
