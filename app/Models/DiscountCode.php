<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiscountCode extends Model
{
    protected $fillable = [
        'code',
        'label',
        'setup_percent',
        'monthly_percent',
        'marketer_id',
        'is_active',
        'starts_at',
        'ends_at',
        'max_redemptions',
        'redemption_count',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function marketer(): BelongsTo
    {
        return $this->belongsTo(Marketer::class);
    }

    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }

    public function isValidNow(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $now = now();

        if ($this->starts_at && $now->lt($this->starts_at)) {
            return false;
        }

        if ($this->ends_at && $now->gt($this->ends_at)) {
            return false;
        }

        if ($this->max_redemptions !== null && $this->redemption_count >= $this->max_redemptions) {
            return false;
        }

        return true;
    }
}
