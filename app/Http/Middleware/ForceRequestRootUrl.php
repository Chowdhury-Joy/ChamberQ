<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Generated URLs (Livewire script/update, Filament assets) must use the host
 * and port the browser is on. APP_URL is often http://localhost while staff
 * open http://127.0.0.1:8000 — a script then talks to the wrong app.
 */
class ForceRequestRootUrl
{
    public function handle(Request $request, Closure $next): Response
    {
        URL::forceRootUrl($request->root());
        URL::forceScheme($request->getScheme());

        return $next($request);
    }
}
