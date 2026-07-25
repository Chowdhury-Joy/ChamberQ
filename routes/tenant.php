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
    \App\Http\Middleware\Localization::class,
])->group(function () {
    Route::get('/lang/{locale}', function ($locale) {
        if (in_array($locale, ['en', 'bn'])) {
            session()->put('locale', $locale);
        }
        return back();
    });
    Route::get('/book', [\App\Http\Controllers\BookingController::class, 'create']);
    Route::post('/api/bookings', [\App\Http\Controllers\BookingController::class, 'store']);
    
    // PWA Routes
    Route::get('/manifest.webmanifest', [\App\Http\Controllers\PWAController::class, 'manifest']);
    Route::get('/sw.js', [\App\Http\Controllers\PWAController::class, 'serviceWorker']);
    
    Route::get('/api/queue/{type}/{bookableId}/{date}', [\App\Http\Controllers\QueueStatusController::class, 'show']);

    // Catch-all for WebPages
    Route::get('/{slug?}', [\App\Http\Controllers\WebPageController::class, 'show'])->where('slug', '^(?!tenant|admin|api|lang).*$');
});
