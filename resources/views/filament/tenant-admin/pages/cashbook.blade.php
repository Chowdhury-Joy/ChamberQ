<x-filament-panels::page>
    @php
        $summary = $this->getSummary();
        $dateFieldLabel = match ($period) {
            'week' => __('Any day in week'),
            'month' => __('Any day in month'),
            default => __('Date'),
        };
    @endphp

    <style>
        .cash-book { display: flex; flex-direction: column; gap: 1.5rem; }
        .cash-toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: stretch;
            gap: 1rem 1.25rem;
            padding: 1rem 1.25rem;
            background-color: var(--color-white);
            border: 1px solid var(--gray-200);
            border-radius: 0.75rem;
        }
        .dark .cash-toolbar {
            background-color: var(--gray-900);
            border-color: var(--gray-800);
        }
        .cash-toolbar-controls {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            gap: 1rem;
            flex: 1 1 18rem;
            min-width: 0;
        }
        .cash-field { display: flex; flex-direction: column; gap: 0.375rem; }
        .cash-field-label {
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.02em;
            text-transform: uppercase;
            color: var(--gray-500);
        }
        .cash-period-tabs {
            display: inline-flex;
            padding: 0.25rem;
            gap: 0.125rem;
            background-color: var(--gray-100);
            border-radius: 0.625rem;
            border: 1px solid var(--gray-200);
        }
        .dark .cash-period-tabs {
            background-color: var(--gray-800);
            border-color: var(--gray-700);
        }
        .cash-period-tab {
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
        .cash-period-tab.is-active {
            background-color: var(--color-white);
            color: var(--gray-950);
        }
        .dark .cash-period-tab.is-active {
            background-color: var(--gray-700);
            color: var(--color-white);
        }
        .cash-control {
            min-width: 11rem;
            padding: 0.5rem 0.75rem;
            border: 1px solid var(--gray-300);
            border-radius: 0.5rem;
            background: var(--color-white);
            color: var(--gray-950);
        }
        .dark .cash-control {
            background: var(--gray-800);
            border-color: var(--gray-700);
            color: var(--color-white);
        }
        .cash-period-summary {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.5rem 0.75rem;
            color: var(--gray-600);
            font-size: 0.875rem;
        }
        .cash-period-summary-label {
            display: block;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            color: var(--gray-500);
        }
        .cash-period-summary-value { font-weight: 600; color: var(--gray-950); }
        .dark .cash-period-summary-value { color: var(--color-white); }
        .cash-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 1rem;
        }
        .cash-kpi {
            padding: 1rem 1.15rem;
            background: var(--color-white);
            border: 1px solid var(--gray-200);
            border-radius: 0.75rem;
            border-left: 4px solid var(--kpi-accent, var(--primary-600));
        }
        .dark .cash-kpi {
            background: var(--gray-900);
            border-color: var(--gray-800);
        }
        .cash-kpi-label {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.02em;
            color: var(--gray-500);
        }
        .cash-kpi-value {
            margin-top: 0.35rem;
            font-size: 1.75rem;
            font-weight: 700;
            font-variant-numeric: tabular-nums;
            color: var(--gray-950);
        }
        .dark .cash-kpi-value { color: var(--color-white); }
        .cash-kpi-hint { font-size: 0.8125rem; color: var(--gray-500); }
        .cash-lede {
            margin: 0;
            font-size: 0.875rem;
            color: var(--gray-600);
        }
        @media (max-width: 640px) {
            .cash-grid { grid-template-columns: 1fr; }
            .cash-period-tabs { width: 100%; }
            .cash-period-tab { flex: 1 1 0; }
            .cash-control { min-width: 100%; width: 100%; }
        }
        @media (min-width: 641px) and (max-width: 900px) {
            .cash-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
    </style>

    <div class="cash-book">
        <p class="cash-lede">
            {{ __('Money in and money out at the desk. Patients still pay at the chamber — this is the khata, not an online payment.') }}
        </p>

        <div class="cash-toolbar" wire:key="cash-toolbar-{{ $period }}-{{ $anchorDate }}">
            <div class="cash-toolbar-controls">
                <div class="cash-field">
                    <span class="cash-field-label" id="cash-period-label">{{ __('Period') }}</span>
                    <div class="cash-period-tabs" role="group" aria-labelledby="cash-period-label">
                        <button type="button" wire:click="$set('period', 'day')" @class(['cash-period-tab', 'is-active' => $period === 'day'])>
                            {{ __('Day') }}
                        </button>
                        <button type="button" wire:click="$set('period', 'week')" @class(['cash-period-tab', 'is-active' => $period === 'week'])>
                            {{ __('Week') }}
                        </button>
                        <button type="button" wire:click="$set('period', 'month')" @class(['cash-period-tab', 'is-active' => $period === 'month'])>
                            {{ __('Month') }}
                        </button>
                    </div>
                </div>
                <div class="cash-field">
                    <label for="cash-report-date" class="cash-field-label">{{ $dateFieldLabel }}</label>
                    <input id="cash-report-date" type="date" wire:model.live="anchorDate" class="cash-control" />
                </div>
            </div>
            <div class="cash-period-summary">
                <div>
                    <span class="cash-period-summary-label">{{ __('Showing') }}</span>
                    <span class="cash-period-summary-value">{{ $this->getPeriodLabel() }}</span>
                </div>
            </div>
        </div>

        <div class="cash-grid">
            <div class="cash-kpi" style="--kpi-accent: var(--success-600);">
                <div class="cash-kpi-label">{{ __('Income') }}</div>
                <div class="cash-kpi-value">{{ $this->formatTaka($summary['income']) }}</div>
                <div class="cash-kpi-hint">{{ __('Fees collected at the desk') }}</div>
            </div>
            <div class="cash-kpi" style="--kpi-accent: var(--danger-600);">
                <div class="cash-kpi-label">{{ __('Expense') }}</div>
                <div class="cash-kpi-value">{{ $this->formatTaka($summary['expense']) }}</div>
                <div class="cash-kpi-hint">{{ __('Rent, supplies, salary, and the rest') }}</div>
            </div>
            <div class="cash-kpi" style="--kpi-accent: var(--primary-600);">
                <div class="cash-kpi-label">{{ __('Net') }}</div>
                <div class="cash-kpi-value">{{ $this->formatTaka($summary['net']) }}</div>
                <div class="cash-kpi-hint">{{ __('Income minus expense') }}</div>
            </div>
            <div class="cash-kpi" style="--kpi-accent: var(--warning-600);">
                <div class="cash-kpi-label">{{ __('Waived') }}</div>
                <div class="cash-kpi-value">{{ $this->formatTaka($summary['waived_amount']) }}</div>
                <div class="cash-kpi-hint">
                    @if ($summary['waived_count'] > 0)
                        {{ trans_choice(':count patient|:count patients', $summary['waived_count'], ['count' => $summary['waived_count']]) }}
                        · {{ __('Not collected at the desk') }}
                    @else
                        {{ __('Not collected at the desk') }}
                    @endif
                </div>
            </div>
        </div>

        {{ $this->table }}
    </div>
</x-filament-panels::page>
