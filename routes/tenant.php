<?php

declare(strict_types=1);

use App\Http\Controllers\BookingController;
use App\Http\Controllers\ConditionController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\PrescriptionController;
use App\Http\Controllers\PrescriptionShareController;
use App\Http\Controllers\VisitMediaController;
use App\Http\Controllers\PWAController;
use App\Http\Controllers\QueueStatusController;
use App\Http\Controllers\ScreenController;
use App\Http\Controllers\WebPageController;
use App\Http\Middleware\EnsureTenantAcceptsBookings;
use App\Http\Middleware\Localization;
use App\Http\Middleware\SetPathTenantUrlDefaults;
use App\Support\TenancyUrl;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\InitializeTenancyByPath;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
|
| Custom domains: root paths (/book) with domain-based tenancy.
| Central domain: path tenancy (/{tenant}/book) for platform URLs.
|
| IMPORTANT: Register central path routes BEFORE the domain-less group so
| /{slug} catch-alls never swallow /{tenant}/book on the central host.
|
*/

$registerTenantRoutes = function (string $routeNamePrefix = ''): void {
    $routeName = static fn (string $name): string => $routeNamePrefix.$name;

    Route::get('/lang/{locale}', function ($locale) {
        if (in_array($locale, ['en', 'bn'])) {
            session()->put('locale', $locale);
        }

        return back();
    });

    Route::get('/book', [BookingController::class, 'create']);

    Route::post('/api/bookings', [BookingController::class, 'store'])
        ->middleware(['throttle:10,1', EnsureTenantAcceptsBookings::class]);

    Route::get('/api/bookings/availability', [BookingController::class, 'availability'])
        ->middleware(['throttle:60,1']);

    Route::get('/api/patients/by-phone', [PatientController::class, 'lookupByPhone'])
        ->middleware(['throttle:60,1']);

    Route::get('/api/conditions/search', [ConditionController::class, 'search'])
        ->middleware(['auth', 'throttle:120,1']);

    Route::get('/prescriptions/{prescription}/print', [PrescriptionController::class, 'print'])
        ->middleware(['auth'])
        ->name($routeName('prescriptions.print'));

    // Patient's own copy, opened from the doctor's WhatsApp link. No auth by
    // design — the expiring signature is the gate, and the view exposes only
    // this prescription's medicines (never a diagnosis).
    Route::get('/prescriptions/{prescription}/share', [PrescriptionShareController::class, 'show'])
        ->middleware(['signed', 'throttle:30,1'])
        ->name($routeName('prescriptions.share'));

    Route::post('/api/visit-media/upload-voice', [VisitMediaController::class, 'uploadVoice'])
        ->middleware(['auth', 'throttle:30,1']);

    Route::get('/visit-records/{visitRecord}/voice', [VisitMediaController::class, 'voice'])
        ->middleware(['auth'])
        ->name($routeName('visit-records.voice'));

    Route::get('/visit-records/{visitRecord}/photo', [VisitMediaController::class, 'photo'])
        ->middleware(['auth'])
        ->name($routeName('visit-records.photo'));

    Route::get('/manifest.webmanifest', [PWAController::class, 'manifest']);
    Route::get('/sw.js', [PWAController::class, 'serviceWorker']);
    Route::get('/pwa-icon-{size}.svg', [PWAController::class, 'icon'])
        ->whereIn('size', [192, 512])
        ->name($routeName('pwa.icon'));

    Route::get('/api/queue/{booking}', [QueueStatusController::class, 'show'])
        ->middleware('throttle:120,1')
        ->name($routeName('queue.status'));

    Route::get('/screen/{session}/{date}', [ScreenController::class, 'show'])
        ->middleware('throttle:60,1')
        ->name($routeName('tenant.screen'));

    Route::get('/api/screen/{session}/{date}', [ScreenController::class, 'api'])
        ->middleware('throttle:120,1')
        ->name($routeName('api.tenant.screen'));

    Route::get('/bookings/{booking}', [BookingController::class, 'show'])
        ->name($routeName('bookings.show'));

    Route::get('/portal', [BookingController::class, 'portal'])
        ->middleware('throttle:30,1')
        ->name($routeName('patient.portal'));

    Route::get('/{slug?}', [WebPageController::class, 'show'])
        // Exact-segment negative lookahead — (?!foo|bar$) only anchors the last alt.
        ->where('slug', '^(?!(?:tenant|admin|api|lang|bookings|portal)$).*$');
};

foreach (config('tenancy.central_domains', []) as $centralDomain) {
    // One middleware() call only — RouteRegistrar overwrites, it does not merge.
    Route::domain($centralDomain)
        ->prefix('{tenant}')
        ->where(['tenant' => TenancyUrl::tenantSlugPattern()])
        ->middleware([
            'web',
            Localization::class,
            InitializeTenancyByPath::class,
            SetPathTenantUrlDefaults::class,
        ])
        ->group(function () use ($registerTenantRoutes): void {
            $registerTenantRoutes(TenancyUrl::PATH_ROUTE_PREFIX);
        });
}

Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
    Localization::class,
])->group(function () use ($registerTenantRoutes): void {
    $registerTenantRoutes('');
});
