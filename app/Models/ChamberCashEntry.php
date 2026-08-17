<?php

namespace App\Models;

use App\Casts\DateOnly;
use App\Models\Concerns\BelongsToTenant;
use App\Services\CashCategoryService;
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

    public const CATEGORY_REFERRAL_PAYOUT = 'referral_payout';

    public const METHOD_CASH = 'cash';

    public const METHOD_BKASH = 'bkash';

    public const METHOD_NAGAD = 'nagad';

    public const METHOD_CARD = 'card';

    public const METHOD_BANK = 'bank';

    public const METHOD_BANGLA_QR = 'bangla_qr';

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
            self::CATEGORY_REFERRAL_PAYOUT => __('Referral payout'),
            self::CATEGORY_OTHER_EXPENSE => __('Other expense'),
        ];
    }

    /** @return array<string, string> */
    public static function paymentMethods(): array
    {
        return [
            self::METHOD_CASH => __('Cash'),
            ...self::onlineMethods(),
        ];
    }

    /** @return array<string, string> */
    public static function onlineMethods(): array
    {
        return [
            self::METHOD_BKASH => __('bKash'),
            self::METHOD_NAGAD => __('Nagad'),
            self::METHOD_BANK => __('Bank'),
            self::METHOD_BANGLA_QR => __('Bangla QR'),
            self::METHOD_CARD => __('Card'),
            self::METHOD_OTHER => __('Other'),
        ];
    }

    /** @return array<string, string> */
    public static function methods(): array
    {
        return [
            self::METHOD_CASH => __('Cash'),
            ...self::onlineMethods(),
            self::METHOD_MIXED => __('Cash + online'),
        ];
    }

    public function paymentMethodLabel(): string
    {
        if ($this->method === self::METHOD_MIXED) {
            if ($this->mobile_taka > 0 && filled($this->mobile_method)) {
                $online = self::onlineMethods()[$this->mobile_method] ?? $this->mobile_method;

                return __('Cash + online (:method)', ['method' => $online]);
            }

            return __('Cash + online');
        }

        return self::methods()[$this->method] ?? $this->method;
    }

    /** Patient procedure, visit type, or expense/income category — for Cashbook rows. */
    public function cashbookSubjectLabel(): string
    {
        if (! $this->isIncome()) {
            return app(CashCategoryService::class)->labelFor($this->category);
        }

        if ($this->category === self::CATEGORY_PATIENT) {
            if ($this->feeCatalogItem) {
                return $this->feeCatalogItem->label;
            }

            if (filled($this->fee_type)) {
                $doctor = $this->booking ? Doctor::resolveForBooking($this->booking) : null;
                $types = $doctor?->feeTypes() ?? [];

                if (isset($types[$this->fee_type])) {
                    return $types[$this->fee_type]['label'];
                }
            }
        }

        return app(CashCategoryService::class)->labelFor($this->category);
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
