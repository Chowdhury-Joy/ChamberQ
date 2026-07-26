@php
    use App\Support\DayOfWeek;
    $bookable = $booking->bookable;
    $isLab = $bookable instanceof \App\Models\LabCollectionSlot;
    $tenant = tenant();
    $fontFamily = $tenant->font_family ?? 'Inter';
    $themeColor = $tenant->theme_color ?? '#0ea5e9';
    $fontUrl = match($fontFamily) {
        'Outfit' => 'https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap',
        'Roboto' => 'https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap',
        'Hind Siliguri' => 'https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&display=swap',
        default => 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap',
    };
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="{{ $themeColor }}">
    <meta name="robots" content="noindex">
    <title>{{ __('Your Appointment') }} | {{ $tenant->displayName() }}</title>
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="{{ $fontUrl }}">
    @if($tenant->favicon_url)
    <link rel="icon" href="{{ $tenant->favicon_url }}">
    @endif
    <link rel="stylesheet" href="/css/theme.css">
    <style>
        :root {
            --color-primary: {{ $themeColor }};
            --font-family-base: '{{ $fontFamily }}', system-ui, -apple-system, sans-serif;
        }
        body { font-family: var(--font-family-base); }
        .ticket { max-width: 520px; margin: 2rem auto; padding: 0 1rem; }
        .ticket-card { background: var(--bg-surface); padding: 2rem; border-radius: var(--radius-lg); box-shadow: var(--shadow-md); text-align: center; }
        .serial { font-size: 3.5rem; font-weight: 700; color: var(--color-primary); line-height: 1; margin: .5rem 0 1.5rem; }
        .now-serving { font-size: 2rem; font-weight: 600; }
        .detail-row { display: flex; justify-content: space-between; gap: 1rem; padding: .65rem 0; border-top: 1px solid rgba(128,128,128,.18); text-align: left; }
        .link-box { display: flex; gap: .5rem; margin-top: 1rem; }
        .link-box input { flex: 1; min-width: 0; }
        .prep { margin-top: 1.5rem; padding: 1rem 1.25rem; text-align: left; border-radius: var(--radius-md); background: #fffbeb; border: 1px solid #f59e0b; color: #713f12; }
        .prep h2 { font-size: 1.05rem; margin-bottom: .5rem; }
        .prep ul { margin: 0; padding-left: 1.1rem; }
        .prep li { margin: .35rem 0; }
        .prep-note { margin-top: .75rem; font-size: .9rem; font-weight: 600; }
        @media (prefers-color-scheme: dark) { .prep { background: #2a2107; color: #fde68a; } }
        .sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0; }
    </style>
</head>
<body>
    <main class="ticket">
        <div class="ticket-card">
            @if($tenant->logo_url)
                <img src="{{ $tenant->logo_url }}" alt="{{ $tenant->displayName() }}" style="height: 48px; margin-bottom: 1rem; display: inline-block;">
            @endif
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
                @foreach ($booking->labTests as $test)
                    <div class="detail-row">
                        <span class="text-muted">{{ $test->name }}</span>
                        <strong>৳{{ number_format((float) $test->pivot->price_at_booking, 2) }}</strong>
                    </div>
                @endforeach
                @if ($booking->labTests->isNotEmpty())
                    <div class="detail-row">
                        <span class="text-muted">{{ __('Total') }}</span>
                        <strong>৳{{ number_format((float) $booking->totalPrice(), 2) }}</strong>
                    </div>
                @endif
                <div class="detail-row">
                    <span class="text-muted">{{ __('Payment') }}</span>
                    <strong>{{ $booking->payment_status === 'paid' ? __('Paid') : __('Pay at the clinic') }}</strong>
                </div>
            </div>

            @php($preparation = $booking->preparationInstructions())
            @if ($preparation !== [])
                {{-- The screen the patient re-reads the night before. A missed
                     fasting requirement means a wasted test and a wasted trip,
                     so this is deliberately loud and never collapsed. --}}
                <section class="prep" aria-labelledby="prepHeading">
                    <h2 id="prepHeading">⚠️ {{ __('Before you come') }}</h2>
                    <ul>
                        @foreach ($preparation as $item)
                            <li><strong>{{ $item['test'] }}:</strong> {{ $item['instructions'] }}</li>
                        @endforeach
                    </ul>
                    @if (count($preparation) > 1)
                        <p class="prep-note">{{ __('If any of these instructions seem to conflict, please call the clinic before your visit.') }}</p>
                    @endif
                </section>
            @endif

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
