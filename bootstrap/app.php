<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Production sits behind nginx/caddy → PHP on 127.0.0.1. Without this,
        // absolute URLs (ticket share, prescription links, SMS fallbacks that
        // read the request host) bake in localhost instead of the public domain.
        $middleware->trustProxies(at: '*');
        $middleware->prependToGroup('web', \App\Http\Middleware\InitializeTenancyForTenantHosts::class);
        // TEMPORARY diagnostic, no-ops unless AUTH_DEBUG=true. Remove with AuthDebugProvider.
        $middleware->appendToGroup('web', \App\Http\Middleware\SessionProbe::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
