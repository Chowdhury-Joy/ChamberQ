<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Marketer extends Model
{
    protected $fillable = [
        'user_id',
        'code',
        'display_name',
        'phone',
        'payout_account',
        'setup_commission_rate',
        'monthly_commission_rate',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'setup_commission_rate' => 'float',
            'monthly_commission_rate' => 'float',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(Commission::class);
    }

    public function discountCodes(): HasMany
    {
        return $this->hasMany(DiscountCode::class);
    }

    public function referralUrl(): string
    {
        $host = config('tenancy.central_domains')[0] ?? 'localhost';
        $scheme = app()->environment('local') ? 'http' : 'https';

        return $scheme.'://'.$host.'/?ref='.$this->code;
    }
}
