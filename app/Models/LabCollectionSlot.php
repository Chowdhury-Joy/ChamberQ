<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class LabCollectionSlot extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'chamber_id',
        'day_of_week',
        'start_time',
        'end_time',
        'slot_cap',
    ];

    public function chamber()
    {
        return $this->belongsTo(Chamber::class, 'chamber_id');
    }
    
    public function bookings()
    {
        return $this->morphMany(Booking::class, 'bookable');
    }
}
