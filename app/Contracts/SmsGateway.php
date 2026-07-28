<?php

namespace App\Contracts;

interface SmsGateway
{
    /**
     * Deliver one SMS. Throw on hard failure so the wallet can be refunded.
     *
     * @param  string  $to  International digits, e.g. 8801712345678
     */
    public function send(string $to, string $message): void;
}
