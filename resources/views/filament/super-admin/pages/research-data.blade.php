<x-filament-panels::page>
    @php
        $results = $this->getResearchResults();
        $rows = $results['rows'];
        $suppressed = $results['suppressed_group_count'];
        $totalCoded = $results['total_coded_visits'];
        $minGroup = $this->getMinGroupSize();
    @endphp

    <style>
        .research-overview { display: flex; flex-direction: column; gap: 1.75rem; }
        .research-section {
            border: 1px solid var(--gray-200);
            border-radius: 0.75rem;
            background: var(--color-white);
            overflow: hidden;
        }
        .dark .research-section {
            border-color: var(--gray-700);
            background: var(--gray-900);
        }
        .research-section-header {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid var(--gray-200);
            background: var(--gray-50);
        }
        .dark .research-section-header {
            border-color: var(--gray-700);
            background: var(--gray-800);
        }
        .research-section-title {
            margin: 0;
            font-size: 1rem;
            font-weight: 600;
            color: var(--gray-950);
        }
        .dark .research-section-title { color: var(--color-white); }
        .research-section-hint {
            margin: 0.25rem 0 0;
            font-size: 0.8125rem;
            color: var(--gray-600);
        }
        .dark .research-section-hint { color: var(--gray-400); }
        .research-filters {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(10rem, 1fr));
            gap: 1rem;
            padding: 1.25rem;
        }
        .research-filter label {
            display: block;
            margin-bottom: 0.375rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            color: var(--gray-600);
        }
        .dark .research-filter label { color: var(--gray-400); }
        .research-filter input,
        .research-filter select {
            width: 100%;
            padding: 0.5rem 0.75rem;
            border: 1px solid var(--gray-300);
            border-radius: 0.5rem;
            font-size: 0.875rem;
            background: var(--color-white);
            color: var(--gray-950);
        }
        .dark .research-filter input,
        .dark .research-filter select {
            border-color: var(--gray-600);
            background: var(--gray-800);
            color: var(--color-white);
        }
        .research-table-wrap { overflow-x: auto; }
        .research-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.875rem;
        }
        .research-table th,
        .research-table td {
            padding: 0.625rem 1rem;
            text-align: left;
            border-bottom: 1px solid var(--gray-200);
        }
        .dark .research-table th,
        .dark .research-table td { border-color: var(--gray-700); }
        .research-table th {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            color: var(--gray-600);
        }
        .dark .research-table th { color: var(--gray-400); }
        .research-table tbody tr:last-child td { border-bottom: none; }
        .research-table td:last-child { font-variant-numeric: tabular-nums; font-weight: 600; }
        .research-empty {
            padding: 1.25rem;
            font-size: 0.875rem;
            color: var(--gray-600);
        }
        .dark .research-empty { color: var(--gray-400); }
        .research-note {
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            font-size: 0.8125rem;
            color: var(--gray-700);
            background: var(--gray-50);
            border: 1px solid var(--gray-200);
        }
        .dark .research-note {
            color: var(--gray-300);
            background: var(--gray-800);
            border-color: var(--gray-700);
        }
        .research-warning {
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            font-size: 0.8125rem;
            color: #92400e;
            background: #fef3c7;
            border: 1px solid #fcd34d;
        }
        .dark .research-warning {
            color: #fde68a;
            background: #78350f;
            border-color: #b45309;
        }
        .research-meta {
            padding: 0 1.25rem 1rem;
            font-size: 0.8125rem;
            color: var(--gray-600);
        }
        .dark .research-meta { color: var(--gray-400); }
    </style>

    <div class="research-overview">
        <p class="research-note">
            <strong>Aggregate anonymous research only.</strong>
            Counts come from coded diagnoses across all practices — never individual patient records, names, or phone numbers.
            Doctors agreed to anonymous statistics at signup.
            Groups smaller than {{ $minGroup }} are never shown, so narrow filters cannot identify a single person.
        </p>

        <section class="research-section">
            <div class="research-section-header">
                <h2 class="research-section-title">Filters</h2>
                <p class="research-section-hint">Date range and plan tier only — no per-practice or per-patient slicing.</p>
            </div>
            <div class="research-filters" wire:ignore.self>
                <div class="research-filter">
                    <label for="research-date-from">From</label>
                    <input id="research-date-from" type="date" wire:model.live="dateFrom">
                </div>
                <div class="research-filter">
                    <label for="research-date-to">To</label>
                    <input id="research-date-to" type="date" wire:model.live="dateTo">
                </div>
                <div class="research-filter">
                    <label for="research-plan-tier">Plan tier</label>
                    <select id="research-plan-tier" wire:model.live="planTier">
                        <option value="">All plans</option>
                        <option value="solo">{{ \App\Models\Tenant::planTierLabel('solo') }}</option>
                        <option value="clinic">{{ \App\Models\Tenant::planTierLabel('clinic') }}</option>
                    </select>
                </div>
            </div>
        </section>

        @if ($suppressed > 0)
            <p class="research-warning">
                {{ $suppressed }} condition {{ $suppressed === 1 ? 'group was' : 'groups were' }} hidden because the count would be below {{ $minGroup }} with these filters.
                Widen your date range or remove the plan filter to see more.
            </p>
        @endif

        <section class="research-section">
            <div class="research-section-header">
                <h2 class="research-section-title">Coded diagnosis counts</h2>
                <p class="research-section-hint">Only conditions picked from the standard list — free-text diagnoses are excluded.</p>
            </div>
            <p class="research-meta">{{ number_format($totalCoded) }} coded visits in this filter window · showing groups of {{ $minGroup }} or more</p>
            @if (count($rows) === 0)
                <p class="research-empty">No condition groups meet the minimum size of {{ $minGroup }} for these filters.</p>
            @else
                <div class="research-table-wrap">
                    <table class="research-table">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Condition</th>
                                <th>Visits</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                <tr wire:key="condition-{{ $row['condition_id'] }}">
                                    <td><code>{{ $row['condition_code'] }}</code></td>
                                    <td>{{ $row['condition_name'] }}</td>
                                    <td>{{ number_format($row['count']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
</x-filament-panels::page>
