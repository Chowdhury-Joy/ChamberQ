<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReferringDoctor extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'name',
        'phone',
        'specialty',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(ReferralCommission::class);
    }

    public function displayLabel(): string
    {
        $label = $this->name;

        if (filled($this->specialty)) {
            $label .= ' ('.$this->specialty.')';
        }

        return $label;
    }
}
