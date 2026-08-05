<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * TEMPORARY diagnostic — remove with AuthDebugProvider.
 *
 * Runs after EncryptCookies + StartSession, so `$request->cookies` holds the
 * DECRYPTED session id the browser sent. Comparing that to the session the app
 * actually ended up using says whether the incoming session was honoured or
 * thrown away and replaced with a fresh one.
 */
class SessionProbe
{
    public function handle(Request $request, Closure $next): Response
    {
        // Never run under PHPUnit: tests that call `Log::spy()` swap the log
        // manager for a mock whose `channel()` returns null, and this probe
        // must not be able to break a test it has nothing to do with.
        if (app()->runningUnitTests() || ! env('AUTH_DEBUG', false) || ! $request->hasSession()) {
            return $next($request);
        }

        $cookieName = config('session.cookie');
        $sentId = $request->cookies->get($cookieName);
        $usedId = $request->session()->getId();

        $response = $next($request);

        Log::channel('single')->info('sessionprobe', [
            'url' => $request->path(),
            'method' => $request->method(),
            'sent_session_id' => is_string($sentId) ? substr($sentId, 0, 8) : null,
            'used_session_id' => substr($usedId, 0, 8),
            'honoured' => $sentId === $usedId,
            'file_existed' => is_string($sentId)
                ? file_exists(config('session.files').'/'.$sentId)
                : false,
            'session_keys' => array_keys($request->session()->all()),
            'status' => $response->getStatusCode(),
        ]);

        return $response;
    }
}
