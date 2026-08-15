<?php

namespace App\Providers;

use App\Contracts\SmsGateway;
use App\Contracts\WebPushSender;
use App\Http\Responses\FilamentLoginResponse;
use App\Support\EnglishFilamentLoader;
use App\Support\RuntimeDirectories;
use App\Support\TenancyUrl;
use App\Services\Sms\HttpSmsGateway;
use App\Services\Sms\LogSmsGateway;
use App\Services\WebPush\MinishlinkWebPushSender;
use App\Services\WebPush\NullWebPushSender;
use App\Services\WebPush\RecordingWebPushSender;
use Filament\Auth\Http\Responses\Contracts\LoginResponse as LoginResponseContract;
use Filament\Facades\Filament;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        RuntimeDirectories::ensure();

        $this->app->extend('translation.loader', function ($loader) {
            return new EnglishFilamentLoader($loader);
        });

        // Filament's stock login redirect targets `Panel::getUrl()`, which falls
        // back to the raw path pattern — `/{tenant}/admin` for the path panel.
        $this->app->bind(LoginResponseContract::class, FilamentLoginResponse::class);

        $this->app->singleton(SmsGateway::class, function () {
            return match (config('sms.driver', 'log')) {
                'log' => new LogSmsGateway,
                'http' => new HttpSmsGateway,
                default => throw new InvalidArgumentException(
                    'Unsupported SMS_DRIVER ['.config('sms.driver').']. Use log or http.'
                ),
            };
        });

        $this->app->singleton(RecordingWebPushSender::class);

        $this->app->singleton(WebPushSender::class, function ($app) {
            if ($app->environment('testing')) {
                return $app->make(RecordingWebPushSender::class);
            }

            $public = (string) config('webpush.vapid.public_key');
            $private = (string) config('webpush.vapid.private_key');

            if ($public === '' || $private === '') {
                return new NullWebPushSender;
            }

            return new MinishlinkWebPushSender;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Route::pattern('tenant', TenancyUrl::tenantSlugPattern());

        // Hero/video FileUpload allows 20 MB; Livewire's default temp rule is 12 MB.
        config([
            'livewire.temporary_file_upload.disk' => 'livewire-tmp',
            'livewire.temporary_file_upload.rules' => ['required', 'file', 'max:20480'],
        ]);

        $this->recoverExpiredGuestPagesSilently();

        \Illuminate\Support\Facades\View::composer(
            ['tenant.prescriptions.print', 'tenant.prescriptions.share'],
            function (): void {
                // The paper the patient leaves with is Bangla-first, even when
                // the doctor is using the English admin panel on this request.
                \Illuminate\Support\Facades\App::setLocale('bn');
            }
        );
    }

    /**
     * All three panels sit on the same host and share one session cookie, and
     * Filament regenerates the session (and with it the CSRF token) on every
     * login. So a login screen left open in another tab — or open longer than
     * `SESSION_LIFETIME` — submits a dead token and Livewire answers 419, which
     * the browser surfaces as "This page has expired. Would you like to refresh
     * the page?".
     *
     * There is nothing to lose on a guest screen, so reload it and let the
     * visitor carry on. Signed-in pages keep Livewire's own prompt: silently
     * reloading a half-finished page builder or booking form would throw away
     * the staff member's work.
     */
    private function recoverExpiredGuestPagesSilently(): void
    {
        FilamentView::registerRenderHook(
            PanelsRenderHook::BODY_END,
            fn (): string => Filament::auth()->check() ? '' : <<<'HTML'
                <script>
                    document.addEventListener('livewire:init', () => {
                        Livewire.hook('request', ({ fail }) => {
                            fail(({ status, preventDefault }) => {
                                if (status !== 419) {
                                    return
                                }

                                preventDefault()
                                window.location.reload()
                            })
                        })
                    })
                </script>
                HTML,
        );
    }
}
