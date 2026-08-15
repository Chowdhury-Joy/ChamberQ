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
        // Sign-out diagnostics, off unless AUTH_DEBUG=true (config/diagnostics.php).
        $middleware->appendToGroup('web', \App\Http\Middleware\SessionProbe::class);
        $middleware->alias([
            'tenant.module' => \App\Http\Middleware\EnsureTenantHasModule::class,
            'patient.auth' => \App\Http\Middleware\AuthenticatePatient::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
