<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Commission extends Model
{
    public const TYPE_SETUP = 'setup';

    public const TYPE_MONTHLY = 'monthly';

    public const TYPE_YEAR_PREPAID = 'year_prepaid';

    public const PAYEE_MARKETER = 'marketer';

    public const PAYEE_MR = 'mr';

    public const STATUS_PENDING = 'pending_doctor_payment';

    public const STATUS_OWED = 'owed';

    public const STATUS_PAID = 'paid';

    public const STATUS_VOID = 'void';

    protected $fillable = [
        'marketer_id',
        'medical_representative_id',
        'payee_key',
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

    public static function payeeKeyForMarketer(int $marketerId): string
    {
        return self::PAYEE_MARKETER.':'.$marketerId;
    }

    public static function payeeKeyForMr(int $mrId): string
    {
        return self::PAYEE_MR.':'.$mrId;
    }

    public function marketer(): BelongsTo
    {
        return $this->belongsTo(Marketer::class);
    }

    public function medicalRepresentative(): BelongsTo
    {
        return $this->belongsTo(MedicalRepresentative::class);
    }

    public function payeeName(): string
    {
        if ($this->medical_representative_id) {
            return (string) ($this->medicalRepresentative?->name ?? 'Medical representative');
        }

        return (string) ($this->marketer?->display_name ?? 'Marketer');
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
