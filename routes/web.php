<?php

use Illuminate\Support\Facades\Route;

$centralDomains = config('tenancy.central_domains');

if (blank($centralDomains)) {
    throw new RuntimeException('tenancy.central_domains is empty — no central routes would be registered.');
}

foreach ($centralDomains as $domain) {
    Route::domain($domain)->group(function () {
        Route::get('/', function () {
            return view('welcome');
        });

        // Server-to-server only. Verification happens per gateway inside the
        // controller and fails closed; the throttle is a blunt backstop against
        // someone hammering the endpoint with forged payloads.
        Route::post('/webhooks/payment/{gateway}', [\App\Http\Controllers\WebhookController::class, 'payment'])
            ->middleware('throttle:60,1')
            ->name('webhooks.payment');
    });
}
