<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Live queue') }} — {{ $scheduleSession->chamber->name }}</title>
    <meta name="robots" content="noindex, nofollow">
    
    @php
        $tenant = tenant();
        $isBn = app()->getLocale() === 'bn';
        $themeColor = $tenant->cssThemeColor();
        $fontFamily = $tenant->font_family ?? 'Inter';
        if ($isBn && ! in_array($fontFamily, ['Hind Siliguri'], true)) {
            $fontFamily = 'Hind Siliguri';
        }
        $localFontCss = public_asset('css/chamberq-screen-fonts.css');
        $callAudioUrl = $tenant->callAudioUrl();
        $callAnnounceLocale = $tenant->call_announce_locale ?? 'en';
        $usesCallChime = $tenant->usesCallChime();
        $usesCallVoice = $tenant->usesCallVoice();
    @endphp

    <link rel="stylesheet" href="{{ $localFontCss }}">
    
    <style>
        :root {
            --color-primary: {{ $themeColor }};
            --font-family-base: '{{ $fontFamily }}', system-ui, -apple-system, sans-serif;
        }
        
        body {
            font-family: var(--font-family-base);
            margin: 0;
            padding: 0;
            background-color: #0f172a;
            color: white;
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .header {
            padding: 2rem;
            background: rgba(0,0,0,0.2);
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .chamber-name {
            font-size: 2rem;
            font-weight: 600;
        }

        .doctor-name {
            font-size: 1.5rem;
            color: #94a3b8;
        }

        .session-label {
            font-size: 1.25rem;
            color: #64748b;
            margin-top: 0.35rem;
            font-weight: 500;
        }

        .main-content {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 2rem;
        }

        .now-serving-box {
            background: rgba(255,255,255,0.05);
            border: 2px solid var(--color-primary);
            border-radius: 2rem;
            padding: 4rem;
            width: 80%;
            max-width: 1000px;
            box-shadow: 0 0 40px rgba(0,0,0,0.5);
            transition: all 0.5s ease;
        }

        .now-serving-box.calling {
            background: var(--color-primary);
            color: #fff;
            transform: scale(1.05);
            box-shadow: 0 0 80px var(--color-primary);
        }

        .label {
            font-size: 2.5rem;
            letter-spacing: {{ $isBn ? '0' : '2px' }};
            margin-bottom: 2rem;
            color: #cbd5e1;
            @unless($isBn)
            text-transform: uppercase;
            @endunless
        }

        .now-serving-box.calling .label {
            color: rgba(255,255,255,0.9);
        }

        .serial {
            font-size: 12rem;
            font-weight: 700;
            line-height: 1;
            margin: 0;
            color: var(--color-primary);
        }

        .now-serving-box.calling .serial {
            color: #fff;
        }

        .patient-name {
            font-size: 3rem;
            margin-top: 2rem;
            font-weight: 500;
        }

        .status-message {
            font-size: 3rem;
            color: #f59e0b;
        }

        .next-up {
            padding: 1.5rem 3rem;
            background: rgba(0,0,0,0.3);
            text-align: center;
            font-size: 1.8rem;
            border-top: 1px solid rgba(255,255,255,0.1);
        }

        .next-up #nextSerial {
            font-weight: 700;
            color: var(--color-primary);
        }

        .next-up #nextEta {
            font-weight: 500;
            color: #94a3b8;
            margin-inline-start: 0.35em;
        }

        .sound-overlay {
            position: fixed;
            inset: 0;
            z-index: 50;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(15, 23, 42, 0.92);
            cursor: pointer;
        }

        .sound-overlay.hidden {
            display: none;
        }

        .sound-enable-btn {
            border: 2px solid var(--color-primary);
            background: rgba(255,255,255,0.06);
            color: #fff;
            border-radius: 1rem;
            padding: 1.5rem 2.5rem;
            font-size: 1.5rem;
            font-family: inherit;
            cursor: pointer;
        }

        .sound-enable-btn small {
            display: block;
            margin-top: 0.5rem;
            font-size: 1rem;
            color: #94a3b8;
        }

        .sound-toggle {
            position: fixed;
            top: 1.25rem;
            right: 1.25rem;
            z-index: 40;
            border: 1px solid rgba(255,255,255,0.2);
            background: rgba(0,0,0,0.35);
            color: #e2e8f0;
            border-radius: 999px;
            padding: 0.6rem 1rem;
            font-size: 0.95rem;
            font-family: inherit;
            cursor: pointer;
        }

        .sound-toggle.hidden {
            display: none;
        }

        .offline-chip {
            position: fixed;
            bottom: 1rem;
            right: 1rem;
            z-index: 45;
            padding: 0.5rem 0.85rem;
            border-radius: 999px;
            background: rgba(0, 0, 0, 0.55);
            border: 1px solid rgba(255, 255, 255, 0.15);
            color: #e2e8f0;
            font-size: 0.85rem;
            display: none;
        }

        .offline-chip.visible {
            display: block;
        }
    </style>
