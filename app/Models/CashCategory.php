<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class CashCategory extends Model
{
    use BelongsToTenant;

    public const TYPE_INCOME = 'income';

    public const TYPE_EXPENSE = 'expense';

    /** @var list<string> */
    public const AUTO_INCOME_CODES = [
        ChamberCashEntry::CATEGORY_PATIENT,
        ChamberCashEntry::CATEGORY_WAIVED,
    ];

    /** @var list<string> */
    public const AUTO_EXPENSE_CODES = [
        ChamberCashEntry::CATEGORY_SALARY,
        ChamberCashEntry::CATEGORY_REFERRAL_PAYOUT,
        ChamberCashEntry::CATEGORY_PATIENT_REFUND,
    ];

    protected $fillable = [
        'type',
        'code',
        'name',
        'is_active',
        'is_builtin',
        'is_locked',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_builtin' => 'boolean',
        'is_locked' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function isIncome(): bool
    {
        return $this->type === self::TYPE_INCOME;
    }

    public function isExpense(): bool
    {
        return $this->type === self::TYPE_EXPENSE;
    }
}
