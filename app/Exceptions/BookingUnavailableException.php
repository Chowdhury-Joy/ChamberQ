<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\Request;

/**
 * Thrown when a booking cannot be made for a reason the patient can act on.
 *
 * Only exceptions of this type are safe to show to an anonymous visitor. Every
 * other throwable must surface as a 500 and be logged — catching bare
 * `Exception` in a controller and echoing `getMessage()` leaks internal detail
 * and hides real faults behind a 422.
 */
class BookingUnavailableException extends Exception
{
    public static function dayOfWeekMismatch(bool $isLabSlot = false): self
    {
        return new self($isLabSlot
            ? __('The date you chose is not a collection day for this slot. Please pick one of the available dates.')
            : __('The date you chose is not a day this doctor sees patients. Please pick one of the available dates.'));
    }

    public static function dateBlocked(): self
    {
        return new self(__('The clinic is closed on the date you chose. Please pick another date.'));
    }

    public static function capacityExceeded(): self
    {
        return new self(__('This session is fully booked for the date you chose. Please pick another date or session.'));
    }

    public static function bookingClosed(): self
    {
        return new self(__('Online booking is temporarily unavailable. Please call the clinic to book your appointment.'));
    }

    public static function bookableUnavailable(): self
    {
        return new self(__('That session is no longer available. Please choose another.'));
    }

    /**
     * Render consistently wherever it is thrown — including from middleware,
     * which runs before any controller try/catch could see it.
     */
    public function render(Request $request)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $this->getMessage(),
            ], 422);
        }

        return back()->withInput()->withErrors(['booking' => $this->getMessage()]);
    }
}
