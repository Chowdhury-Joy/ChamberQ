<?php

namespace App\Http\Middleware;

use App\Exceptions\BookingUnavailableException;
use Closure;
use Illuminate\Http\Request;

/**
 * Blocks new bookings when the tenant's billing status refuses them.
 *
 * Deliberately narrow: past_due / suspended / read_only tenants keep their
 * public site and staff dashboard — only creating a new booking is refused.
 * Apply to booking-creating routes only, never globally.
 */
class EnsureTenantAcceptsBookings
{
    public function handle(Request $request, Closure $next)
    {
        if (tenancy()->initialized && tenant() && ! tenant()->acceptsBookings()) {
            throw BookingUnavailableException::bookingClosed();
        }

        return $next($request);
    }
}
