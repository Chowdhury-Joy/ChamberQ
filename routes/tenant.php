<?php

declare(strict_types=1);

use App\Http\Controllers\BookingController;
use App\Http\Controllers\ConditionController;
use App\Http\Controllers\NotifySmsController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\PrescriptionController;
use App\Http\Controllers\PrescriptionShareController;
use App\Http\Controllers\VisitMediaController;
use App\Http\Controllers\PWAController;
use App\Http\Controllers\QueueStatusController;
use App\Http\Controllers\ScreenController;
use App\Http\Controllers\ClinicContentController;
use App\Http\Controllers\WebPageController;
use App\Http\Middleware\EnsureTenantAcceptsBookings;
use App\Http\Middleware\Localization;
use App\Http\Middleware\SetPathTenantUrlDefaults;
use App\Support\TenancyUrl;
use Illuminate\Http\Request;
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

    // Kept as a GET link (the switcher is an <a> in several shells, including
    // locked solo views) — the only state it writes is the display language.
    // `back()` is not used: it trusts the Referer header, so a page on another
    // origin linking here would bounce the visitor straight back off the
    // clinic's domain, which is exactly the shape a phishing link wants.
    Route::get('/lang/{locale}', function (Request $request, $locale) {
        if (in_array($locale, ['en', 'bn'], true)) {
            session()->put('locale', $locale);
        }

        $referer = (string) $request->headers->get('referer');
        $sameHost = $referer !== ''
            && parse_url($referer, PHP_URL_HOST) === $request->getHost();

        return redirect()->to($sameHost ? $referer : tenant_web_url('/'));
    });

    Route::get('/book', [BookingController::class, 'create']);

    // Homepage hero form target. POST, not GET, so the patient's name and
    // phone number never land in the URL / history / access logs; it flashes
    // them to the session and redirects to the wizard.
    Route::post('/book', [BookingController::class, 'prefill'])
        ->middleware(['throttle:20,1']);

    Route::post('/api/bookings', [BookingController::class, 'store'])
        ->middleware(['throttle:10,1', EnsureTenantAcceptsBookings::class]);

    Route::get('/api/bookings/availability', [BookingController::class, 'availability'])
        ->middleware(['throttle:60,1']);

    Route::get('/api/bookings/open-dates', [BookingController::class, 'openDates'])
        ->middleware(['throttle:60,1']);

    // Unauthenticated patient-name oracle keyed on a guessable BD mobile —
    // throttled hard on purpose. A real patient types their number once or
    // twice; 60/min only ever served a scraper.
    Route::get('/api/patients/by-phone', [PatientController::class, 'lookupByPhone'])
        ->middleware(['throttle:10,1']);

    Route::get('/api/conditions/search', [ConditionController::class, 'search'])
        ->middleware(['auth', 'throttle:120,1']);

    Route::get('/api/medicines/search', [\App\Http\Controllers\MedicineController::class, 'search'])
        ->middleware(['auth', 'throttle:120,1']);

    Route::get('/prescriptions/{prescription}/print', [PrescriptionController::class, 'print'])
        ->middleware(['auth'])
        ->name($routeName('prescriptions.print'));

    // Patient's own copy, opened from the doctor's SMS/WhatsApp link. No auth
    // by design — an unguessable, expiring token is the gate, and the view
    // exposes only this prescription's medicines (never a diagnosis).
    //
    // Deliberately short: a temporary signed URL carries its expiry and
    // signature in the query string and ran ~181 characters, which pushed the
    // prescription SMS into a second billable segment. Two segments, so the
    // `/{slug?}` catch-all below (one segment) can never swallow it.
    Route::get('/p/{token}', [PrescriptionShareController::class, 'showByToken'])
        ->where('token', '[A-Za-z0-9]+')
        ->middleware(['throttle:30,1'])
        ->name($routeName('prescriptions.share-token'));

    // Superseded by /p/{token} above. Kept only so links already delivered
    // keep working; every one of them expires within
    // Prescription::SHARE_LINK_EXPIRY_HOURS, after which this can be deleted.
    Route::get('/prescriptions/{prescription}/share', [PrescriptionShareController::class, 'show'])
        ->middleware(['signed', 'throttle:30,1'])
        ->name($routeName('prescriptions.share'));

    // Staff-tapped SMS for cancel / prescription — gated by each doctor's
    // notify_channels prefs inside SmsService. Auth + tenant membership required.
    Route::post('/api/bookings/{booking}/sms/cancellation', [NotifySmsController::class, 'cancellation'])
        ->middleware(['auth', 'throttle:30,1'])
        ->name($routeName('bookings.sms.cancellation'));

    Route::post('/api/prescriptions/{prescription}/sms', [NotifySmsController::class, 'prescription'])
        ->middleware(['auth', 'throttle:30,1'])
        ->name($routeName('prescriptions.sms'));

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

    // Stable "always today" outdoor TV links — bookmark once per schedule session.
    Route::get('/screen/{session}', [ScreenController::class, 'showToday'])
        ->middleware('throttle:60,1')
        ->whereNumber('session')
        ->name($routeName('tenant.screen.today'));

    Route::get('/api/screen/{session}', [ScreenController::class, 'apiToday'])
        ->middleware('throttle:120,1')
        ->whereNumber('session')
        ->name($routeName('api.tenant.screen.today'));

    // Dated URLs kept for old bookmarks / deep links.
    Route::get('/screen/{session}/{date}', [ScreenController::class, 'show'])
        ->middleware('throttle:60,1')
        ->whereNumber('session')
        ->where('date', '\d{4}-\d{2}-\d{2}')
        ->name($routeName('tenant.screen'));

    Route::get('/api/screen/{session}/{date}', [ScreenController::class, 'api'])
        ->middleware('throttle:120,1')
        ->whereNumber('session')
        ->where('date', '\d{4}-\d{2}-\d{2}')
        ->name($routeName('api.tenant.screen'));

    Route::get('/bookings/{booking}', [BookingController::class, 'show'])
        ->name($routeName('bookings.show'));

    Route::get('/portal', [BookingController::class, 'portal'])
        ->middleware('throttle:30,1')
        ->name($routeName('patient.portal'));

    Route::get('/departments', [ClinicContentController::class, 'departmentsIndex'])
        ->name($routeName('clinic.departments.index'));
    Route::get('/departments/{slug}', [ClinicContentController::class, 'departmentShow'])
        ->name($routeName('clinic.departments.show'));
    Route::get('/blog', [ClinicContentController::class, 'blogIndex'])
        ->name($routeName('clinic.blog.index'));
    Route::get('/blog/{slug}', [ClinicContentController::class, 'blogShow'])
        ->name($routeName('clinic.blog.show'));
    Route::get('/doctors', [ClinicContentController::class, 'doctorsIndex'])
        ->name($routeName('clinic.doctors.index'));
    Route::get('/doctors/{slug}', [ClinicContentController::class, 'doctorShow'])
        ->name($routeName('clinic.doctors.show'));

    Route::get('/{slug?}', [WebPageController::class, 'show'])
        // Exact-segment negative lookahead — (?!foo|bar$) only anchors the last alt.
        ->where('slug', '^(?!(?:tenant|admin|api|lang|bookings|portal|departments|blog|doctors)$).*$');
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
