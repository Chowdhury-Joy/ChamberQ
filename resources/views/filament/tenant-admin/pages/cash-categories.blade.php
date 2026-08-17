<x-filament-panels::page>
    <style>
        .cash-cat-tabs {
            display: inline-flex;
            padding: 0.25rem;
            gap: 0.125rem;
            margin-bottom: 1rem;
            background-color: var(--gray-100);
            border-radius: 0.625rem;
            border: 1px solid var(--gray-200);
        }
        .dark .cash-cat-tabs {
            background-color: var(--gray-800);
            border-color: var(--gray-700);
        }
        .cash-cat-tab {
            appearance: none;
            border: 0;
            background: transparent;
            cursor: pointer;
            padding: 0.5rem 0.875rem;
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--gray-600);
            border-radius: 0.5rem;
        }
        .cash-cat-tab.is-active {
            background-color: var(--color-white);
            color: var(--gray-950);
        }
        .dark .cash-cat-tab.is-active {
            background-color: var(--gray-700);
            color: var(--color-white);
        }
        .cash-cat-lede {
            margin: 0 0 1rem;
            font-size: 0.875rem;
            color: var(--gray-600);
        }
    </style>

    <p class="cash-cat-lede">
        {{ __('Headings for money in and money out. Hiding a heading does not change old cashbook rows — it only removes it from the dropdown when staff add new entries.') }}
    </p>

    <div class="cash-cat-tabs" role="tablist" aria-label="{{ __('Category type') }}">
        <button
            type="button"
            wire:click="$set('categoryType', @js(\App\Models\CashCategory::TYPE_INCOME))"
            @class(['cash-cat-tab', 'is-active' => $categoryType === \App\Models\CashCategory::TYPE_INCOME])
            role="tab"
            aria-selected="{{ $categoryType === \App\Models\CashCategory::TYPE_INCOME ? 'true' : 'false' }}"
        >
            {{ __('Income') }}
        </button>
        <button
            type="button"
            wire:click="$set('categoryType', @js(\App\Models\CashCategory::TYPE_EXPENSE))"
            @class(['cash-cat-tab', 'is-active' => $categoryType === \App\Models\CashCategory::TYPE_EXPENSE])
            role="tab"
            aria-selected="{{ $categoryType === \App\Models\CashCategory::TYPE_EXPENSE ? 'true' : 'false' }}"
        >
            {{ __('Expense') }}
        </button>
    </div>

    {{ $this->table }}
</x-filament-panels::page>
