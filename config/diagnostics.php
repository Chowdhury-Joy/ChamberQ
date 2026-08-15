<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication diagnostics
    |--------------------------------------------------------------------------
    |
    | Turns on `AuthDebugProvider` and `SessionProbe`, which record who is
    | authenticated on each request and — the point of them — what caused a
    | logout, with the application stack frames that led there.
    |
    | Off by default. Switch on with AUTH_DEBUG=true only while chasing a
    | reported sign-out, then switch it back: these log every request on the
    | `single` channel, including session ids (truncated) and referers.
    |
    | Read through config, never `env()` at the call site. `php artisan
    | config:cache` — which any real deployment runs — makes Laravel skip
    | loading `.env` entirely, so `env()` outside a config file returns null on
    | a live server. Both diagnostics did exactly that, which meant the one
    | environment they existed for was the one environment they could not be
    | switched on in. See decisions.md 2026-08-05 (instrumentation kept to find
    | the cause) and the correction that follows it.
    |
    */

    'auth' => (bool) env('AUTH_DEBUG', false),

];
