<?php

declare(strict_types=1);

use App\Http\Controllers\BookingController;
use App\Http\Controllers\ClinicContentController;
use App\Http\Controllers\ConditionController;
use App\Http\Controllers\ConditionTopicController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MedicineController;
use App\Http\Controllers\NotifySmsController;
use App\Http\Controllers\OfflineController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\FeeReceiptController;
use App\Http\Controllers\PharmacyInvoiceController;
use App\Http\Controllers\PrescriptionController;
use App\Http\Controllers\PrescriptionShareController;
use App\Http\Controllers\PWAController;
use App\Http\Controllers\QueuePushController;
use App\Http\Controllers\QueueStatusController;
use App\Http\Controllers\ScreenController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\StaffPushController;
use App\Http\Controllers\VisitMediaController;
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

    // Kept as a GET link (the switcher is an <a> in several shells, including
    // locked solo views) — the only state it writes is the display language.
    // `back()` is not used: it trusts the Referer header, so a page on another
    // origin linking here would bounce the visitor straight back off the
    // clinic's domain, which is exactly the shape a phishing link wants.
    Route::get('/lang/{locale}', [LocaleController::class, 'switch']);

    Route::get('/robots.txt', [SeoController::class, 'robots']);
    Route::get('/sitemap.xml', [SeoController::class, 'sitemap']);

    Route::get('/book', [BookingController::class, 'create'])
        ->middleware(['tenant.module:front_door']);

    // Homepage hero form target. POST, not GET, so the patient's name and
    // phone number never land in the URL / history / access logs; it flashes
    // them to the session and redirects to the wizard.
    Route::post('/book', [BookingController::class, 'prefill'])
        ->middleware(['throttle:20,1', 'tenant.module:front_door']);

    // Safety net: a browser that posts the homepage (empty/missing form
    // action) would 405 because GET /{slug?} is GET-only. Same handler as
    // POST /book — flash + redirect to the wizard; PII never stays on /.
    Route::post('/', [BookingController::class, 'prefill'])
        ->middleware(['throttle:20,1', 'tenant.module:front_door']);

    Route::post('/api/bookings', [BookingController::class, 'store'])
        ->middleware(['throttle:10,1', 'tenant.module:front_door', EnsureTenantAcceptsBookings::class]);

    Route::get('/api/bookings/availability', [BookingController::class, 'availability'])
        ->middleware(['throttle:60,1', 'tenant.module:front_door']);

    Route::get('/api/bookings/open-dates', [BookingController::class, 'openDates'])
        ->middleware(['throttle:60,1', 'tenant.module:front_door']);

    // Unauthenticated patient-name oracle keyed on a guessable BD mobile —
    // throttled hard on purpose. A real patient types their number once or
    // twice; 60/min only ever served a scraper.
    Route::get('/api/patients/by-phone', [PatientController::class, 'lookupByPhone'])
        ->middleware(['throttle:10,1', 'tenant.module:front_door']);

    Route::get('/api/conditions/search', [ConditionController::class, 'search'])
        ->middleware(['auth', 'throttle:120,1', 'tenant.module:prescription']);

    Route::get('/api/medicines/search', [MedicineController::class, 'search'])
        ->middleware(['auth', 'throttle:120,1', 'tenant.module:prescription']);

    Route::get('/api/medicines/doses', [MedicineController::class, 'doses'])
        ->middleware(['auth', 'throttle:120,1', 'tenant.module:prescription']);

    Route::get('/api/offline/bag', [OfflineController::class, 'bag'])
        ->middleware(['auth', 'throttle:30,1', 'tenant.module:prescription'])
        ->name($routeName('offline.bag'));

    Route::get('/api/offline/queue/{session}', [OfflineController::class, 'queue'])
        ->middleware(['auth', 'throttle:60,1', 'tenant.module:live_queue'])
        ->name($routeName('offline.queue'));

    Route::post('/api/offline/sync', [OfflineController::class, 'sync'])
        ->middleware(['auth', 'throttle:30,1'])
        ->name($routeName('offline.sync'));

    Route::get('/prescriptions/{prescription}/print', [PrescriptionController::class, 'print'])
        ->middleware(['auth', 'tenant.module:prescription'])
        ->name($routeName('prescriptions.print'));

    Route::get('/pharmacy-sales/{sale}/invoice', [PharmacyInvoiceController::class, 'show'])
        ->middleware(['auth', 'tenant.module:pharmacy'])
        ->name($routeName('pharmacy-invoices.show'));

    Route::get('/fee-receipts/{entry}', [FeeReceiptController::class, 'show'])
        ->middleware(['auth'])
        ->name($routeName('fee-receipts.show'));

    // Patient's own copy, opened from the doctor's SMS/WhatsApp link. No auth
    // by design — an unguessable, expiring token is the gate. Full clinical
    // pad (diagnosis + notes + meds + chamber); voice/photo stay off.
    //
    // Deliberately short: a temporary signed URL carries its expiry and
    // signature in the query string and ran ~181 characters, which pushed the
    // prescription SMS into a second billable segment. Two segments, so the
    // `/{slug?}` catch-all below (one segment) can never swallow it.
    Route::get('/p/{token}', [PrescriptionShareController::class, 'showByToken'])
        ->where('token', '[A-Za-z0-9]+')
        ->middleware(['throttle:30,1', 'tenant.module:prescription'])
        ->name($routeName('prescriptions.share-token'));

    // Superseded by /p/{token} above. Kept only so links already delivered
    // keep working; every one of them expires within
    // Prescription::SHARE_LINK_EXPIRY_HOURS, after which this can be deleted.
    Route::get('/prescriptions/{prescription}/share', [PrescriptionShareController::class, 'show'])
        ->middleware(['signed', 'throttle:30,1', 'tenant.module:prescription'])
        ->name($routeName('prescriptions.share'));

    // Portal backup when staff forget to send /p/{token}. Phone must match
    // the booking that owns the visit; durable (no 48h expiry).
    Route::get('/portal/prescriptions/{prescription}', [PrescriptionShareController::class, 'showFromPortal'])
        ->middleware(['throttle:30,1', 'tenant.module:prescription'])
        ->name($routeName('prescriptions.portal'));

    // Staff-tapped SMS for cancel / prescription — gated by each doctor's
    // notify_channels prefs inside SmsService. Auth + tenant membership required.
    Route::post('/api/bookings/{booking}/sms/cancellation', [NotifySmsController::class, 'cancellation'])
        ->middleware(['auth', 'throttle:30,1', 'tenant.module:front_door'])
        ->name($routeName('bookings.sms.cancellation'));

    Route::post('/api/bookings/{booking}/sms/confirmation', [NotifySmsController::class, 'confirmation'])
        ->middleware(['auth', 'throttle:30,1', 'tenant.module:front_door'])
        ->name($routeName('bookings.sms.confirmation'));

    Route::post('/api/bookings/{booking}/sms/late', [NotifySmsController::class, 'doctorLate'])
        ->middleware(['auth', 'throttle:30,1', 'tenant.module:live_queue'])
        ->name($routeName('bookings.sms.late'));

    Route::post('/api/bookings/{booking}/sms/follow-up', [NotifySmsController::class, 'followUp'])
        ->middleware(['auth', 'throttle:30,1', 'tenant.module:prescription'])
        ->name($routeName('bookings.sms.follow-up'));

    Route::post('/api/prescriptions/{prescription}/sms', [NotifySmsController::class, 'prescription'])
        ->middleware(['auth', 'throttle:30,1', 'tenant.module:prescription'])
        ->name($routeName('prescriptions.sms'));

    Route::post('/api/bookings/{booking}/sms/review', [NotifySmsController::class, 'review'])
        ->middleware(['auth', 'throttle:30,1'])
        ->name($routeName('bookings.sms.review'));

    Route::post('/api/visit-media/upload-voice', [VisitMediaController::class, 'uploadVoice'])
        ->middleware(['auth', 'throttle:30,1', 'tenant.module:prescription']);

    Route::post('/api/visit-media/upload-report-photo', [VisitMediaController::class, 'uploadReportPhoto'])
        ->middleware(['auth', 'throttle:30,1', 'tenant.module:prescription']);

    Route::get('/visit-records/{visitRecord}/voice', [VisitMediaController::class, 'voice'])
        ->middleware(['auth', 'tenant.module:prescription'])
        ->name($routeName('visit-records.voice'));

    Route::get('/visit-records/{visitRecord}/photo', [VisitMediaController::class, 'photo'])
        ->middleware(['auth', 'tenant.module:prescription'])
        ->name($routeName('visit-records.photo'));

    Route::get('/visit-records/{visitRecord}/report-photos/{index}', [VisitMediaController::class, 'reportPhoto'])
        ->middleware(['auth', 'tenant.module:prescription'])
        ->whereNumber('index')
        ->name($routeName('visit-records.report-photo'));

    Route::get('/manifest.webmanifest', [PWAController::class, 'manifest']);
    Route::get('/sw.js', [PWAController::class, 'serviceWorker']);
    Route::get('/pwa-icon-{size}.svg', [PWAController::class, 'icon'])
        ->whereIn('size', [192, 512])
        ->name($routeName('pwa.icon'));

    Route::get('/api/queue/{booking}', [QueueStatusController::class, 'show'])
        ->middleware(['throttle:120,1', 'tenant.module:live_queue'])
        ->name($routeName('queue.status'));

    Route::post('/api/queue/{booking}/push', [QueuePushController::class, 'store'])
        ->middleware(['throttle:20,1', 'tenant.module:live_queue'])
        ->name($routeName('queue.push'));

    Route::post('/api/staff/push', [StaffPushController::class, 'store'])
        ->middleware(['auth', 'throttle:20,1', 'tenant.module:live_queue'])
        ->name($routeName('staff.push'));

    // Combined waiting-room TV for every live sitting in one chamber today.
    Route::get('/screen/chamber/{chamber}', [ScreenController::class, 'showChamberToday'])
        ->middleware(['throttle:60,1', 'tenant.module:live_queue'])
        ->whereNumber('chamber')
        ->name($routeName('tenant.screen.chamber.today'));

    Route::get('/api/screen/chamber/{chamber}', [ScreenController::class, 'apiChamberToday'])
        ->middleware(['throttle:120,1', 'tenant.module:live_queue'])
        ->whereNumber('chamber')
        ->name($routeName('api.tenant.screen.chamber.today'));

    // Stable "always today" outdoor TV links — bookmark once per schedule session.
    Route::get('/screen/{session}', [ScreenController::class, 'showToday'])
        ->middleware(['throttle:60,1', 'tenant.module:live_queue'])
        ->whereNumber('session')
        ->name($routeName('tenant.screen.today'));

    Route::get('/api/screen/{session}', [ScreenController::class, 'apiToday'])
        ->middleware(['throttle:120,1', 'tenant.module:live_queue'])
        ->whereNumber('session')
        ->name($routeName('api.tenant.screen.today'));

    // Dated URLs kept for old bookmarks / deep links.
    Route::get('/screen/{session}/{date}', [ScreenController::class, 'show'])
        ->middleware(['throttle:60,1', 'tenant.module:live_queue'])
        ->whereNumber('session')
        ->where('date', '\d{4}-\d{2}-\d{2}')
        ->name($routeName('tenant.screen'));

    Route::get('/api/screen/{session}/{date}', [ScreenController::class, 'api'])
        ->middleware(['throttle:120,1', 'tenant.module:live_queue'])
        ->whereNumber('session')
        ->where('date', '\d{4}-\d{2}-\d{2}')
        ->name($routeName('api.tenant.screen'));

    Route::get('/bookings/{booking}', [BookingController::class, 'show'])
        ->middleware(['throttle:60,1'])
        ->name($routeName('bookings.show'));

    Route::get('/portal', [BookingController::class, 'portal'])
        ->middleware(['throttle:30,1', 'tenant.module:front_door'])
        ->name($routeName('patient.portal'));

    // POST, not GET — the patient's phone must not sit in the URL, history, or
    // access logs after lookup; it lives in the session instead.
    Route::post('/portal', [BookingController::class, 'portalLookup'])
        ->middleware(['throttle:20,1', 'tenant.module:front_door'])
        ->name($routeName('patient.portal.lookup'));

    Route::post('/portal/rx-otp/send', [BookingController::class, 'sendPortalRxOtp'])
        ->middleware(['throttle:10,1', 'tenant.module:front_door'])
        ->name($routeName('patient.portal.rx-otp.send'));

    Route::post('/portal/rx-otp/verify', [BookingController::class, 'verifyPortalRxOtp'])
        ->middleware(['throttle:10,1', 'tenant.module:front_door'])
        ->name($routeName('patient.portal.rx-otp.verify'));

    Route::post('/portal/rx-password', [BookingController::class, 'setPortalPrescriptionPassword'])
        ->middleware(['throttle:10,1', 'tenant.module:front_door'])
        ->name($routeName('patient.portal.rx-password'));

    Route::post('/portal/rx-unlock', [BookingController::class, 'unlockPortalPrescriptions'])
        ->middleware(['throttle:10,1', 'tenant.module:front_door'])
        ->name($routeName('patient.portal.rx-unlock'));

    Route::post('/portal/rx-lock', [BookingController::class, 'lockPortalPrescriptions'])
        ->middleware(['throttle:20,1', 'tenant.module:front_door'])
        ->name($routeName('patient.portal.rx-lock'));

    // Note: /portal/prescriptions/{prescription} is registered above with the
    // other prescription patient routes so it stays next to /p/{token}.

    Route::get('/conditions', [ConditionTopicController::class, 'index'])
        ->middleware(['tenant.module:front_door'])
        ->name($routeName('conditions.index'));
    Route::get('/conditions/{slug}', [ConditionTopicController::class, 'show'])
        ->middleware(['tenant.module:front_door'])
        ->name($routeName('conditions.show'));

    Route::get('/departments', [ClinicContentController::class, 'departmentsIndex'])
        ->middleware(['tenant.module:front_door'])
        ->name($routeName('clinic.departments.index'));
    Route::get('/departments/{slug}', [ClinicContentController::class, 'departmentShow'])
        ->middleware(['tenant.module:front_door'])
        ->name($routeName('clinic.departments.show'));
    Route::get('/blog', [ClinicContentController::class, 'blogIndex'])
        ->middleware(['tenant.module:front_door'])
        ->name($routeName('clinic.blog.index'));
    Route::get('/blog/{slug}', [ClinicContentController::class, 'blogShow'])
        ->middleware(['tenant.module:front_door'])
        ->name($routeName('clinic.blog.show'));
    Route::get('/doctors', [ClinicContentController::class, 'doctorsIndex'])
        ->middleware(['tenant.module:front_door'])
        ->name($routeName('clinic.doctors.index'));
    Route::get('/doctors/{slug}', [ClinicContentController::class, 'doctorShow'])
        ->middleware(['tenant.module:front_door'])
        ->name($routeName('clinic.doctors.show'));

    Route::get('/{slug?}', [WebPageController::class, 'show'])
        ->middleware(['tenant.module:front_door'])
        // Exact-segment negative lookahead — (?!foo|bar$) only anchors the last alt.
        ->where('slug', '^(?!(?:tenant|admin|api|lang|bookings|portal|departments|blog|doctors|conditions|robots\\.txt|sitemap\\.xml)$).*$');
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
