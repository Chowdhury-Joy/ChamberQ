<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BillingPayment extends Model
{
    public const TYPE_SETUP = 'setup';

    public const TYPE_MONTHLY = 'monthly';

    protected $fillable = [
        'tenant_id',
        'type',
        'period',
        'list_amount',
        'discount_amount',
        'amount_paid',
        'discount_code_id',
        'confirmed_by',
        'confirmed_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'confirmed_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function discountCode(): BelongsTo
    {
        return $this->belongsTo(DiscountCode::class);
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function commission(): HasOne
    {
        return $this->hasOne(Commission::class);
    }
}
