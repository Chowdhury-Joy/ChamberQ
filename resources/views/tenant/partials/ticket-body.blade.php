{{-- Shared ticket body + queue JS. Shells: tenant.ticket / tenant.solo.ticket --}}
@php
    use App\Support\DayOfWeek;
    $bookable = $booking->bookable;
    $isLab = $bookable instanceof \App\Models\LabCollectionSlot;
    $tenant = tenant();
    $locale = app()->getLocale();
    $ticketUrl = url()->current();
    $chamber = $bookable?->chamber;
    $mapsUrl = $chamber?->googleMapsUrl();
    $shareText = $mapsUrl
        ? __('Serial :serial at :clinic — Ticket: :ticket — Map: :map', [
            'serial' => $booking->serial_number,
            'clinic' => $tenant->displayName(),
            'ticket' => $ticketUrl,
            'map' => $mapsUrl,
        ])
        : __('Serial :serial at :clinic — :link', [
            'serial' => $booking->serial_number,
            'clinic' => $tenant->displayName(),
            'link' => $ticketUrl,
        ]);
    $whatsAppShareUrl = 'https://wa.me/?text=' . rawurlencode($shareText);
    $copyPayload = $mapsUrl ? ($ticketUrl . "\n" . $mapsUrl) : $ticketUrl;
