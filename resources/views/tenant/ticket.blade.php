@php
    use App\Support\DayOfWeek;
    $bookable = $booking->bookable;
    $isLab = $bookable instanceof \App\Models\LabCollectionSlot;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0ea5e9">
    <meta name="robots" content="noindex">
    <title>{{ __('Your Appointment') }} | {{ tenant('id') }}</title>
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="stylesheet" href="/css/theme.css">
    <style>
        .ticket { max-width: 520px; margin: 2rem auto; padding: 0 1rem; }
        .ticket-card { background: var(--bg-surface); padding: 2rem; border-radius: var(--radius-lg); box-shadow: var(--shadow-md); text-align: center; }
        .serial { font-size: 3.5rem; font-weight: 700; color: var(--color-primary); line-height: 1; margin: .5rem 0 1.5rem; }
        .now-serving { font-size: 2rem; font-weight: 600; }
        .detail-row { display: flex; justify-content: space-between; gap: 1rem; padding: .65rem 0; border-top: 1px solid rgba(128,128,128,.18); text-align: left; }
        .link-box { display: flex; gap: .5rem; margin-top: 1rem; }
        .link-box input { flex: 1; min-width: 0; }
    </style>
</head>
<body>
    <main class="ticket">
        <div class="ticket-card">
            <p class="text-muted">{{ __('Your serial number') }}</p>
            <p class="serial">{{ $booking->serial_number }}</p>

            <p class="text-muted">{{ __('Now serving') }}</p>
            <p class="now-serving" id="nowServing" aria-live="polite">—</p>
            <p class="text-muted" id="aheadOfYou"></p>

            <div style="margin-top:1.5rem">
                <div class="detail-row">
                    <span class="text-muted">{{ __('Date') }}</span>
                    <strong>{{ \Carbon\Carbon::parse($booking->booking_date)->translatedFormat('j F Y') }}</strong>
                </div>
                @if ($bookable)
                    <div class="detail-row">
                        <span class="text-muted">{{ $isLab ? __('Collection window') : __('Session') }}</span>
                        <strong>
                            {{ $isLab ? DayOfWeek::label($bookable->day_of_week) : $bookable->session_name }}
                            ({{ \Carbon\Carbon::parse($bookable->start_time)->format('h:i A') }} –
                            {{ \Carbon\Carbon::parse($bookable->end_time)->format('h:i A') }})
                        </strong>
                    </div>
                    @if (! $isLab && $bookable->doctor)
                        <div class="detail-row">
                            <span class="text-muted">{{ __('Doctor') }}</span>
                            <strong>{{ $bookable->doctor->name }}</strong>
                        </div>
                    @endif
                    @if ($bookable->chamber)
                        <div class="detail-row">
                            <span class="text-muted">{{ __('Location') }}</span>
                            <strong>{{ $bookable->chamber->name }}</strong>
                        </div>
                    @endif
                @endif
                <div class="detail-row">
                    <span class="text-muted">{{ __('Patient') }}</span>
                    <strong>{{ $booking->patient_name }}</strong>
                </div>
                <div class="detail-row">
                    <span class="text-muted">{{ __('Payment') }}</span>
                    <strong>{{ $booking->payment_status === 'paid' ? __('Paid') : __('Pay at the clinic') }}</strong>
                </div>
            </div>

            <p class="text-muted" style="margin-top:1.5rem">{{ __('Save this link to check your place in the queue.') }}</p>
            <div class="link-box">
                <label class="sr-only" for="ticketLink">{{ __('Link to this ticket') }}</label>
                <input id="ticketLink" class="form-control" type="text" readonly value="{{ url()->current() }}">
                <button type="button" class="btn btn-primary" id="copyLink">{{ __('Copy') }}</button>
            </div>
        </div>
    </main>

    <script>
        const statusUrl = @json(route('queue.status', $booking));

        async function refreshQueue() {
            try {
                const res = await fetch(statusUrl, { headers: { 'Accept': 'application/json' } });
                if (!res.ok) return;
                const data = await res.json();
                document.getElementById('nowServing').textContent = data.now_serving ?? '—';
                const ahead = document.getElementById('aheadOfYou');
                ahead.textContent = data.ahead_of_you > 0
                    ? @json(__('people ahead of you:')) + ' ' + data.ahead_of_you
                    : @json(__('You are next.'));
            } catch (e) { /* offline: keep the last known value */ }
        }

        refreshQueue();
        setInterval(refreshQueue, 30000);

        document.getElementById('copyLink').addEventListener('click', async () => {
            const input = document.getElementById('ticketLink');
            input.select();
            try { await navigator.clipboard.writeText(input.value); } catch (e) { document.execCommand('copy'); }
        });

        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js'));
        }
    </script>
</body>
</html>
