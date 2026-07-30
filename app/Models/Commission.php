<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Commission extends Model
{
    public const TYPE_SETUP = 'setup';

    public const TYPE_MONTHLY = 'monthly';

    public const STATUS_PENDING = 'pending_doctor_payment';

    public const STATUS_OWED = 'owed';

    public const STATUS_PAID = 'paid';

    public const STATUS_VOID = 'void';

    protected $fillable = [
        'marketer_id',
        'tenant_id',
        'billing_payment_id',
        'type',
        'period',
        'base_amount',
        'rate',
        'commission_amount',
        'status',
        'paid_at',
        'payout_note',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'float',
            'paid_at' => 'datetime',
        ];
    }

    public function marketer(): BelongsTo
    {
        return $this->belongsTo(Marketer::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function billingPayment(): BelongsTo
    {
        return $this->belongsTo(BillingPayment::class);
    }
}
