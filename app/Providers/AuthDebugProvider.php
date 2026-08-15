<?php

namespace App\Providers;

use Illuminate\Auth\Events\Authenticated;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

/**
 * Sign-out diagnostics, off unless `AUTH_DEBUG=true` (config/diagnostics.php).
 * Records who is authenticated on each request and, crucially, what
 * caused a logout, so the next occurrence explains itself instead of being
 * guessed at.
 */
class AuthDebugProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Never run under PHPUnit — see SessionProbe for why (Log::spy()).
        //
        // `config()`, not `env()`: a deployment runs `config:cache`, after which
        // Laravel does not load `.env` at all and `env()` here returns null — so
        // this switch could never be turned on in production, which is the only
        // place the sign-out it was built to explain has ever been reported.
        if ($this->app->runningUnitTests() || ! config('diagnostics.auth')) {
            return;
        }

        Event::listen(Login::class, function (Login $event): void {
            Log::channel('single')->info('authdebug.login', $this->context([
                'user_id' => $event->user->getAuthIdentifier(),
                'guard' => $event->guard,
            ]));
        });

        Event::listen(Logout::class, function (Logout $event): void {
            Log::channel('single')->warning('authdebug.logout', $this->context([
                'user_id' => $event->user?->getAuthIdentifier(),
                'guard' => $event->guard,
                'called_from' => $this->callerTrace(),
            ]));
        });

        Event::listen(Authenticated::class, function (Authenticated $event): void {
            Log::channel('single')->debug('authdebug.authenticated', $this->context([
                'user_id' => $event->user->getAuthIdentifier(),
            ]));
        });
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function context(array $extra): array
    {
        $request = request();

        return [
            ...$extra,
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'referer' => $request->headers->get('referer'),
            'session_id' => $request->hasSession() ? substr($request->session()->getId(), 0, 8) : null,
            'has_session_cookie' => $request->cookies->has(config('session.cookie')),
            'tenant' => tenancy()->initialized ? tenant('id') : null,
        ];
    }

    /**
     * The application frames that led here — enough to tell
     * AuthenticateSession's forced logout apart from a real sign-out.
     *
     * @return list<string>
     */
    private function callerTrace(): array
    {
        $frames = [];

        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 40) as $frame) {
            $file = $frame['file'] ?? '';

            if ($file === '' || str_contains($file, '/vendor/laravel/framework/src/Illuminate/Events')) {
                continue;
            }

            $frames[] = str_replace(base_path().'/', '', $file).':'.($frame['line'] ?? '?');

            if (count($frames) >= 12) {
                break;
            }
        }

        return $frames;
    }
}
