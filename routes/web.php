<?php

use Illuminate\Support\Facades\Route;

$centralDomains = config('tenancy.central_domains');

if (blank($centralDomains)) {
    throw new RuntimeException('tenancy.central_domains is empty — no central routes would be registered.');
}

foreach ($centralDomains as $domain) {
    Route::domain($domain)->middleware(\App\Http\Middleware\CaptureReferralParams::class)->group(function () {
        Route::get('/', function () {
            return view('marketing.home');
        });
    });
}