</head>
<body>
    <div id="soundOverlay" class="sound-overlay" role="button" tabindex="0" aria-label="{{ __('Tap to enable sound') }}">
        <button type="button" class="sound-enable-btn" id="soundEnableBtn">
            {{ __('Tap to enable sound') }}
            <small>{{ __('Required once so call chimes and voice announcements can play on this screen') }}</small>
        </button>
    </div>

    <button type="button" id="soundToggle" class="sound-toggle hidden" aria-pressed="true">
        {{ __('Sound on') }}
    </button>

    <div class="header">
        <div>
            <div class="chamber-name">{{ $scheduleSession->chamber->name }}</div>
            <div class="doctor-name">{{ $scheduleSession->doctor->name }}</div>
            <div class="session-label">{{ $scheduleSession->screenLabel() }}</div>
        </div>
        <div style="text-align: right;">
            <div id="screenDate" style="font-size: 1.5rem; color: #94a3b8;">{{ \Carbon\Carbon::parse($sessionDate)->translatedFormat('j F Y') }}</div>
        </div>
    </div>

    <div class="main-content">
        <div id="statusContainer" class="now-serving-box">
            <div id="defaultView">
                <div class="label" id="mainLabel">{{ __('Now serving') }}</div>
                <div class="serial" id="currentSerial">—</div>
                <div class="patient-name" id="currentName"></div>
            </div>
            
            <div id="messageView" style="display: none;">
                <div class="status-message" id="messageText"></div>
                <div class="patient-name" id="messageSubtext" style="color: #cbd5e1; font-size: 2rem;"></div>
            </div>
        </div>
    </div>

    <div class="next-up" id="nextUpContainer" style="display: none;">
        {{ __('Next:') }} <span id="nextSerial"></span><span id="nextEta"></span>
    </div>

    <div id="offlineChip" class="offline-chip" aria-live="polite"></div>

    @if($usesCallChime)
    <audio id="chimeAudio" src="{{ $callAudioUrl }}" preload="auto"></audio>
    @endif
    @if($usesCallVoice)
    <audio id="announceAudio" preload="auto"></audio>
    @endif

    <script>
        @php
            $liveToday = $liveToday ?? false;
            $statusUrl = $liveToday
                ? tenant_web_route('api.tenant.screen.today', ['session' => $scheduleSession->id], absolute: false)
                : tenant_web_route('api.tenant.screen', ['session' => $scheduleSession->id, 'date' => $sessionDate], absolute: false);
        @endphp
        @php
            $bookUrl = tenant('id') === 'nusraturmi'
                ? 'drurminusrat.com/book'
                : (\App\Support\TenancyUrl::usesPathPrefix()
                    ? url(tenant_web_url('/book'))
                    : \App\Support\TenancyUrl::publicAbsolute((string) tenant('id'), '/book'));
            $screenLabels = [
                'nowServing' => __('Now serving'),
                'nowCalling' => __('Now calling you'),
                'sessionNotStarted' => __('Session has not started yet'),
                'pleaseWait' => __('Please wait'),
                'sessionCancelled' => __("Today's session has been cancelled"),
                'contactReception' => __('Contact reception'),
                'sessionEnded' => __('Session ended — thank you'),
                'nextBooking' => __('For your next booking:'),
                'breakInProgress' => __('Break in progress'),
                'estimatedResume' => __('Estimated resume:'),
            ];
        @endphp
        const statusUrl = @json($statusUrl);
        const liveToday = @json($liveToday);
        let pageDate = @json($sessionDate);
        const callAnnounceLocale = @json($callAnnounceLocale);
        const usesCallChime = @json($usesCallChime);
        const usesCallVoice = @json($usesCallVoice);
        const labels = @json($screenLabels);
        const bookUrl = @json($bookUrl);
        let lastCalledTime = null;
        let soundUnlocked = false;
        let soundMuted = false;
        let lastGoodPayload = null;
        let lastGoodAt = null;
        let pollOffline = false;
        const cacheKey = 'cq-screen:' + statusUrl;
        const offlineChip = document.getElementById('offlineChip');
        const offlineChipTemplate = @json(__('Line dropped — last update :time'));

        const audio = document.getElementById('chimeAudio');
        const announceAudio = document.getElementById('announceAudio');
        const overlay = document.getElementById('soundOverlay');
        const toggle = document.getElementById('soundToggle');

        async function unlockSound() {
            if (usesCallChime && audio) {
                try {
                    audio.muted = true;
                    await audio.play();
                    audio.pause();
                    audio.currentTime = 0;
                    audio.muted = false;
                } catch (e) {
                    logAudio('chime unlock failed', e);
                }
            }

            // Unlock recorded “Number twelve” clips the same way as the chime.
            if (usesCallVoice && announceAudio) {
                try {
                    announceAudio.src = @json(public_asset('audio/announce/number-1.wav'));
                    announceAudio.muted = true;
                    await announceAudio.play();
                    announceAudio.pause();
                    announceAudio.currentTime = 0;
                    announceAudio.muted = false;
                    announceAudio.removeAttribute('src');
                    announceAudio.load();
                } catch (e) {
                    logAudio('announce unlock failed', e);
                }
            }

            soundUnlocked = true;
            overlay.classList.add('hidden');
            toggle.classList.remove('hidden');
            updateToggleLabel();

            // Chrome fills getVoices() asynchronously; poke it during the gesture.
            if ('speechSynthesis' in window) {
                try { window.speechSynthesis.getVoices(); } catch (e) {}
            }
        }

        /*
         * Audio diagnostics for a silent waiting-room TV — the most common
         * support call for this screen, and impossible to diagnose without
         * knowing whether playback was blocked, muted, or the clip is missing.
         * Off unless someone adds ?debug=1 to the screen URL, so a permanently
         * mounted display is not logging to a console nobody reads.
         */
        const audioDebug = new URLSearchParams(window.location.search).has('debug');
        function logAudio(...args) {
            if (audioDebug) console.log('[screen audio]', ...args);
        }

        function updateToggleLabel() {
            toggle.textContent = soundMuted ? @json(__('Sound off')) : @json(__('Sound on'));
            toggle.setAttribute('aria-pressed', soundMuted ? 'false' : 'true');
        }

        function playChime() {
            if (!soundUnlocked || soundMuted || !audio) return;
            audio.currentTime = 0;
            audio.play().catch(e => logAudio('chime blocked by browser', e));
        }

        const announceBaseUrl = @json(rtrim(public_asset('audio/announce'), '/'));

        function playAnnounceClip(serial) {
            if (!soundUnlocked || soundMuted || !announceAudio) return Promise.resolve(false);

            const n = parseInt(String(serial), 10);
            if (!Number.isFinite(n) || n < 1 || n > 99) {
                return Promise.resolve(false);
            }

            return new Promise(function (resolve) {
                const url = announceBaseUrl + '/number-' + n + '.wav';
                const onEnded = function () {
                    cleanup();
                    resolve(true);
                };
                const onError = function () {
                    cleanup();
                    resolve(false);
                };
                const cleanup = function () {
                    announceAudio.removeEventListener('ended', onEnded);
                    announceAudio.removeEventListener('error', onError);
                };

                announceAudio.pause();
                announceAudio.src = url;
                announceAudio.addEventListener('ended', onEnded);
                announceAudio.addEventListener('error', onError);
                announceAudio.play().catch(function (e) {
                    logAudio('announce blocked', e);
                    cleanup();
                    resolve(false);
                });
            });
        }

        // Say the number (and name) three times: a waiting room is noisy and a
        // patient who looked away for one pass still catches the second or third.
        const ANNOUNCE_REPEATS = 3;
        const ANNOUNCE_GAP_MS = 700;

        // Bumped on every new call so a sequence still repeating for the previous
        // serial stops instead of talking over the new one.
        let announceSequence = 0;

        // Name is spoken with browser TTS (try-it). Serial stays on Karen WAVs —
        // those were the ghostly part. Name quality varies by TV/OS voice pack.
        function pickNameVoice() {
            if (!('speechSynthesis' in window)) return null;
            const voices = window.speechSynthesis.getVoices() || [];
            if (!voices.length) return null;

            const wantBn = callAnnounceLocale === 'bn';
            const prefix = wantBn ? 'bn' : 'en';
            const matchLang = voices.find(function (v) {
                return String(v.lang || '').toLowerCase().startsWith(prefix);
            });
            if (matchLang) return matchLang;

            // Bangla voice packs are rare on TVs — any English voice is fine.
            return voices.find(function (v) {
                return String(v.lang || '').toLowerCase().startsWith('en');
            }) || voices[0];
        }

        function speakName(name) {
            if (!soundUnlocked || soundMuted) return Promise.resolve(false);
            if (!('speechSynthesis' in window)) return Promise.resolve(false);

            const text = String(name || '').trim();
            if (!text) return Promise.resolve(false);

            return new Promise(function (resolve) {
                try {
                    window.speechSynthesis.cancel();
                } catch (e) {}

                const utterance = new SpeechSynthesisUtterance(text);
                utterance.lang = callAnnounceLocale === 'bn' ? 'bn-BD' : 'en-US';
                utterance.rate = 0.95;
                const voice = pickNameVoice();
                if (voice) utterance.voice = voice;

                let settled = false;
                const done = function (ok) {
                    if (settled) return;
                    settled = true;
                    resolve(ok);
                };

                utterance.onend = function () { done(true); };
                utterance.onerror = function () { done(false); };

                try {
                    window.speechSynthesis.speak(utterance);
                } catch (e) {
                    logAudio('name TTS failed', e);
                    done(false);
                }
            });
        }

        async function speakCall(serial, name) {
            if (!soundUnlocked || soundMuted) return;

            const mySequence = ++announceSequence;

            // Serial: pre-recorded Karen WAV only. Name: browser TTS after each pass.
            for (let i = 0; i < ANNOUNCE_REPEATS; i++) {
                if (soundMuted || mySequence !== announceSequence) return;

                const played = await playAnnounceClip(serial);

                // Clip missing or playback blocked — do not retry two more times.
                if (!played) return;

                if (soundMuted || mySequence !== announceSequence) return;
                await speakName(name);

                if (i < ANNOUNCE_REPEATS - 1) {
                    await new Promise(function (r) { setTimeout(r, ANNOUNCE_GAP_MS); });
                }
            }
        }

        function announceCall(serial, name) {
            if (!soundUnlocked || soundMuted) return;

            if (usesCallChime) {
                playChime();
            }

            if (usesCallVoice) {
                const delay = usesCallChime ? 900 : 0;
                setTimeout(function () { speakCall(serial, name); }, delay);
            }
        }

        overlay.addEventListener('click', unlockSound);
        document.getElementById('soundEnableBtn').addEventListener('click', function (e) {
            e.stopPropagation();
            unlockSound();
        });
        overlay.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                unlockSound();
            }
        });

        toggle.addEventListener('click', function () {
            soundMuted = !soundMuted;
            if (soundMuted) {
                announceSequence++;
                if (announceAudio) {
                    announceAudio.pause();
                    announceAudio.currentTime = 0;
                }
                if ('speechSynthesis' in window) {
                    try { window.speechSynthesis.cancel(); } catch (e) {}
                }
            }
            updateToggleLabel();
        });

        function loadCachedPayload() {
            try {
                const raw = localStorage.getItem(cacheKey);
                if (!raw) return null;
                const parsed = JSON.parse(raw);
                return parsed?.data || null;
            } catch (e) {
                return null;
            }
        }

        function saveCachedPayload(data) {
            try {
                localStorage.setItem(cacheKey, JSON.stringify({
                    saved_at: new Date().toISOString(),
                    data,
                }));
            } catch (e) {}
        }

        function formatChipTime(iso) {
            try {
                return new Date(iso).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
            } catch (e) {
                return '';
            }
        }

        function setOfflineChip(visible, atIso) {
            if (!offlineChip) return;
            if (!visible) {
                offlineChip.classList.remove('visible');
                offlineChip.textContent = '';
                return;
            }
            const label = offlineChipTemplate.replace(':time', formatChipTime(atIso || new Date().toISOString()));
            offlineChip.textContent = label;
            offlineChip.classList.add('visible');
        }

        function renderPayload(data) {
                // Stable TV bookmark: when the calendar day rolls over (APP_TIMEZONE
                // on the server), reload so the header date and queue match today.
                if (liveToday && data.session_date && data.session_date !== pageDate) {
                    window.location.reload();
                    return;
                }

                const box = document.getElementById('statusContainer');
                const defaultView = document.getElementById('defaultView');
                const messageView = document.getElementById('messageView');
                const nextUp = document.getElementById('nextUpContainer');

                if (data.status === 'scheduled') {
                    defaultView.style.display = 'none';
                    messageView.style.display = 'block';
                    document.getElementById('messageText').textContent = labels.sessionNotStarted;
                    document.getElementById('messageText').style.color = '#f59e0b';
                    document.getElementById('messageSubtext').textContent = labels.pleaseWait;
                    box.className = 'now-serving-box';
                    nextUp.style.display = 'none';
                    return;
                }

                if (data.status === 'cancelled') {
                    defaultView.style.display = 'none';
                    messageView.style.display = 'block';
                    document.getElementById('messageText').textContent = '❌ ' + labels.sessionCancelled;
                    document.getElementById('messageText').style.color = '#ef4444';
                    document.getElementById('messageSubtext').textContent = labels.contactReception;
                    box.className = 'now-serving-box';
                    nextUp.style.display = 'none';
                    return;
                }

                if (data.status === 'completed') {
                    defaultView.style.display = 'none';
                    messageView.style.display = 'block';
                    document.getElementById('messageText').textContent = labels.sessionEnded;
                    document.getElementById('messageText').style.color = '#94a3b8';
                    document.getElementById('messageSubtext').textContent = labels.nextBooking + ' ' + bookUrl;
                    box.className = 'now-serving-box';
                    nextUp.style.display = 'none';
                    return;
                }

                function renderNextUp(payload) {
                    if (payload.next_booking) {
                        nextUp.style.display = 'block';
                        document.getElementById('nextSerial').textContent = '#' + payload.next_booking;
                        document.getElementById('nextEta').textContent = payload.next_estimated_time
                            ? (' · ~' + payload.next_estimated_time)
                            : '';
                    } else {
                        nextUp.style.display = 'none';
                        document.getElementById('nextSerial').textContent = '';
                        document.getElementById('nextEta').textContent = '';
                    }
                }

                if (data.status === 'paused') {
                    defaultView.style.display = 'none';
                    messageView.style.display = 'block';
                    document.getElementById('messageText').textContent = '⏸ ' + labels.breakInProgress;
                    document.getElementById('messageText').style.color = '#f59e0b';
                    document.getElementById('messageSubtext').textContent = (data.pause_reason || '') + (data.estimated_resume_time ? (' — ' + labels.estimatedResume + ' ' + data.estimated_resume_time) : '');
                    box.className = 'now-serving-box';
                    renderNextUp(data);
                    return;
                }

                // Active or Delayed with active queue
                defaultView.style.display = 'block';
                messageView.style.display = 'none';
                
                document.getElementById('currentSerial').textContent = data.now_serving ? '#' + data.now_serving : '—';
                document.getElementById('currentName').textContent = data.now_serving_name ?? '';

                renderNextUp(data);

                // Handle 'Called' state and announce (chime / voice)
                if (data.is_called && data.now_serving) {
                    box.className = 'now-serving-box calling';
                    document.getElementById('mainLabel').textContent = labels.nowCalling;
                    
                    if (data.called_at !== lastCalledTime) {
                        lastCalledTime = data.called_at;
                        announceCall(data.now_serving, data.now_serving_name);
                    }
                } else {
                    box.className = 'now-serving-box';
                    document.getElementById('mainLabel').textContent = labels.nowServing;
                }
        }

        async function updateScreen() {
            try {
                const res = await fetch(statusUrl);
                if (!res.ok) throw new Error('bad status');
                const data = await res.json();
                lastGoodPayload = data;
                lastGoodAt = new Date().toISOString();
                saveCachedPayload(data);
                pollOffline = false;
                setOfflineChip(false);
                renderPayload(data);
            } catch (e) {
                pollOffline = true;
                const cached = lastGoodPayload || loadCachedPayload();
                if (cached) {
                    lastGoodPayload = cached;
                    const raw = localStorage.getItem(cacheKey);
                    let at = lastGoodAt;
                    try {
                        at = JSON.parse(raw || '{}').saved_at || at;
                    } catch (err) {}
                    setOfflineChip(true, at);
                    renderPayload(cached);
                } else {
                    console.error('Error fetching screen data:', e);
                }
            }
        }

        window.addEventListener('cq-queue-screen', function (event) {
            const data = event.detail;
            if (!data) return;
            lastGoodPayload = data;
            lastGoodAt = new Date().toISOString();
            saveCachedPayload(data);
            renderPayload(data);
        });

        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function () {
                navigator.serviceWorker.register(@json(tenant_web_url('/sw.js'))).catch(function () {});
            });
        }

        const initialCache = loadCachedPayload();
        if (initialCache) {
            lastGoodPayload = initialCache;
        }

        updateScreen();
        setInterval(updateScreen, 2000);
    </script>
</body>
</html>
