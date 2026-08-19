<?php

namespace App\Models;

use App\Casts\DateOnly;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PharmacyDoctorCommission extends Model
{
    use BelongsToTenant;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_VOID = 'void';

    protected $fillable = [
        'doctor_id',
        'pharmacy_sale_id',
        'pharmacy_sale_item_id',
        'shop_cut_taka',
        'percent',
        'amount_taka',
        'status',
        'occurred_on',
        'paid_at',
        'paid_by',
        'payout_cash_entry_id',
    ];

    protected $casts = [
        'shop_cut_taka' => 'integer',
        'percent' => 'integer',
        'amount_taka' => 'integer',
        'occurred_on' => DateOnly::class,
        'paid_at' => 'datetime',
    ];

    /** @return array<string, string> */
    public static function statusOptions(): array
    {
        return [
            self::STATUS_PENDING => __('Pending'),
            self::STATUS_PAID => __('Paid'),
            self::STATUS_VOID => __('Void'),
        ];
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(PharmacySale::class, 'pharmacy_sale_id');
    }

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(PharmacySaleItem::class, 'pharmacy_sale_item_id');
    }
}
