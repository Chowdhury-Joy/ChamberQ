<?php

namespace App\Services\Sms;

use App\Contracts\SmsGateway;
use Illuminate\Support\Facades\Log;

/**
 * Dev/test driver — never hits the network. Always succeeds.
 */
class LogSmsGateway implements SmsGateway
{
    public function send(string $to, string $message): void
    {
        Log::info('sms.sent', [
            'to' => $to,
            'message' => $message,
        ]);
    }
}
