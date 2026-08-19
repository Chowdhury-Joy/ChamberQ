<?php

namespace App\Models;

use App\Casts\DateOnly;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferralCommission extends Model
{
    use BelongsToTenant;

    public const KIND_VISIT = 'visit';

    public const KIND_INTERVENTION = 'intervention';

    public const KIND_MSK = 'msk';

    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_VOID = 'void';

    protected $fillable = [
        'referring_doctor_id',
        'booking_id',
        'income_cash_entry_id',
        'kind',
        'amount_taka',
        'status',
        'occurred_on',
        'paid_at',
        'paid_by',
        'payout_cash_entry_id',
        'note',
    ];

    protected $casts = [
        'amount_taka' => 'integer',
        'occurred_on' => DateOnly::class,
        'paid_at' => 'datetime',
    ];

    /** @return array<string, string> */
    public static function kindOptions(): array
    {
        return [
            self::KIND_VISIT => __('Visit'),
            self::KIND_INTERVENTION => __('Intervention'),
            self::KIND_MSK => __('MSK scan'),
        ];
    }

    /** @return array<string, string> */
    public static function statusOptions(): array
    {
        return [
            self::STATUS_PENDING => __('Pending'),
            self::STATUS_PAID => __('Paid'),
            self::STATUS_VOID => __('Void'),
        ];
    }

    public function referringDoctor(): BelongsTo
    {
        return $this->belongsTo(ReferringDoctor::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function incomeCashEntry(): BelongsTo
    {
        return $this->belongsTo(ChamberCashEntry::class, 'income_cash_entry_id');
    }

    public function payoutCashEntry(): BelongsTo
    {
        return $this->belongsTo(ChamberCashEntry::class, 'payout_cash_entry_id');
    }

    public function paidByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
