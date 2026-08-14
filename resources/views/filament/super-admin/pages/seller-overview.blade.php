<x-filament-panels::page>
    @php
        $quietClients = $this->getQuietClients();
        $goLiveFunnel = $this->getGoLiveFunnel();
        $smsWarnings = $this->getSmsWarnings();
        $overduePayments = $this->getOverduePayments();
        $stepLabels = $this->getFunnelStepLabels();
    @endphp

    <style>
        .seller-overview { display: flex; flex-direction: column; gap: 1.75rem; }
        .seller-section {
            border: 1px solid var(--gray-200);
            border-radius: 0.75rem;
            background: var(--color-white);
            overflow: hidden;
        }
        .dark .seller-section {
            border-color: var(--gray-700);
            background: var(--gray-900);
        }
        .seller-section-header {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid var(--gray-200);
            background: var(--gray-50);
        }
        .dark .seller-section-header {
            border-color: var(--gray-700);
            background: var(--gray-800);
        }
        .seller-section-title {
            margin: 0;
            font-size: 1rem;
            font-weight: 600;
            color: var(--gray-950);
        }
        .dark .seller-section-title { color: var(--color-white); }
        .seller-section-hint {
            margin: 0.25rem 0 0;
            font-size: 0.8125rem;
            color: var(--gray-600);
        }
        .dark .seller-section-hint { color: var(--gray-400); }
        .seller-table-wrap { overflow-x: auto; }
        .seller-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.875rem;
        }
        .seller-table th,
        .seller-table td {
            padding: 0.625rem 1rem;
            text-align: left;
            border-bottom: 1px solid var(--gray-200);
            vertical-align: top;
        }
        .dark .seller-table th,
        .dark .seller-table td { border-color: var(--gray-700); }
        .seller-table th {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            color: var(--gray-600);
            background: var(--color-white);
        }
        .dark .seller-table th {
            color: var(--gray-400);
            background: var(--gray-900);
        }
        .seller-table tbody tr:last-child td { border-bottom: none; }
        .seller-empty {
            padding: 1.25rem;
            font-size: 0.875rem;
            color: var(--gray-600);
        }
        .dark .seller-empty { color: var(--gray-400); }
        .seller-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.125rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .seller-badge-danger { background: var(--danger-50); color: var(--danger-800); }
        .seller-badge-warning { background: var(--warning-50); color: var(--warning-800); }
        .seller-badge-success { background: var(--success-50); color: var(--success-800); }
        .seller-badge-muted { background: var(--gray-100); color: var(--gray-700); }
        .dark .seller-badge-danger {
            background: color-mix(in srgb, var(--danger-500) 20%, transparent);
            color: var(--danger-200);
        }
        .dark .seller-badge-warning {
            background: color-mix(in srgb, var(--warning-500) 20%, transparent);
            color: var(--warning-200);
        }
        .dark .seller-badge-success {
            background: color-mix(in srgb, var(--success-500) 20%, transparent);
            color: var(--success-200);
        }
        .dark .seller-badge-muted { background: var(--gray-800); color: var(--gray-300); }
        .seller-funnel-steps {
            display: flex;
            flex-wrap: wrap;
            gap: 0.375rem;
        }
        .seller-funnel-step {
            padding: 0.125rem 0.5rem;
            border-radius: 0.375rem;
            font-size: 0.6875rem;
            font-weight: 600;
            white-space: nowrap;
        }
        .seller-funnel-step-done { background: var(--success-50); color: var(--success-800); }
        .seller-funnel-step-pending { background: var(--gray-100); color: var(--gray-500); }
        .seller-funnel-step-stall { background: var(--warning-50); color: var(--warning-800); outline: 2px solid var(--warning-500); }
        .dark .seller-funnel-step-done {
            background: color-mix(in srgb, var(--success-500) 20%, transparent);
            color: var(--success-200);
        }
        .dark .seller-funnel-step-pending { background: var(--gray-800); color: var(--gray-400); }
        .dark .seller-funnel-step-stall {
            background: color-mix(in srgb, var(--warning-500) 20%, transparent);
            color: var(--warning-200);
            outline-color: var(--warning-400);
        }
        .seller-client-link { color: inherit; text-decoration: none; }
        .seller-client-link:hover { text-decoration: underline; }
        .seller-client-meta { color: var(--gray-500); font-size: 0.75rem; }
        .seller-phone-link { color: var(--primary-600); font-size: 0.75rem; }
        .dark .seller-phone-link { color: var(--primary-400); }
        .seller-note {
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            font-size: 0.8125rem;
            color: var(--gray-700);
            background: var(--gray-50);
            border: 1px solid var(--gray-200);
        }
        .dark .seller-note {
            color: var(--gray-300);
            background: var(--gray-800);
            border-color: var(--gray-700);
        }
    </style>

    <div class="seller-overview">
        <p class="seller-note">
            Counts only — tenant names and usage signals, never patient names, diagnoses, prescriptions, or visit contents.
        </p>

        <section class="seller-section">
            <div class="seller-section-header">
                <h2 class="seller-section-title">Quiet clients</h2>
                <p class="seller-section-hint">Worst first — Sunday-morning call list. Days since last live session, booking drop vs their own baseline, or schedule set but never run.</p>
            </div>
            @if (count($quietClients) === 0)
                <p class="seller-empty">No quiet clients right now.</p>
            @else
                <div class="seller-table-wrap">
                    <table class="seller-table">
                        <thead>
                            <tr>
                                <th>Client</th>
                                <th>Days since session</th>
                                <th>Bookings this week</th>
                                <th>Baseline / week</th>
                                <th>Drop</th>
                                <th>Schedule, never started</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($quietClients as $row)
                                <tr>
                                    <td>@include('filament.super-admin.pages.partials.seller-client-cell', ['row' => $row])</td>
                                    <td>
                                        @if ($row['days_since_last_session'] === null)
                                            <span class="seller-badge seller-badge-danger">Never ran</span>
                                        @elseif ($row['days_since_last_session'] >= 21)
                                            <span class="seller-badge seller-badge-danger">{{ $row['days_since_last_session'] }} days</span>
                                        @elseif ($row['days_since_last_session'] >= 10)
                                            <span class="seller-badge seller-badge-warning">{{ $row['days_since_last_session'] }} days</span>
                                        @else
                                            {{ $row['days_since_last_session'] }} days
                                        @endif
                                    </td>
                                    <td>{{ $row['bookings_this_week'] }}</td>
                                    <td>{{ $row['bookings_baseline_weekly'] }}</td>
                                    <td>
                                        @if ($row['booking_drop_percent'] !== null)
                                            {{ $row['booking_drop_percent'] }}%
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td>
                                        @if ($row['scheduled_never_started'])
                                            <span class="seller-badge seller-badge-warning">Yes</span>
                                        @else
                                            No
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section class="seller-section">
            <div class="seller-section-header">
                <h2 class="seller-section-title">Go-live funnel</h2>
                <p class="seller-section-hint">Recent signups — where onboarding stalls. First live session is the real finish line.</p>
            </div>
            @if (count($goLiveFunnel) === 0)
                <p class="seller-empty">No signups in the last {{ \App\Services\SellerOverviewService::RECENT_SIGNUP_DAYS }} days.</p>
            @else
                <div class="seller-table-wrap">
                    <table class="seller-table">
                        <thead>
                            <tr>
                                <th>Client</th>
                                <th>Signed up</th>
                                <th>Progress</th>
                                <th>Stalls at</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($goLiveFunnel as $row)
                                <tr>
                                    <td>@include('filament.super-admin.pages.partials.seller-client-cell', ['row' => $row])</td>
                                    <td>{{ $row['signed_up_at']->format('d M Y') }}</td>
                                    <td>
                                        <div class="seller-funnel-steps">
                                            @foreach ($stepLabels as $key => $label)
                                                @php
                                                    $done = $row['steps'][$key] ?? false;
                                                    $isStall = $row['stall_step'] === $key;
                                                    $class = $isStall ? 'seller-funnel-step-stall' : ($done ? 'seller-funnel-step-done' : 'seller-funnel-step-pending');
                                                @endphp
                                                <span class="seller-funnel-step {{ $class }}" title="{{ $label }}">{{ $done ? '✓' : '○' }} {{ $label }}</span>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td>
                                        @if ($row['is_live'])
                                            <span class="seller-badge seller-badge-success">Live</span>
                                        @elseif ($row['stall_step'])
                                            {{ $stepLabels[$row['stall_step']] ?? $row['stall_step'] }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section class="seller-section">
            <div class="seller-section-header">
                <h2 class="seller-section-title">SMS credit warnings</h2>
                <p class="seller-section-hint">At or below {{ \App\Services\SellerOverviewService::SMS_LOW_THRESHOLD }} credits — booking confirmations stop silently at zero.</p>
            </div>
            @if (count($smsWarnings) === 0)
                <p class="seller-empty">All clients have SMS headroom.</p>
            @else
                <div class="seller-table-wrap">
                    <table class="seller-table">
                        <thead>
                            <tr>
                                <th>Client</th>
                                <th>Balance</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($smsWarnings as $row)
                                <tr>
                                    <td>@include('filament.super-admin.pages.partials.seller-client-cell', ['row' => $row])</td>
                                    <td>
                                        @if ($row['is_empty'])
                                            <span class="seller-badge seller-badge-danger">{{ $row['sms_balance'] }} — empty</span>
                                        @else
                                            <span class="seller-badge seller-badge-warning">{{ $row['sms_balance'] }}</span>
                                        @endif
                                    </td>
                                    <td><span class="seller-badge seller-badge-muted">{{ strtoupper($row['billing_status']) }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section class="seller-section">
            <div class="seller-section-header">
                <h2 class="seller-section-title">Overdue payments</h2>
                <p class="seller-section-hint">Who owes, and for how long — not just a platform total.</p>
            </div>
            @if (count($overduePayments) === 0)
                <p class="seller-empty">No overdue accounts.</p>
            @else
                <div class="seller-table-wrap">
                    <table class="seller-table">
                        <thead>
                            <tr>
                                <th>Client</th>
                                <th>Reason</th>
                                <th>Days overdue</th>
                                <th>Billing status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($overduePayments as $row)
                                <tr>
                                    <td>@include('filament.super-admin.pages.partials.seller-client-cell', ['row' => $row])</td>
                                    <td>{{ str_replace('_', ' ', ucfirst($row['reason'])) }}</td>
                                    <td>
                                        <span class="seller-badge {{ $row['days_overdue'] >= 30 ? 'seller-badge-danger' : 'seller-badge-warning' }}">
                                            {{ $row['days_overdue'] }} days
                                        </span>
                                    </td>
                                    <td>{{ strtoupper($row['billing_status']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
</x-filament-panels::page>
