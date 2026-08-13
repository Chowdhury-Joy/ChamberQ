<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticatePatient
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! auth('patient')->check()) {
            return redirect()->guest('/me/login');
        }

        return $next($request);
    }
}
