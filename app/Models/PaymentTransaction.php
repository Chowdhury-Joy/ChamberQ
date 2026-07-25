<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class PaymentTransaction extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'booking_id',
        'gateway',
        'transaction_id',
        'amount',
        'status',
        'payload',
        'verified_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'verified_at' => 'datetime',
        'amount' => 'decimal:2',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }
}
