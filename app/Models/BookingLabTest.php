<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Line item joining a booking to a lab test.
 *
 * Exists as a real pivot model so `price_at_booking` is consistently cast as
 * money everywhere it is read — totals and displayed prices must not depend on
 * whichever numeric type the database driver happened to return.
 */
class BookingLabTest extends Pivot
{
    protected $table = 'booking_lab_test';

    public $incrementing = true;

    protected $casts = [
        'price_at_booking' => 'decimal:2',
    ];
}
