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
    public string $errorCode = 'unavailable';

    public static function dayOfWeekMismatch(bool $isLabSlot = false): self
    {
        return self::make(
            $isLabSlot
                ? __('The date you chose is not a collection day for this slot. Please pick one of the available dates.')
                : __('The date you chose is not a day this doctor sees patients. Please pick one of the available dates.'),
            'day_mismatch'
        );
    }

    public static function dateBlocked(): self
    {
        return self::make(
            __('The clinic is closed on the date you chose. Please pick another date.'),
            'blocked'
        );
    }

    public static function capacityExceeded(): self
    {
        return self::make(
            __('This session just filled up. Please pick another session or date.'),
            'capacity'
        );
    }

    public static function bookingClosed(): self
    {
        return self::make(
            __('Online booking is temporarily unavailable. Please call the clinic to book your appointment.'),
            'closed'
        );
    }

    public static function bookableUnavailable(): self
    {
        return self::make(
            __('That session is no longer available. Please choose another.'),
            'unavailable'
        );
    }

    public static function labTestUnavailable(): self
    {
        return self::make(
            __('One of the tests you selected is no longer available. Please review your selection.'),
            'lab_unavailable'
        );
    }

    public static function labTestsNotBookable(): self
    {
        return self::make(
            __('Lab tests cannot be booked here.'),
            'lab_not_bookable'
        );
    }

    public static function duplicateBooking(): self
    {
        return self::make(
            __('This phone number already has a booking for this session today.'),
            'duplicate'
        );
    }

    private static function make(string $message, string $code): self
    {
        $exception = new self($message);
        $exception->errorCode = $code;

        return $exception;
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
                'code' => $this->errorCode,
            ], 422);
        }

        return back()->withInput()->withErrors(['booking' => $this->getMessage()]);
    }
}
