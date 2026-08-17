{{-- Shared ticket body + queue JS. Shells: tenant.ticket / tenant.solo.ticket --}}
@php
    use App\Support\DayOfWeek;
    $bookable = $booking->bookable;
    $isLab = $bookable instanceof \App\Models\LabCollectionSlot;
    $tenant = tenant();
    $locale = app()->getLocale();
    $ticketUrl = \App\Support\TenancyUrl::publicAbsolute(
        (string) $booking->tenant_id,
        '/bookings/'.$booking->id,
    );
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
    // Clipboard may include ticket + map on two lines. Never put that in an
    // <input type="text"> value — browsers strip newlines and glue the URLs.
    $copyPayload = $mapsUrl ? ($ticketUrl."\n".$mapsUrl) : $ticketUrl;
    $hasLiveQueue = $tenant?->hasLiveQueue() ?? false;
@endphp

    {{-- Keeps the serial (and the number being called) on screen once the big
         one scrolls past — patients scroll to the map and prep notes while waiting.
         aria-hidden because the live region inside .live-queue already announces it. --}}
    @if ($hasLiveQueue)
    <div class="serial-strip no-print" id="serialStrip" aria-hidden="true">
        <div class="serial-strip-inner">
            <span class="serial-strip-label">{{ __('Your serial') }}</span>
            <span class="serial-strip-serial">{{ $booking->serial_number }}</span>
            <span class="serial-strip-now">
                <span class="serial-strip-label">{{ __('Now serving') }}</span>
                <span id="stripNowServing">—</span>
            </span>
        </div>
    </div>
    @endif

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
            @if (($tenant?->hasStations() ?? false) && filled($booking->voucherLabel()))
                <p class="text-muted" style="margin-top:-0.5rem;margin-bottom:0.25rem;">{{ __('Voucher') }}</p>
                <p style="font-size:1.5rem;font-weight:600;margin:0 0 0.75rem;">{{ $booking->voucherLabel() }}</p>
            @endif
            <p class="text-muted" style="margin-bottom:1.25rem;">{{ __('Show this number at reception') }}</p>

            @if ($hasLiveQueue)
            <div class="eta-box" id="etaContainer" style="display: none;">
                <p class="text-muted" style="margin-bottom: 0.25rem; font-size: 0.9rem;">{{ __('Estimated Time') }}</p>
                <p style="font-size: 1.25rem; font-weight: 700; color: var(--color-primary); margin: 0;" id="shownTime">—</p>
            </div>

            <div class="live-queue no-print">
                <p class="text-muted">{{ __('Now serving') }}</p>
                <p class="now-serving" id="nowServing" aria-live="polite">—</p>
                <p class="text-muted" id="aheadOfYou" aria-live="polite"></p>
            </div>

            <audio id="ticketChime" src="{{ $tenant->callAudioUrl() }}" preload="auto" hidden></audio>

            <div class="eta-box no-print" id="pocketAlertCard" style="margin-top:1rem;text-align:left;">
                <p style="font-weight:700;margin:0 0 0.35rem;">{{ __('Get a buzz even if the phone is locked', [], 'bn') }}</p>
                <p class="text-muted" style="margin:0 0 0.75rem;font-size:0.9rem;">{{ __('If you go for tea, we can still tell you. This is only for this serial.', [], 'bn') }}</p>
                <p class="sr-only">{{ __('Start walking back — you are soon.', [], 'bn') }}</p>
                <p class="text-muted" id="pocketAlertDenied" hidden style="margin:0;font-size:0.9rem;">{{ __('We cannot reach a locked phone. Come at your ticket time, or sit by the screen.', [], 'bn') }}</p>
                <div id="pocketAlertActions" style="display:flex;flex-wrap:wrap;gap:0.5rem;">
                    <button type="button" class="btn btn-primary" id="pocketAlertAllow">{{ __('Notify me', [], 'bn') }}</button>
                    <button type="button" class="btn btn-back" id="pocketAlertLater">{{ __('Not now', [], 'bn') }}</button>
                </div>
            </div>
            @endif

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

            <div class="handoff no-print">
                <strong>{{ __('Keep this page') }}</strong>
                <span> — {{ $hasLiveQueue
                    ? __('Save the link, print a copy, or send it on WhatsApp so you can check the live queue from your phone.')
                    : __('Save the link, print a copy, or send it on WhatsApp so you have your serial at the chamber.') }}</span>
            </div>

            <div class="share-actions no-print">
                <button type="button" class="btn btn-primary" id="copyLink">{{ __('Copy link') }}</button>
                <a class="btn btn-back" id="whatsAppShare" href="{{ $whatsAppShareUrl }}" target="_blank" rel="noopener noreferrer">{{ __('Share on WhatsApp') }}</a>
                <button type="button" class="btn btn-back" id="printTicket">{{ __('Print') }}</button>
                <button type="button" class="btn btn-back" id="saveTicketPdf">{{ __('Save as PDF') }}</button>
                @if ($mapsUrl)
                    <a class="btn btn-back" href="{{ $mapsUrl }}" target="_blank" rel="noopener noreferrer">{{ __('Open in Google Maps') }}</a>
                @endif
            </div>
            <div class="link-box no-print">
                <label class="sr-only" for="ticketLink">{{ __('Link to this ticket') }}</label>
                <input id="ticketLink" class="form-control" type="text" readonly value="{{ $ticketUrl }}">
            </div>
            <p class="text-muted no-print" style="margin-top:0.35rem;font-size:0.85rem;">
                @if ($mapsUrl)
                    {{ __('Copy includes your ticket link and the chamber map.') }}
                @elseif ($hasLiveQueue)
                    {{ __('Save this link to check your place in the queue.') }}
                @else
                    {{ __('Save this link so you have your serial handy.') }}
                @endif
            </p>
            <p class="text-muted no-print" style="margin-top:0.25rem;font-size:0.85rem;">
                {{ __('To download a PDF, tap Save as PDF and choose “Save as PDF” in the print window.') }}
            </p>
            <p class="text-muted no-print" id="copyFeedback" style="margin-top:0.5rem;min-height:1.25rem;" aria-live="polite"></p>

            <div class="print-footer print-only" aria-hidden="true">
                <p>{{ __('Show this number at reception') }}</p>
                <p class="print-url">{{ $ticketUrl }}</p>
            </div>
        </div>
    </main>

    <script>
        const copyPayload = @json($copyPayload);
        const i18n = {
            youAreNext: @json(__('You are next.')),
            oneAhead: @json(__('1 person ahead of you')),
            manyAhead: @json(__(':count people ahead of you')),
            copied: @json(__('Link copied')),
            twoAway: @json(__('Start walking back — you are soon.', [], 'bn'), JSON_UNESCAPED_UNICODE),
            stayClose: @json(__('You are next — stay close.', [], 'bn'), JSON_UNESCAPED_UNICODE),
            yourTurn: @json(__('It is your turn! Please enter the chamber.', [], 'bn'), JSON_UNESCAPED_UNICODE),
        };

        @if ($hasLiveQueue)
        const queueApproach = true;
        const statusUrl = @json(tenant_web_route('queue.status', $booking, absolute: false));
        const pushUrl = @json(tenant_web_route('queue.push', $booking, absolute: false));
        const csrfToken = @json(csrf_token());
        const vapidPublicKey = @json((string) config('webpush.vapid.public_key'));
        const chime = document.getElementById('ticketChime');
        const STAGE_RANK = { two_away: 1, next: 2, called: 3 };
        let lastAlertStage = 0;

        function unlockChime() {
            if (!chime) return;
            chime.play().then(() => { chime.pause(); chime.currentTime = 0; }).catch(() => {});
        }
        document.addEventListener('pointerdown', unlockChime, { once: true });

        function buzz(stage) {
            const rank = STAGE_RANK[stage] || 0;
            if (rank <= lastAlertStage) return;
            lastAlertStage = rank;
            try { navigator.vibrate([200, 100, 200]); } catch (e) {}
            if (chime) chime.play().catch(() => {});
        }

        function urlBase64ToUint8Array(base64String) {
            const padding = '='.repeat((4 - base64String.length % 4) % 4);
            const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
            const raw = atob(base64);
            const output = new Uint8Array(raw.length);
            for (let i = 0; i < raw.length; ++i) output[i] = raw.charCodeAt(i);
            return output;
        }

        (function setupPocketAlerts() {
            const card = document.getElementById('pocketAlertCard');
            const actions = document.getElementById('pocketAlertActions');
            const denied = document.getElementById('pocketAlertDenied');
            const allow = document.getElementById('pocketAlertAllow');
            const later = document.getElementById('pocketAlertLater');
            if (!card || !actions || !denied || !allow || !later) return;

            const hideActions = () => { actions.hidden = true; };
            later.addEventListener('click', hideActions);

            if (!vapidPublicKey) {
                hideActions();
                return;
            }

            allow.addEventListener('click', async () => {
                unlockChime();
                if (!('Notification' in window) || !('serviceWorker' in navigator) || !('PushManager' in window)) {
                    denied.hidden = false;
                    hideActions();
                    return;
                }
                try {
                    const perm = await Notification.requestPermission();
                    if (perm !== 'granted') {
                        denied.hidden = false;
                        hideActions();
                        return;
                    }
                    const reg = await navigator.serviceWorker.ready;
                    const sub = await reg.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: urlBase64ToUint8Array(vapidPublicKey),
                    });
                    await fetch(pushUrl, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: JSON.stringify(sub.toJSON()),
                    });
                    hideActions();
                } catch (e) {
                    denied.hidden = false;
                    hideActions();
                }
            });
        })();

        async function refreshQueue() {
            try {
                const res = await fetch(statusUrl, { headers: { 'Accept': 'application/json' } });
                if (!res.ok) return;
                const data = await res.json();
                
                document.getElementById('nowServing').textContent = data.now_serving ?? '—';
                const stripNow = document.getElementById('stripNowServing');
                if (stripNow) stripNow.textContent = data.now_serving ?? '—';
                const strip = document.getElementById('serialStrip');
                if (strip) strip.classList.toggle('is-called', Boolean(data.is_called));

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
                if (data.overflow_phrase) {
                    etaContainer.style.display = 'block';
                    shownTimeEl.textContent = data.overflow_phrase;
                } else if (data.shown_time) {
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
                    buzz('called');
                    banner.style.display = 'block';
                    banner.style.backgroundColor = '#dcfce7';
                    banner.style.color = '#166534';
                    banner.textContent = i18n.yourTurn;
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
                } else if (data.status === 'waiting' && n <= 1) {
                    buzz('next');
                    banner.style.display = 'block';
                    banner.style.backgroundColor = '#dbeafe';
                    banner.style.color = '#1e40af';
                    banner.textContent = i18n.stayClose;
                } else if (data.status === 'waiting' && n === 2) {
                    buzz('two_away');
                    banner.style.display = 'block';
                    banner.style.backgroundColor = '#fef3c7';
                    banner.style.color = '#92400e';
                    banner.textContent = i18n.twoAway;
                }

            } catch (e) { /* offline: keep the last known value */ }
        }

        refreshQueue();
        setInterval(refreshQueue, 5000);

        // Reveal the sticky strip once the big serial has scrolled under the header.
        // The strip is fixed, so reading its own rect.top each time gives whatever
        // header offset the shell set (0 on clinic, 68/95px on solo) without
        // duplicating those breakpoints in JS.
        (function setupSerialStrip() {
            const strip = document.getElementById('serialStrip');
            const serial = document.querySelector('.serial');
            if (!strip || !serial) return;

            let ticking = false;
            const sync = () => {
                ticking = false;
                const offset = strip.getBoundingClientRect().top;
                strip.classList.toggle('is-visible', serial.getBoundingClientRect().bottom < offset);
            };
            const schedule = () => {
                if (ticking) return;
                ticking = true;
                requestAnimationFrame(sync);
            };

            sync();
            window.addEventListener('scroll', schedule, { passive: true });
            window.addEventListener('resize', schedule, { passive: true });
        })();
        @endif

        document.getElementById('copyLink').addEventListener('click', async () => {
            const input = document.getElementById('ticketLink');
            const feedback = document.getElementById('copyFeedback');
            input.select();
            try {
                // Prefer the JS payload so a map link stays on its own line —
                // the visible input is ticket-only (text inputs cannot hold \n).
                await navigator.clipboard.writeText(copyPayload);
            } catch (e) {
                document.execCommand('copy');
            }
            feedback.textContent = i18n.copied;
            setTimeout(() => { feedback.textContent = ''; }, 2500);
        });

        function openTicketPrint() {
            window.print();
        }
        document.getElementById('printTicket').addEventListener('click', openTicketPrint);
        document.getElementById('saveTicketPdf').addEventListener('click', openTicketPrint);

        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => navigator.serviceWorker.register(@json(tenant_web_url('/sw.js'))));
        }
    </script>
