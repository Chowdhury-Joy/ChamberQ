<?php

namespace App\Services;

use App\Models\CashCategory;
use App\Models\ChamberCashEntry;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CashCategoryService
{
    /**
     * @var list<array{type: string, code: string, name: string, is_locked: bool, sort_order: int}>
     */
    private const DEFAULTS = [
        ['type' => CashCategory::TYPE_INCOME, 'code' => ChamberCashEntry::CATEGORY_PATIENT, 'name' => 'Patient fee', 'is_locked' => true, 'sort_order' => 10],
        ['type' => CashCategory::TYPE_INCOME, 'code' => ChamberCashEntry::CATEGORY_WAIVED, 'name' => 'Waived', 'is_locked' => true, 'sort_order' => 20],
        ['type' => CashCategory::TYPE_INCOME, 'code' => ChamberCashEntry::CATEGORY_OTHER_INCOME, 'name' => 'Other income', 'is_locked' => false, 'sort_order' => 30],
        ['type' => CashCategory::TYPE_EXPENSE, 'code' => ChamberCashEntry::CATEGORY_RENT, 'name' => 'Rent', 'is_locked' => false, 'sort_order' => 10],
        ['type' => CashCategory::TYPE_EXPENSE, 'code' => ChamberCashEntry::CATEGORY_UTILITIES, 'name' => 'Utilities', 'is_locked' => false, 'sort_order' => 20],
        ['type' => CashCategory::TYPE_EXPENSE, 'code' => ChamberCashEntry::CATEGORY_SUPPLIES, 'name' => 'Supplies', 'is_locked' => false, 'sort_order' => 30],
        ['type' => CashCategory::TYPE_EXPENSE, 'code' => ChamberCashEntry::CATEGORY_SALARY, 'name' => 'Salary', 'is_locked' => true, 'sort_order' => 40],
        ['type' => CashCategory::TYPE_EXPENSE, 'code' => ChamberCashEntry::CATEGORY_TRANSPORT, 'name' => 'Transport', 'is_locked' => false, 'sort_order' => 50],
        ['type' => CashCategory::TYPE_EXPENSE, 'code' => ChamberCashEntry::CATEGORY_REFERRAL_PAYOUT, 'name' => 'Referral payout', 'is_locked' => true, 'sort_order' => 60],
        ['type' => CashCategory::TYPE_EXPENSE, 'code' => ChamberCashEntry::CATEGORY_OTHER_EXPENSE, 'name' => 'Other expense', 'is_locked' => false, 'sort_order' => 70],
    ];

    public function ensureDefaults(): void
    {
        if (! tenancy()->initialized) {
            return;
        }

        if (CashCategory::query()->exists()) {
            return;
        }

        foreach (self::DEFAULTS as $row) {
            CashCategory::create([
                'type' => $row['type'],
                'code' => $row['code'],
                'name' => __($row['name']),
                'is_active' => true,
                'is_builtin' => true,
                'is_locked' => $row['is_locked'],
                'sort_order' => $row['sort_order'],
            ]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function pickerOptions(string $type): array
    {
        $this->ensureDefaults();

        $exclude = $type === CashCategory::TYPE_INCOME
            ? CashCategory::AUTO_INCOME_CODES
            : CashCategory::AUTO_EXPENSE_CODES;

        return CashCategory::query()
            ->where('type', $type)
            ->where('is_active', true)
            ->whereNotIn('code', $exclude)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name', 'code')
            ->all();
    }

    public function labelFor(string $code): string
    {
        $this->ensureDefaults();

        $label = CashCategory::query()->where('code', $code)->value('name');

        if (filled($label)) {
            return $label;
        }

        return $this->fallbackLabel($code);
    }

    public function validateManualExpenseCategory(string $category): void
    {
        $this->assertManualCategory($category, CashCategory::TYPE_EXPENSE, CashCategory::AUTO_EXPENSE_CODES);
    }

    public function validateManualIncomeCategory(string $category): void
    {
        $this->assertManualCategory($category, CashCategory::TYPE_INCOME, CashCategory::AUTO_INCOME_CODES);
    }

    public function canDelete(CashCategory $category): bool
    {
        if ($category->is_locked) {
            return false;
        }

        return ! ChamberCashEntry::query()
            ->where('category', $category->code)
            ->exists();
    }

    public function createCustom(string $name, string $type): CashCategory
    {
        $this->ensureDefaults();

        $name = trim($name);

        if ($name === '') {
            throw new InvalidArgumentException(__('Category name is required.'));
        }

        if (! in_array($type, [CashCategory::TYPE_INCOME, CashCategory::TYPE_EXPENSE], true)) {
            throw new InvalidArgumentException(__('Unknown category type.'));
        }

        $maxSort = (int) CashCategory::query()->where('type', $type)->max('sort_order');

        return CashCategory::create([
            'type' => $type,
            'code' => $this->uniqueCodeFromName($name, $type),
            'name' => $name,
            'is_active' => true,
            'is_builtin' => false,
            'is_locked' => false,
            'sort_order' => $maxSort + 10,
        ]);
    }

    public function uniqueCodeFromName(string $name, string $type): string
    {
        $base = Str::slug($name, '_');

        if ($base === '') {
            $base = 'category';
        }

        $code = $base;
        $suffix = 2;

        while (
            CashCategory::query()->where('code', $code)->exists()
            || $this->isReservedCode($code)
        ) {
            $code = $base.'_'.$suffix;
            $suffix++;
        }

        return $code;
    }

    /**
     * @param  list<string>  $autoCodes
     */
    private function assertManualCategory(string $category, string $type, array $autoCodes): void
    {
        $this->ensureDefaults();

        if (in_array($category, $autoCodes, true)) {
            throw new InvalidArgumentException(__('That category cannot be chosen manually.'));
        }

        $exists = CashCategory::query()
            ->where('type', $type)
            ->where('code', $category)
            ->where('is_active', true)
            ->exists();

        if (! $exists) {
            throw new InvalidArgumentException(__('Unknown category.'));
        }
    }

    private function isReservedCode(string $code): bool
    {
        foreach (self::DEFAULTS as $row) {
            if ($row['code'] === $code) {
                return true;
            }
        }

        return false;
    }

    private function fallbackLabel(string $code): string
    {
        foreach (self::DEFAULTS as $row) {
            if ($row['code'] === $code) {
                return __($row['name']);
            }
        }

        return $code;
    }
}
