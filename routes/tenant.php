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

    // Booking creation is IP rate limited, and refused outright for tenants
    // whose billing status is read_only.
    Route::post('/api/bookings', [\App\Http\Controllers\BookingController::class, 'store'])
        ->middleware(['throttle:10,1', \App\Http\Middleware\EnsureTenantAcceptsBookings::class]);

    // PWA Routes
    Route::get('/manifest.webmanifest', [\App\Http\Controllers\PWAController::class, 'manifest']);
    Route::get('/sw.js', [\App\Http\Controllers\PWAController::class, 'serviceWorker']);
    Route::get('/pwa-icon-{size}.svg', [\App\Http\Controllers\PWAController::class, 'icon'])
        ->whereIn('size', [192, 512])
        ->name('pwa.icon');

    // Keyed by the booking UUID: no sequential id is ever exposed, and a
    // patient can only poll a queue they hold a place in. Polled by the ticket
    // page, so the limit is generous but present.
    Route::get('/api/queue/{booking}', [\App\Http\Controllers\QueueStatusController::class, 'show'])
        ->middleware('throttle:120,1')
        ->name('queue.status');

    Route::get('/screen/{session}/{date}', [\App\Http\Controllers\ScreenController::class, 'show'])
        ->name('tenant.screen');
        
    Route::get('/api/screen/{session}/{date}', [\App\Http\Controllers\ScreenController::class, 'api'])
        ->name('api.tenant.screen');

    Route::get('/bookings/{booking}', [\App\Http\Controllers\BookingController::class, 'show'])
        ->name('bookings.show');

    Route::get('/portal', [\App\Http\Controllers\BookingController::class, 'portal'])
        ->middleware('throttle:30,1')
        ->name('patient.portal');

    // Catch-all for WebPages
    Route::get('/{slug?}', [\App\Http\Controllers\WebPageController::class, 'show'])->where('slug', '^(?!tenant|admin|api|lang|bookings|portal).*$');
});
