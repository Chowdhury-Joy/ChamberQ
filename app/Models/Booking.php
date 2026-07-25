<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    use HasUuids, BelongsToTenant;

    protected $fillable = [
        'bookable_type',
        'bookable_id',
        'booking_date',
        'patient_name',
        'patient_phone',
        'serial_number',
        'status',
        'payment_status',
        'payment_reference',
    ];
    
    public function bookable()
    {
        return $this->morphTo();
    }
}
