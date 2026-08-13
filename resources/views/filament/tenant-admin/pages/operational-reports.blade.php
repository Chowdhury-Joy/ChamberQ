<x-filament-panels::page>
    @php
        $totals = $this->getTotals();
        $statusMeta = $this->getStatusMeta();
        $queueCount = $this->getQueueCount();
        $completionRate = $this->getCompletionRate();
        $dateFieldLabel = match ($period) {
            'week' => __('Any day in week'),
            'month' => __('Any day in month'),
            default => __('Date'),
        };
    @endphp

    {{--
        This panel ships Filament's precompiled stylesheet, so arbitrary Tailwind
        utilities are not available here. Layout is written against Filament's own
        CSS variables so it tracks the panel theme and dark mode.
    --}}
    <style>
        .ops-report { display: flex; flex-direction: column; gap: 1.5rem; }

        /* —— Filter toolbar —— */
        .ops-toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: stretch;
            gap: 1rem 1.25rem;
            padding: 1rem 1.25rem;
            background-color: var(--color-white);
            border: 1px solid var(--gray-200);
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgb(0 0 0 / 0.06);
        }
        .dark .ops-toolbar {
            background-color: var(--gray-900);
            border-color: var(--gray-800);
        }
        .ops-toolbar-controls {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            gap: 1rem;
            flex: 1 1 18rem;
            min-width: 0;
        }
        .ops-field { display: flex; flex-direction: column; gap: 0.375rem; }
        .ops-field-label {
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.02em;
            text-transform: uppercase;
            color: var(--gray-500);
        }
        .dark .ops-field-label { color: var(--gray-400); }

        .ops-period-tabs {
            display: inline-flex;
            padding: 0.25rem;
            gap: 0.125rem;
            background-color: var(--gray-100);
            border-radius: 0.625rem;
            border: 1px solid var(--gray-200);
        }
        .dark .ops-period-tabs {
            background-color: var(--gray-800);
            border-color: var(--gray-700);
        }
        .ops-period-tab {
            appearance: none;
            border: 0;
            background: transparent;
            cursor: pointer;
            padding: 0.5rem 0.875rem;
            font-size: 0.875rem;
            font-weight: 600;
            line-height: 1.25;
            color: var(--gray-600);
            border-radius: 0.5rem;
            transition: background-color 0.12s ease, color 0.12s ease, box-shadow 0.12s ease;
        }
        .ops-period-tab:hover { color: var(--gray-950); }
        .dark .ops-period-tab { color: var(--gray-300); }
        .dark .ops-period-tab:hover { color: var(--color-white); }
        .ops-period-tab.is-active {
            background-color: var(--color-white);
            color: var(--primary-700);
            box-shadow: 0 1px 2px rgb(0 0 0 / 0.08);
        }
        .dark .ops-period-tab.is-active {
            background-color: var(--gray-950);
            color: var(--primary-400);
            box-shadow: 0 0 0 1px var(--gray-700);
        }
        .ops-period-tab:focus-visible {
            outline: 2px solid var(--primary-500);
            outline-offset: 1px;
        }

        .ops-control {
            min-width: 11rem;
            padding: 0.5rem 0.75rem;
            font-size: 0.875rem;
            color: var(--gray-950);
            background-color: var(--color-white);
            border: 1px solid var(--gray-300);
            border-radius: 0.5rem;
            box-shadow: 0 1px 2px rgb(0 0 0 / 0.05);
        }
        .ops-control:focus {
            outline: 2px solid var(--primary-500);
            outline-offset: -1px;
            border-color: var(--primary-500);
        }
        .dark .ops-control {
            color: var(--color-white);
            background-color: var(--gray-950);
            border-color: var(--gray-700);
        }

        .ops-period-summary {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-inline-start: auto;
            padding: 0.75rem 1rem;
            min-width: min(100%, 16rem);
            background-color: var(--primary-50);
            border: 1px solid color-mix(in srgb, var(--primary-500) 22%, transparent);
            border-radius: 0.625rem;
        }
        .dark .ops-period-summary {
            background-color: color-mix(in srgb, var(--primary-500) 16%, var(--gray-900));
            border-color: color-mix(in srgb, var(--primary-500) 35%, var(--gray-700));
        }
        .ops-period-summary-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            width: 2.25rem;
            height: 2.25rem;
            border-radius: 0.5rem;
            color: var(--primary-700);
            background-color: var(--color-white);
            border: 1px solid color-mix(in srgb, var(--primary-500) 20%, transparent);
        }
        .dark .ops-period-summary-icon {
            color: var(--primary-300);
            background-color: var(--gray-950);
            border-color: var(--gray-700);
        }
        .ops-period-summary-icon svg { width: 1.125rem; height: 1.125rem; }
        .ops-period-summary-text { display: flex; flex-direction: column; gap: 0.125rem; min-width: 0; }
        .ops-period-summary-label {
            font-size: 0.6875rem;
            font-weight: 600;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: var(--primary-700);
        }
        .dark .ops-period-summary-label { color: var(--primary-300); }
        .ops-period-summary-value {
            font-size: 0.9375rem;
            font-weight: 700;
            line-height: 1.25;
            color: var(--gray-950);
        }
        .dark .ops-period-summary-value { color: var(--color-white); }
        .ops-period-summary-tz {
            font-size: 0.75rem;
            color: var(--gray-500);
        }
        .dark .ops-period-summary-tz { color: var(--gray-400); }

        /* —— Single 3×3 metric grid —— */
        .ops-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 0.75rem;
        }
        .ops-kpi {
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            gap: 0.625rem;
            padding: 1.125rem 1.25rem;
            background-color: var(--color-white);
            border: 1px solid var(--gray-200);
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgb(0 0 0 / 0.06);
        }
        .ops-kpi::before {
            content: '';
            position: absolute;
            inset-block: 0;
            inset-inline-start: 0;
            width: 3px;
            background-color: var(--ops-accent);
        }
        .dark .ops-kpi { background-color: var(--gray-900); border-color: var(--gray-800); }
        .ops-kpi.ops-kpi-empty { opacity: 0.5; }

        .ops-kpi-head { display: flex; align-items: center; gap: 0.5rem; min-width: 0; }
        .ops-kpi-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            width: 1.75rem;
            height: 1.75rem;
            border-radius: 0.5rem;
            color: var(--ops-accent);
            background-color: var(--ops-accent-soft);
        }
        .dark .ops-kpi-icon {
            background-color: color-mix(in srgb, var(--ops-accent) 18%, transparent);
        }
        .ops-kpi-icon svg { width: 1rem; height: 1rem; }
        .ops-kpi-label {
            font-size: 0.8125rem;
            font-weight: 600;
            color: var(--gray-600);
            line-height: 1.2;
            overflow-wrap: anywhere;
        }
        .dark .ops-kpi-label { color: var(--gray-400); }
        .ops-kpi-value {
            font-size: 1.875rem;
            font-weight: 700;
            line-height: 1.1;
            color: var(--gray-950);
            font-variant-numeric: tabular-nums;
        }
        .dark .ops-kpi-value { color: var(--color-white); }
        .ops-kpi-hint { font-size: 0.8125rem; color: var(--gray-500); }
        .dark .ops-kpi-hint { color: var(--gray-500); }

        .ops-empty {
            margin: 0;
            padding: 0.75rem 1rem;
            font-size: 0.875rem;
            color: var(--gray-600);
            background-color: var(--gray-50);
            border: 1px solid var(--gray-200);
            border-radius: 0.625rem;
        }
        .dark .ops-empty {
            color: var(--gray-300);
            background-color: var(--gray-900);
            border-color: var(--gray-800);
        }

        .ops-table-scroll { overflow-x: auto; }
        .ops-table {
            width: 100%;
            min-width: 44rem;
            border-collapse: collapse;
            font-size: 0.875rem;
        }
        .ops-table th,
        .ops-table td {
            padding: 0.75rem 0.875rem;
            text-align: end;
            white-space: nowrap;
            border-bottom: 1px solid var(--gray-200);
        }
        .dark .ops-table th,
        .dark .ops-table td { border-color: var(--gray-800); }
        .ops-table th:first-child,
        .ops-table td:first-child { text-align: start; }
        .ops-table thead th {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            color: var(--gray-500);
            background-color: var(--gray-50);
        }
        .dark .ops-table thead th { background-color: var(--gray-900); color: var(--gray-400); }
        .ops-table tbody td { color: var(--gray-700); }
        .dark .ops-table tbody td { color: var(--gray-300); }
        .ops-table tbody tr:hover td { background-color: var(--gray-50); }
        .dark .ops-table tbody tr:hover td { background-color: var(--gray-900); }
        .ops-table .ops-cell-label,
        .ops-table .ops-cell-total {
            font-weight: 600;
            color: var(--gray-950);
        }
        .dark .ops-table .ops-cell-label,
        .dark .ops-table .ops-cell-total { color: var(--color-white); }
        .ops-table .ops-cell-zero { color: var(--gray-400); }
        .ops-table tfoot td {
            font-weight: 700;
            color: var(--gray-950);
            background-color: var(--gray-50);
            border-bottom: 0;
        }
        .dark .ops-table tfoot td { color: var(--color-white); background-color: var(--gray-900); }

        @media (max-width: 640px) {
            .ops-period-summary { margin-inline-start: 0; width: 100%; }
            .ops-control { min-width: 100%; width: 100%; }
            .ops-field { width: 100%; }
            .ops-period-tabs { width: 100%; }
            .ops-period-tab { flex: 1 1 0; text-align: center; }
            .ops-grid { grid-template-columns: 1fr; }
            .ops-kpi-value { font-size: 1.75rem; }
        }

        @media (min-width: 641px) and (max-width: 900px) {
            .ops-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
    </style>

    <div class="ops-report">
        {{-- Filters —— polished toolbar --}}
        <div class="ops-toolbar" wire:key="ops-toolbar-{{ $period }}-{{ $anchorDate }}">
            <div class="ops-toolbar-controls">
                <div class="ops-field">
                    <span class="ops-field-label" id="ops-report-period-label">{{ __('Period') }}</span>
                    <div class="ops-period-tabs" role="group" aria-labelledby="ops-report-period-label">
                        <button
                            type="button"
                            wire:click="$set('period', 'day')"
                            @class(['ops-period-tab', 'is-active' => $period === 'day'])
                            @if ($period === 'day') aria-current="true" @endif
                        >
                            {{ __('Day') }}
                        </button>
                        <button
                            type="button"
                            wire:click="$set('period', 'week')"
                            @class(['ops-period-tab', 'is-active' => $period === 'week'])
                            @if ($period === 'week') aria-current="true" @endif
                        >
                            {{ __('Week') }}
                        </button>
                        <button
                            type="button"
                            wire:click="$set('period', 'month')"
                            @class(['ops-period-tab', 'is-active' => $period === 'month'])
                            @if ($period === 'month') aria-current="true" @endif
                        >
                            {{ __('Month') }}
                        </button>
                    </div>
                </div>

                <div class="ops-field">
                    <label for="ops-report-date" class="ops-field-label">{{ $dateFieldLabel }}</label>
                    <input id="ops-report-date" type="date" wire:model.live="anchorDate" class="ops-control" />
                </div>
            </div>

            <div class="ops-period-summary" aria-live="polite">
                <span class="ops-period-summary-icon" aria-hidden="true">
                    <x-filament::icon icon="heroicon-m-calendar-days" />
                </span>
                <div class="ops-period-summary-text">
                    <span class="ops-period-summary-label">{{ __('Showing') }}</span>
                    <span class="ops-period-summary-value">{{ $this->getPeriodLabel() }}</span>
                    <span class="ops-period-summary-tz">{{ \App\Services\OperationalReportService::TIMEZONE }}</span>
                </div>
            </div>
        </div>

        @php
            $gridCards = [
                [
                    'key' => 'total',
                    'label' => __('Total bookings'),
                    'value' => $totals['total'],
                    'hint' => __('Everything booked in this period'),
                    'icon' => 'heroicon-m-calendar-days',
                    'accent' => 'var(--primary-600)',
                    'accent_soft' => 'var(--primary-50)',
                    'empty' => $totals['total'] === 0,
                ],
                [
                    'key' => 'completed',
                    'label' => $statusMeta['completed']['label'],
                    'value' => $totals['completed'],
                    'hint' => $completionRate !== null
                        ? __(':rate% of all bookings', ['rate' => $completionRate])
                        : __('Visits finished with the doctor'),
                    'icon' => $statusMeta['completed']['icon'],
                    'accent' => $statusMeta['completed']['accent'],
                    'accent_soft' => $statusMeta['completed']['accent_soft'],
                    'empty' => $totals['completed'] === 0,
                ],
                [
                    'key' => 'queue',
                    'label' => __('Still in queue'),
                    'value' => $queueCount,
                    'hint' => __('Waiting + called + in chamber'),
                    'icon' => 'heroicon-m-users',
                    'accent' => 'var(--info-600)',
                    'accent_soft' => 'var(--info-50)',
                    'empty' => $queueCount === 0,
                ],
                'waiting',
                'no_show',
                'cancelled',
            ];
        @endphp

        @if ($totals['total'] === 0)
            <p class="ops-empty">{{ __('No bookings recorded for this period yet.') }}</p>
        @endif

        {{-- One grid: headline totals + Waiting / No-show / Cancelled (mid-flow statuses live under Still in queue) --}}
        <div class="ops-grid" role="list" aria-label="{{ __('Booking summary') }}">
            @foreach ($gridCards as $card)
                @php
                    if (is_string($card)) {
                        $meta = $statusMeta[$card];
                        $card = [
                            'key' => $card,
                            'label' => $meta['label'],
                            'value' => $totals[$card],
                            'hint' => null,
                            'icon' => $meta['icon'],
                            'accent' => $meta['accent'],
                            'accent_soft' => $meta['accent_soft'],
                            'empty' => $totals[$card] === 0,
                        ];
                    }
                @endphp
                <div
                    class="ops-kpi{{ $card['empty'] ? ' ops-kpi-empty' : '' }}"
                    style="--ops-accent: {{ $card['accent'] }}; --ops-accent-soft: {{ $card['accent_soft'] }};"
                    role="listitem"
                    wire:key="ops-grid-{{ $card['key'] }}"
                >
                    <div class="ops-kpi-head">
                        <span class="ops-kpi-icon" aria-hidden="true">
                            <x-filament::icon :icon="$card['icon']" />
                        </span>
                        <span class="ops-kpi-label">{{ $card['label'] }}</span>
                    </div>
                    <div class="ops-kpi-value">{{ number_format($card['value']) }}</div>
                    @if (filled($card['hint']))
                        <div class="ops-kpi-hint">{{ $card['hint'] }}</div>
                    @endif
                </div>
            @endforeach
        </div>

        {{-- Detail --}}
        @if ($period === 'day')
            {{ $this->table }}
        @elseif ($period === 'week')
            <x-filament::section :heading="__('Daily breakdown')" :description="__('Sunday to Saturday, for the selected week.')">
                <div class="ops-table-scroll">
                    <table class="ops-table">
                        <thead>
                            <tr>
                                <th>{{ __('Day') }}</th>
                                <th>{{ __('Total') }}</th>
                                @foreach ($statusMeta as $meta)
                                    <th>{{ $meta['label'] }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->getDailyRows() as $date => $row)
                                <tr wire:key="week-{{ $date }}">
                                    <td class="ops-cell-label">
                                        {{ \Carbon\Carbon::parse($date)->translatedFormat('D, j M') }}
                                    </td>
                                    <td class="ops-cell-total">{{ number_format($row['total']) }}</td>
                                    @foreach ($statusMeta as $status => $meta)
                                        <td @class(['ops-cell-zero' => $row[$status] === 0])>{{ number_format($row[$status]) }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <td>{{ __('Week total') }}</td>
                                <td>{{ number_format($totals['total']) }}</td>
                                @foreach ($statusMeta as $status => $meta)
                                    <td>{{ number_format($totals[$status]) }}</td>
                                @endforeach
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </x-filament::section>
        @else
            <x-filament::section :heading="__('Weekly breakdown')" :description="__('Each week that falls inside the selected month.')">
                <div class="ops-table-scroll">
                    <table class="ops-table">
                        <thead>
                            <tr>
                                <th>{{ __('Week') }}</th>
                                <th>{{ __('Total') }}</th>
                                @foreach ($statusMeta as $meta)
                                    <th>{{ $meta['label'] }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->getWeeklyRows() as $row)
                                <tr wire:key="month-{{ $row['week_start'] }}">
                                    <td class="ops-cell-label">
                                        {{ \Carbon\Carbon::parse($row['week_start'])->translatedFormat('j M') }}
                                        –
                                        {{ \Carbon\Carbon::parse($row['week_end'])->translatedFormat('j M') }}
                                    </td>
                                    <td class="ops-cell-total">{{ number_format($row['total']) }}</td>
                                    @foreach ($statusMeta as $status => $meta)
                                        <td @class(['ops-cell-zero' => $row[$status] === 0])>{{ number_format($row[$status]) }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <td>{{ __('Month total') }}</td>
                                <td>{{ number_format($totals['total']) }}</td>
                                @foreach ($statusMeta as $status => $meta)
                                    <td>{{ number_format($totals[$status]) }}</td>
                                @endforeach
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
