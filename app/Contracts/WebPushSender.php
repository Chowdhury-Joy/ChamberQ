<?php

namespace App\Contracts;

use App\Models\BookingPushSubscription;

interface WebPushSender
{
    /**
     * @param  array{title: string, body: string, url: string, stage: string}  $payload
     * @return 'ok'|'gone'|'fail'
     */
    public function send(BookingPushSubscription $subscription, array $payload): string;
}
