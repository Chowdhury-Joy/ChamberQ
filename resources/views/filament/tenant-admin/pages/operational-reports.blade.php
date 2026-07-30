<x-filament-panels::page>
    @php
        $totals = $this->getTotals();
        $statusMeta = $this->getStatusMeta();
        $queueCount = $this->getQueueCount();
        $problemCount = $this->getProblemCount();
        $completionRate = $this->getCompletionRate();
    @endphp

    {{--
        This panel ships Filament's precompiled stylesheet, so arbitrary Tailwind
        utilities are not available here. Layout is written against Filament's own
        CSS variables so it tracks the panel theme and dark mode.
    --}}
    <style>
        .ops-report { display: flex; flex-direction: column; gap: 1.5rem; }

        .ops-filters {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            gap: 1rem;
        }
        .ops-field { display: flex; flex-direction: column; gap: 0.375rem; min-width: 12rem; }
        .ops-field-label { font-size: 0.8125rem; font-weight: 600; color: var(--gray-700); }
        .dark .ops-field-label { color: var(--gray-300); }
        .ops-control {
            width: 100%;
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
            background-color: var(--gray-900);
            border-color: var(--gray-700);
        }
        .ops-period {
            margin-inline-start: auto;
            padding-bottom: 0.5rem;
            font-size: 0.875rem;
            color: var(--gray-600);
        }
        .ops-period strong { font-weight: 600; color: var(--gray-950); }
        .dark .ops-period { color: var(--gray-400); }
        .dark .ops-period strong { color: var(--color-white); }

        .ops-kpis {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(15rem, 1fr));
            gap: 1rem;
        }
        .ops-kpi {
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            gap: 0.625rem;
            padding: 1.25rem;
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

        .ops-kpi-head { display: flex; align-items: center; gap: 0.5rem; }
        .ops-kpi-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 1.75rem;
            height: 1.75rem;
            border-radius: 0.5rem;
            color: var(--ops-accent);
            background-color: var(--ops-accent-soft);
        }
        .ops-kpi-icon svg { width: 1rem; height: 1rem; }
        .ops-kpi-label {
            font-size: 0.8125rem;
            font-weight: 600;
            color: var(--gray-600);
        }
        .dark .ops-kpi-label { color: var(--gray-400); }
        .ops-kpi-value {
            font-size: 2rem;
            font-weight: 700;
            line-height: 1.1;
            color: var(--gray-950);
        }
        .dark .ops-kpi-value { color: var(--color-white); }
        .ops-kpi-hint { font-size: 0.8125rem; color: var(--gray-500); }
        .dark .ops-kpi-hint { color: var(--gray-500); }

        .ops-statuses {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(9rem, 1fr));
            gap: 0.75rem;
        }
        .ops-status {
            display: flex;
            flex-direction: column;
            gap: 0.375rem;
            padding: 0.75rem;
            border-radius: 0.625rem;
            background-color: var(--gray-50);
            border: 1px solid var(--gray-200);
        }
        .dark .ops-status { background-color: var(--gray-900); border-color: var(--gray-800); }
        .ops-status.ops-status-empty { opacity: 0.55; }
        .ops-status-value {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--gray-950);
        }
        .dark .ops-status-value { color: var(--color-white); }

        .ops-empty {
            padding: 0.25rem 0;
            font-size: 0.875rem;
            color: var(--gray-500);
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
            .ops-period { margin-inline-start: 0; padding-bottom: 0; }
            .ops-field { min-width: 100%; }
            .ops-kpi-value { font-size: 1.75rem; }
        }
    </style>

    <div class="ops-report">
        {{-- Filters --}}
        <x-filament::section>
            <div class="ops-filters">
                <div class="ops-field">
                    <label for="ops-report-period" class="ops-field-label">{{ __('Period') }}</label>
                    <select id="ops-report-period" wire:model.live="period" class="ops-control">
                        <option value="day">{{ __('Day') }}</option>
                        <option value="week">{{ __('Week') }}</option>
                        <option value="month">{{ __('Month') }}</option>
                    </select>
                </div>

                <div class="ops-field">
                    <label for="ops-report-date" class="ops-field-label">
                        @if ($period === 'week')
                            {{ __('Any day in week') }}
                        @elseif ($period === 'month')
                            {{ __('Any day in month') }}
                        @else
                            {{ __('Date') }}
                        @endif
                    </label>
                    <input id="ops-report-date" type="date" wire:model.live="anchorDate" class="ops-control" />
                </div>

                <p class="ops-period">
                    <strong>{{ $this->getPeriodLabel() }}</strong><br />
                    {{ \App\Services\OperationalReportService::TIMEZONE }}
                </p>
            </div>
        </x-filament::section>

        {{-- Headline numbers --}}
        <div class="ops-kpis">
            <div class="ops-kpi" style="--ops-accent: var(--primary-600); --ops-accent-soft: var(--primary-50);">
                <div class="ops-kpi-head">
                    <span class="ops-kpi-icon">
                        <x-filament::icon icon="heroicon-m-calendar-days" />
                    </span>
                    <span class="ops-kpi-label">{{ __('Total bookings') }}</span>
                </div>
                <div class="ops-kpi-value">{{ number_format($totals['total']) }}</div>
                <div class="ops-kpi-hint">{{ __('Everything booked in this period') }}</div>
            </div>

            <div class="ops-kpi" style="--ops-accent: var(--success-600); --ops-accent-soft: var(--success-50);">
                <div class="ops-kpi-head">
                    <span class="ops-kpi-icon">
                        <x-filament::icon icon="heroicon-m-check-circle" />
                    </span>
                    <span class="ops-kpi-label">{{ __('Completed') }}</span>
                </div>
                <div class="ops-kpi-value">{{ number_format($totals['completed']) }}</div>
                <div class="ops-kpi-hint">
                    @if ($completionRate !== null)
                        {{ __(':rate% of all bookings', ['rate' => $completionRate]) }}
                    @else
                        {{ __('Visits finished with the doctor') }}
                    @endif
                </div>
            </div>

            <div class="ops-kpi" style="--ops-accent: var(--info-600); --ops-accent-soft: var(--info-50);">
                <div class="ops-kpi-head">
                    <span class="ops-kpi-icon">
                        <x-filament::icon icon="heroicon-m-users" />
                    </span>
                    <span class="ops-kpi-label">{{ __('Still in queue') }}</span>
                </div>
                <div class="ops-kpi-value">{{ number_format($queueCount) }}</div>
                <div class="ops-kpi-hint">{{ __('Waiting, called or in chamber') }}</div>
            </div>

            <div class="ops-kpi" style="--ops-accent: var(--danger-600); --ops-accent-soft: var(--danger-50);">
                <div class="ops-kpi-head">
                    <span class="ops-kpi-icon">
                        <x-filament::icon icon="heroicon-m-exclamation-triangle" />
                    </span>
                    <span class="ops-kpi-label">{{ __('Needs attention') }}</span>
                </div>
                <div class="ops-kpi-value">{{ number_format($problemCount) }}</div>
                <div class="ops-kpi-hint">{{ __('Skipped, no-show or cancelled') }}</div>
            </div>
        </div>

        {{-- Status breakdown --}}
        <x-filament::section :heading="__('Status breakdown')" :description="__('Every booking in this period, grouped by where it ended up.')">
            @if ($totals['total'] === 0)
                <p class="ops-empty">{{ __('No bookings recorded for this period yet.') }}</p>
            @else
                <div class="ops-statuses">
                    @foreach ($statusMeta as $status => $meta)
                        <div @class(['ops-status', 'ops-status-empty' => $totals[$status] === 0])>
                            <x-filament::badge :color="$meta['color']" :icon="$meta['icon']">
                                {{ $meta['label'] }}
                            </x-filament::badge>
                            <span class="ops-status-value">{{ number_format($totals[$status]) }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-filament::section>

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
