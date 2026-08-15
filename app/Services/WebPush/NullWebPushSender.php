<?php

namespace App\Services\WebPush;

use App\Contracts\WebPushSender;
use App\Models\BookingPushSubscription;

class NullWebPushSender implements WebPushSender
{
    public function send(BookingPushSubscription $subscription, array $payload): string
    {
        return 'fail';
    }
}
