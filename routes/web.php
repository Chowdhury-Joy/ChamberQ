<?php

use App\Http\Controllers\FindDoctorController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MarketingController;
use App\Http\Controllers\PatientAccountController;
use App\Http\Controllers\PatientAuthController;
use App\Http\Controllers\SeoController;
use App\Http\Middleware\CaptureReferralParams;
use App\Http\Middleware\Localization;
use Illuminate\Support\Facades\Route;

$centralDomains = config('tenancy.central_domains');

if (blank($centralDomains)) {
    throw new RuntimeException('tenancy.central_domains is empty — no central routes would be registered.');
}

foreach ($centralDomains as $domain) {
    Route::domain($domain)->middleware([CaptureReferralParams::class, Localization::class])->group(function () {
        Route::get('/', [MarketingController::class, 'home']);

        Route::get('/robots.txt', [SeoController::class, 'robots']);
        Route::get('/sitemap.xml', [SeoController::class, 'sitemap']);

        Route::get('/lang/{locale}', [LocaleController::class, 'switch']);

        Route::get('/find', [FindDoctorController::class, 'index'])->name('patient.find');

        Route::get('/me/login', [PatientAuthController::class, 'showLogin'])->name('patient.login');
        Route::post('/me/login/otp', [PatientAuthController::class, 'sendOtp'])
            ->middleware('throttle:5,10')
            ->name('patient.otp.send');
        Route::post('/me/login/verify', [PatientAuthController::class, 'verify'])
            ->middleware('throttle:10,10')
            ->name('patient.otp.verify');
        Route::post('/me/logout', [PatientAuthController::class, 'logout'])
            ->name('patient.logout');

        Route::middleware('patient.auth')->group(function () {
            Route::get('/me', [PatientAccountController::class, 'serials'])->name('patient.serials');
            Route::get('/me/history', [PatientAccountController::class, 'history'])->name('patient.history');
            Route::get('/me/prescriptions/{prescription}', [PatientAccountController::class, 'showPrescription'])
                ->name('patient.prescription');
        });
    });
}
