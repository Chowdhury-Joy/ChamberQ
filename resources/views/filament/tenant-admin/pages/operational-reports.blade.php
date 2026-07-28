<x-filament-panels::page>
    <div class="space-y-6">
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-content-ctn p-4 sm:p-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-end">
                    <div class="sm:w-44">
                        <label for="ops-report-period" class="fi-fo-field-wrp-label inline-flex items-center gap-x-1 text-sm font-medium text-gray-950 dark:text-white">
                            {{ __('Period') }}
                        </label>
                        <select
                            id="ops-report-period"
                            wire:model.live="period"
                            class="fi-input mt-2 block w-full rounded-lg border-none bg-white px-3 py-2 text-sm text-gray-950 shadow-sm ring-1 ring-gray-950/10 focus:ring-2 focus:ring-primary-600 dark:bg-white/5 dark:text-white dark:ring-white/20"
                        >
                            <option value="day">{{ __('Day') }}</option>
                            <option value="week">{{ __('Week') }}</option>
                            <option value="month">{{ __('Month') }}</option>
                        </select>
                    </div>

                    <div class="sm:w-56">
                        <label for="ops-report-date" class="fi-fo-field-wrp-label inline-flex items-center gap-x-1 text-sm font-medium text-gray-950 dark:text-white">
                            @if ($period === 'week')
                                {{ __('Any day in week') }}
                            @elseif ($period === 'month')
                                {{ __('Any day in month') }}
                            @else
                                {{ __('Date') }}
                            @endif
                        </label>
                        <input
                            id="ops-report-date"
                            type="date"
                            wire:model.live="anchorDate"
                            class="fi-input mt-2 block w-full rounded-lg border-none bg-white px-3 py-2 text-sm text-gray-950 shadow-sm ring-1 ring-gray-950/10 focus:ring-2 focus:ring-primary-600 dark:bg-white/5 dark:text-white dark:ring-white/20"
                        />
                    </div>

                    <p class="text-sm text-gray-500 dark:text-gray-400 sm:pb-2">
                        {{ $this->getPeriodLabel() }}
                        <span class="text-gray-400 dark:text-gray-500">· Asia/Dhaka</span>
                    </p>
                </div>
            </div>
        </div>

        @php($totals = $this->getTotals())

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Total bookings') }}</p>
                <p class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ $totals['total'] }}</p>
            </div>
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Completed') }}</p>
                <p class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ $totals['completed'] }}</p>
            </div>
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Waiting / Called / In chamber') }}</p>
                <p class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">
                    {{ $totals['waiting'] }} / {{ $totals['called'] }} / {{ $totals['in_chamber'] }}
                </p>
            </div>
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Skipped / No-show / Cancelled') }}</p>
                <p class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">
                    {{ $totals['skipped'] }} / {{ $totals['no_show'] }} / {{ $totals['cancelled'] }}
                </p>
            </div>
        </div>

        <div class="grid gap-3 sm:grid-cols-4 lg:grid-cols-7">
            @foreach (\App\Services\OperationalReportService::STATUSES as $status)
                <div class="rounded-lg bg-gray-50 px-3 py-2 text-center ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                    <p class="text-xs capitalize text-gray-500 dark:text-gray-400">{{ str_replace('_', ' ', $status) }}</p>
                    <p class="mt-0.5 text-lg font-semibold text-gray-950 dark:text-white">{{ $totals[$status] }}</p>
                </div>
            @endforeach
        </div>

        @if ($period === 'day')
            {{ $this->table }}
        @elseif ($period === 'week')
            <div class="fi-section overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[640px] divide-y divide-gray-200 text-sm dark:divide-white/10">
                        <thead class="bg-gray-50 dark:bg-white/5">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">{{ __('Day') }}</th>
                                <th class="px-3 py-3 text-right font-medium text-gray-500 dark:text-gray-400">{{ __('Total') }}</th>
                                @foreach (\App\Services\OperationalReportService::STATUSES as $status)
                                    <th class="px-3 py-3 text-right font-medium capitalize text-gray-500 dark:text-gray-400">
                                        {{ str_replace('_', ' ', $status) }}
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                            @foreach ($this->getDailyRows() as $date => $row)
                                <tr wire:key="week-{{ $date }}">
                                    <td class="px-4 py-3 font-medium text-gray-950 dark:text-white">
                                        {{ \Carbon\Carbon::parse($date)->translatedFormat('D, j M') }}
                                    </td>
                                    <td class="px-3 py-3 text-right font-semibold text-gray-950 dark:text-white">{{ $row['total'] }}</td>
                                    @foreach (\App\Services\OperationalReportService::STATUSES as $status)
                                        <td class="px-3 py-3 text-right text-gray-700 dark:text-gray-300">{{ $row[$status] }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-50 dark:bg-white/5">
                            <tr>
                                <td class="px-4 py-3 font-semibold text-gray-950 dark:text-white">{{ __('Week total') }}</td>
                                <td class="px-3 py-3 text-right font-semibold text-gray-950 dark:text-white">{{ $totals['total'] }}</td>
                                @foreach (\App\Services\OperationalReportService::STATUSES as $status)
                                    <td class="px-3 py-3 text-right font-semibold text-gray-950 dark:text-white">{{ $totals[$status] }}</td>
                                @endforeach
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        @else
            <div class="fi-section overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[640px] divide-y divide-gray-200 text-sm dark:divide-white/10">
                        <thead class="bg-gray-50 dark:bg-white/5">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">{{ __('Week') }}</th>
                                <th class="px-3 py-3 text-right font-medium text-gray-500 dark:text-gray-400">{{ __('Total') }}</th>
                                @foreach (\App\Services\OperationalReportService::STATUSES as $status)
                                    <th class="px-3 py-3 text-right font-medium capitalize text-gray-500 dark:text-gray-400">
                                        {{ str_replace('_', ' ', $status) }}
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                            @foreach ($this->getWeeklyRows() as $row)
                                <tr wire:key="month-{{ $row['week_start'] }}">
                                    <td class="px-4 py-3 font-medium text-gray-950 dark:text-white">
                                        {{ \Carbon\Carbon::parse($row['week_start'])->translatedFormat('j M') }}
                                        –
                                        {{ \Carbon\Carbon::parse($row['week_end'])->translatedFormat('j M') }}
                                    </td>
                                    <td class="px-3 py-3 text-right font-semibold text-gray-950 dark:text-white">{{ $row['total'] }}</td>
                                    @foreach (\App\Services\OperationalReportService::STATUSES as $status)
                                        <td class="px-3 py-3 text-right text-gray-700 dark:text-gray-300">{{ $row[$status] }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-50 dark:bg-white/5">
                            <tr>
                                <td class="px-4 py-3 font-semibold text-gray-950 dark:text-white">{{ __('Month total') }}</td>
                                <td class="px-3 py-3 text-right font-semibold text-gray-950 dark:text-white">{{ $totals['total'] }}</td>
                                @foreach (\App\Services\OperationalReportService::STATUSES as $status)
                                    <td class="px-3 py-3 text-right font-semibold text-gray-950 dark:text-white">{{ $totals[$status] }}</td>
                                @endforeach
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