@endphp

    <main class="ticket">
        <div class="ticket-card">
            @if($tenant->logo_url)
                <img src="{{ $tenant->logo_url }}" alt="{{ $tenant->displayName() }}" style="height: 48px; margin-bottom: 1rem; display: inline-block;">
            @else
                <p class="ticket-brand">{{ $tenant->displayName() }}</p>
            @endif

            <div id="alertBanner" style="display: none; padding: 1rem; border-radius: var(--radius-md); margin-bottom: 1.5rem; font-weight: 600;"></div>

            <p class="text-muted">{{ __('Your serial number') }}</p>
            <p class="serial">{{ $booking->serial_number }}</p>
            <p class="text-muted" style="margin-top:-0.75rem;margin-bottom:1.25rem;">{{ __('Show this number at reception') }}</p>

            <div class="eta-box" id="etaContainer" style="display: none;">
                <p class="text-muted" style="margin-bottom: 0.25rem; font-size: 0.9rem;">{{ __('Estimated Time') }}</p>
                <p style="font-size: 1.25rem; font-weight: 700; color: var(--color-primary); margin: 0;" id="shownTime">—</p>
            </div>

            <p class="text-muted">{{ __('Now serving') }}</p>
            <p class="now-serving" id="nowServing" aria-live="polite">—</p>
            <p class="text-muted" id="aheadOfYou" aria-live="polite"></p>

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
                        @if ($mapsUrl)
                            <div class="detail-row">
                                <span class="text-muted">{{ __('Map') }}</span>
                                <strong>
                                    <a href="{{ $mapsUrl }}" target="_blank" rel="noopener noreferrer" style="color: var(--color-primary);">
                                        {{ __('Open in Google Maps') }}
                                    </a>
                                </strong>
                            </div>
                        @endif
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
                    <strong>{{ __('Pay at the clinic') }}</strong>
                </div>
            </div>

            @php($preparation = $booking->preparationInstructions())
            @if ($preparation !== [])
                <section class="prep" aria-labelledby="prepHeading">
                    <h2 id="prepHeading">{{ __('Before you come') }}</h2>
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

            <div class="handoff">
                <strong>{{ __('Keep this page') }}</strong>
                <span> — {{ __('Save the link or send it on WhatsApp so you can check the live queue from your phone.') }}</span>
            </div>

            <div class="share-actions">
                <button type="button" class="btn btn-primary" id="copyLink">{{ __('Copy link') }}</button>
                <a class="btn btn-back" id="whatsAppShare" href="{{ $whatsAppShareUrl }}" target="_blank" rel="noopener noreferrer">{{ __('Share on WhatsApp') }}</a>
                @if ($mapsUrl)
                    <a class="btn btn-back" href="{{ $mapsUrl }}" target="_blank" rel="noopener noreferrer">{{ __('Open in Google Maps') }}</a>
                @endif
            </div>
            <div class="link-box">
                <label class="sr-only" for="ticketLink">{{ __('Link to this ticket') }}</label>
                <input id="ticketLink" class="form-control" type="text" readonly value="{{ $copyPayload }}">
            </div>
            <p class="text-muted" style="margin-top:0.35rem;font-size:0.85rem;">
                {{ $mapsUrl ? __('Copy includes your ticket link and the chamber map.') : __('Save this link to check your place in the queue.') }}
            </p>
            <p class="text-muted" id="copyFeedback" style="margin-top:0.5rem;min-height:1.25rem;" aria-live="polite"></p>
        </div>
    </main>

    <script>
        const statusUrl = @json(route('queue.status', $booking));
        const i18n = {
            youAreNext: @json(__('You are next.')),
            oneAhead: @json(__('1 person ahead of you')),
            manyAhead: @json(__(':count people ahead of you')),
            copied: @json(__('Link copied')),
        };

        async function refreshQueue() {
            try {
                const res = await fetch(statusUrl, { headers: { 'Accept': 'application/json' } });
                if (!res.ok) return;
                const data = await res.json();
                
                document.getElementById('nowServing').textContent = data.now_serving ?? '—';
                
                const ahead = document.getElementById('aheadOfYou');
                const n = Number(data.ahead_of_you) || 0;
                if (n <= 0) {
                    ahead.textContent = i18n.youAreNext;
                } else if (n === 1) {
                    ahead.textContent = i18n.oneAhead;
                } else {
                    ahead.textContent = i18n.manyAhead.replace(':count', String(n));
                }

                const etaContainer = document.getElementById('etaContainer');
                const shownTimeEl = document.getElementById('shownTime');
                if (data.shown_time) {
                    etaContainer.style.display = 'block';
                    const date = new Date(data.shown_time);
                    shownTimeEl.textContent = date.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
                } else {
                    etaContainer.style.display = 'none';
                }

                const banner = document.getElementById('alertBanner');
                banner.style.display = 'none';
                
                if (data.status === 'cancelled' || data.session_status === 'cancelled') {
                    banner.style.display = 'block';
                    banner.style.backgroundColor = '#fee2e2';
                    banner.style.color = '#991b1b';
                    banner.textContent = @json(__('Booking cancelled.'));
                } else if (data.status === 'no_show') {
                    banner.style.display = 'block';
                    banner.style.backgroundColor = '#fee2e2';
                    banner.style.color = '#991b1b';
                    banner.textContent = @json(__('You missed your call (No Show).'));
                } else if (data.is_called) {
                    banner.style.display = 'block';
                    banner.style.backgroundColor = '#dcfce7';
                    banner.style.color = '#166534';
                    banner.textContent = @json(__('It is your turn! Please enter the chamber.'));
                } else if (data.status === 'skipped') {
                    banner.style.display = 'block';
                    banner.style.backgroundColor = '#ffedd5';
                    banner.style.color = '#9a3412';
                    banner.textContent = @json(__('You missed your call. We will try again shortly.'));
                } else if (data.is_paused) {
                    banner.style.display = 'block';
                    banner.style.backgroundColor = '#f3f4f6';
                    banner.style.color = '#374151';
                    banner.textContent = @json(__('Session paused')) + (data.pause_reason ? ': ' + data.pause_reason : '');
                } else if (data.session_status === 'delayed' && data.delay_minutes > 0) {
                    banner.style.display = 'block';
                    banner.style.backgroundColor = '#fef3c7';
                    banner.style.color = '#92400e';
                    banner.textContent = @json(__('Doctor is delayed by :minutes minutes.', ['minutes' => '__MIN__'])).replace('__MIN__', data.delay_minutes);
                }

            } catch (e) { /* offline: keep the last known value */ }
        }

        refreshQueue();
        setInterval(refreshQueue, 5000);

        document.getElementById('copyLink').addEventListener('click', async () => {
            const input = document.getElementById('ticketLink');
            const feedback = document.getElementById('copyFeedback');
            input.select();
            try {
                await navigator.clipboard.writeText(input.value);
            } catch (e) {
                document.execCommand('copy');
            }
            feedback.textContent = i18n.copied;
            setTimeout(() => { feedback.textContent = ''; }, 2500);
        });

        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js'));
        }
    </script>
