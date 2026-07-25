<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
|
| Here you can register the tenant routes for your application.
| These routes are loaded by the TenantRouteServiceProvider.
|
| Feel free to customize them however you want. Good luck!
|
*/

Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {
    Route::get('/book', [\App\Http\Controllers\BookingController::class, 'create']);
    Route::post('/api/bookings', [\App\Http\Controllers\BookingController::class, 'store']);
    
    // PWA Routes
    Route::get('/manifest.webmanifest', [\App\Http\Controllers\PWAController::class, 'manifest']);
    Route::get('/sw.js', [\App\Http\Controllers\PWAController::class, 'serviceWorker']);
    
    // Catch-all for WebPages
    Route::get('/{slug?}', [\App\Http\Controllers\WebPageController::class, 'show'])->where('slug', '.*');

    Route::get('/api/queue/{sessionId}/{date}', [\App\Http\Controllers\QueueStatusController::class, 'show']);
});
