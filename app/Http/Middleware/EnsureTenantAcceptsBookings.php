<?php

namespace App\Http\Middleware;

use App\Exceptions\BookingUnavailableException;
use Closure;
use Illuminate\Http\Request;

/**
 * Blocks new bookings for tenants whose billing status is `read_only`.
 *
 * Per the spec this is deliberately narrow: a read-only tenant keeps its public
 * site viewable and its staff dashboard usable — only the act of creating a new
 * booking is refused. Apply it to booking-creating routes only, never globally.
 */
class EnsureTenantAcceptsBookings
{
    public function handle(Request $request, Closure $next)
    {
        if (tenancy()->initialized && tenant('billing_status') === 'read_only') {
            throw BookingUnavailableException::bookingClosed();
        }

        return $next($request);
    }
}
