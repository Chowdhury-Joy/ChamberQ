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
        // Production sits behind nginx/caddy → PHP on 127.0.0.1. Restrict in
        // production to your reverse-proxy IPs/CIDRs via TRUSTED_PROXIES; *
        // is fine only when PHP is unreachable except through that proxy.
        // Cannot call config() here — this closure runs before the config
        // service exists (e.g. composer post-autoload `package:discover`).
        // ProductionReadiness reads config('app.trusted_proxies') at deploy time.
        $raw = $_ENV['TRUSTED_PROXIES'] ?? $_SERVER['TRUSTED_PROXIES'] ?? getenv('TRUSTED_PROXIES');
        $trusted = is_string($raw) && $raw !== '' ? $raw : '*';
        $middleware->trustProxies(at: $trusted === '*' ? '*' : array_map('trim', explode(',', $trusted)));
        $middleware->prependToGroup('web', \App\Http\Middleware\ForceRequestRootUrl::class);
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
