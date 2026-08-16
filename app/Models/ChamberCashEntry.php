<?php

namespace App\Models;

use App\Casts\DateOnly;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChamberCashEntry extends Model
{
    use BelongsToTenant, HasUuids;

    public const DIRECTION_INCOME = 'income';

    public const DIRECTION_EXPENSE = 'expense';

    public const CATEGORY_PATIENT = 'patient';

    public const CATEGORY_WAIVED = 'waived';

    public const CATEGORY_OTHER_INCOME = 'other_income';

    public const CATEGORY_RENT = 'rent';

    public const CATEGORY_UTILITIES = 'utilities';

    public const CATEGORY_SUPPLIES = 'supplies';

    public const CATEGORY_SALARY = 'salary';

    public const CATEGORY_TRANSPORT = 'transport';

    public const CATEGORY_OTHER_EXPENSE = 'other_expense';

    public const METHOD_CASH = 'cash';

    public const METHOD_BKASH = 'bkash';

    public const METHOD_NAGAD = 'nagad';

    public const METHOD_CARD = 'card';

    public const METHOD_OTHER = 'other';

    public const METHOD_MIXED = 'mixed';

    protected $fillable = [
        'direction',
        'amount',
        'list_price_taka',
        'cash_taka',
        'mobile_taka',
        'mobile_method',
        'discount_taka',
        'clinic_share_taka',
        'doctor_share_taka',
        'fee_catalog_item_id',
        'fee_type',
        'category',
        'method',
        'booking_id',
        'chamber_id',
        'doctor_id',
        'recorded_by',
        'occurred_on',
        'note',
    ];

    protected $casts = [
        'amount' => 'integer',
        'list_price_taka' => 'integer',
        'cash_taka' => 'integer',
        'mobile_taka' => 'integer',
        'discount_taka' => 'integer',
        'clinic_share_taka' => 'integer',
        'doctor_share_taka' => 'integer',
        'occurred_on' => DateOnly::class,
    ];

    /** @return array<string, string> */
    public static function incomeCategories(): array
    {
        return [
            self::CATEGORY_PATIENT => __('Patient fee'),
            self::CATEGORY_WAIVED => __('Waived'),
            self::CATEGORY_OTHER_INCOME => __('Other income'),
        ];
    }

    /** @return array<string, string> */
    public static function expenseCategories(): array
    {
        return [
            self::CATEGORY_RENT => __('Rent'),
            self::CATEGORY_UTILITIES => __('Utilities'),
            self::CATEGORY_SUPPLIES => __('Supplies'),
            self::CATEGORY_SALARY => __('Salary'),
            self::CATEGORY_TRANSPORT => __('Transport'),
            self::CATEGORY_OTHER_EXPENSE => __('Other expense'),
        ];
    }

    /** @return array<string, string> */
    public static function methods(): array
    {
        return [
            self::METHOD_CASH => __('Cash'),
            self::METHOD_BKASH => __('bKash'),
            self::METHOD_NAGAD => __('Nagad'),
            self::METHOD_CARD => __('Card'),
            self::METHOD_OTHER => __('Other'),
            self::METHOD_MIXED => __('Cash + mobile'),
        ];
    }

    public function feeCatalogItem(): BelongsTo
    {
        return $this->belongsTo(FeeCatalogItem::class);
    }

    public function usesStationsTill(): bool
    {
        return $this->fee_catalog_item_id !== null;
    }

    public function isIncome(): bool
    {
        return $this->direction === self::DIRECTION_INCOME;
    }

    public function isWaived(): bool
    {
        return $this->category === self::CATEGORY_WAIVED;
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function chamber(): BelongsTo
    {
        return $this->belongsTo(Chamber::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
