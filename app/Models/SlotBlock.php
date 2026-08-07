<?php

namespace App\Models;

use App\Casts\DateOnly;
use App\Models\Concerns\BelongsToTenant;
use App\Services\SlotBlockService;
use Illuminate\Database\Eloquent\Model;

class SlotBlock extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'chamber_id',
        'doctor_id',
        'date',
        'reason',
    ];

    protected $casts = [
        'date' => DateOnly::class,
    ];

    protected static function booted(): void
    {
        // Blocking a date cancels what it invalidates. Doing this on the model
        // rather than inside one Filament page means every path — admin UI,
        // console, a future API — behaves identically and none of them can
        // leave a patient holding a serial for a day the clinic is shut.
        static::created(function (self $block) {
            app(SlotBlockService::class)->cancelAffected($block);
        });
    }

    public function chamber()
    {
        return $this->belongsTo(Chamber::class);
    }

    public function doctor()
    {
        return $this->belongsTo(Doctor::class);
    }

    /** Bookings this block cancelled — the "who still needs telling" list. */
    public function cancelledBookings()
    {
        return $this->hasMany(Booking::class);
    }
}
