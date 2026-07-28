<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SMS feature switch
    |--------------------------------------------------------------------------
    |
    | When false, booking confirmations are never sent (wallet not debited).
    |
    */
    'enabled' => (bool) env('SMS_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Driver
    |--------------------------------------------------------------------------
    |
    | log  — write to the log (local/tests; always "succeeds")
    | http — POST to SMS_HTTP_URL with api_key, to, message, sender
    |
    */
    'driver' => env('SMS_DRIVER', 'log'),

    'http' => [
        'url' => env('SMS_HTTP_URL'),
        'api_key' => env('SMS_HTTP_API_KEY'),
        'sender' => env('SMS_HTTP_SENDER', 'DocGemini'),
        'timeout' => (int) env('SMS_HTTP_TIMEOUT', 10),
    ],

];
