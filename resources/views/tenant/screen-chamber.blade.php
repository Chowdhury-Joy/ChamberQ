<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Live queue') }} — {{ $chamber->name }}</title>

    @php
        $tenant = tenant();
        $isBn = app()->getLocale() === 'bn';
        $themeColor = $tenant->theme_color ?? '#0ea5e9';
        $fontFamily = $tenant->font_family ?? 'Inter';
        if ($isBn && ! in_array($fontFamily, ['Hind Siliguri'], true)) {
            $fontFamily = 'Hind Siliguri';
        }
        $localFontCss = public_asset('css/chamberq-screen-fonts.css');
        $callAudioUrl = $tenant->callAudioUrl();
        $callAnnounceLocale = $tenant->call_announce_locale ?? 'en';
        $usesCallChime = $tenant->usesCallChime();
        $usesCallVoice = $tenant->usesCallVoice();
        $statusUrl = tenant_web_route('api.tenant.screen.chamber.today', ['chamber' => $chamber->id], absolute: false);
    @endphp

    <link rel="stylesheet" href="{{ $localFontCss }}">

    <style>
        :root {
            --theme: {{ $themeColor }};
            --font-family-base: '{{ $fontFamily }}', system-ui, -apple-system, sans-serif;
            --bg: #0f172a;
            --ink: #f8fafc;
            --name: #e2e8f0;
            --micro: #94a3b8;
            --line: #475569;
            --late: #fbbf24;
            --call-fill: #f8fafc;
            --call-ink: #0f172a;
        }

        * { box-sizing: border-box; }
        body {
            font-family: var(--font-family-base);
            margin: 0;
            background: var(--bg);
            color: var(--ink);
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            font-weight: 500;
        }

        .theme-bar {
            height: 6px;
            background: var(--theme);
            flex-shrink: 0;
        }

        .header {
            padding: 1.25rem 1.75rem 1rem;
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            border-bottom: 1px solid var(--line);
        }
        .chamber-name { font-size: 1.75rem; font-weight: 500; }
        .header-date { font-size: 1.15rem; color: var(--micro); }

        .grid {
            flex: 1;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 1rem;
            padding: 1rem 1.25rem 1.5rem;
            min-height: 0;
        }

        .tile {
            border: 1px solid var(--line);
            border-radius: 1.25rem;
            padding: 1.25rem 1.35rem 1.5rem;
            display: flex;
            flex-direction: column;
            min-height: 0;
            background: rgba(255,255,255,0.03);
        }
        .tile.calling {
            background: var(--call-fill);
            color: var(--call-ink);
            border-color: var(--call-ink);
        }
        .tile.calling .micro,
        .tile.calling .room-label,
        .tile.calling .waiting-name { color: #334155; }
        .tile.calling .serial,
        .tile.calling .patient { color: var(--call-ink); }

        .room-label {
            font-size: 1.05rem;
            color: var(--micro);
            margin-bottom: 0.35rem;
            font-weight: 500;
        }
        .micro {
            font-size: 0.95rem;
            color: var(--micro);
            letter-spacing: {{ $isBn ? '0' : '0.04em' }};
            font-weight: 500;
        }
        .serial {
            font-size: clamp(3.5rem, 8vw, 7.5rem);
            font-weight: 500;
            line-height: 1;
            color: #fff;
            margin: 0.35rem 0 0.15rem;
        }
        .patient {
            font-size: clamp(1.25rem, 2.4vw, 2rem);
            color: var(--name);
            font-weight: 500;
            min-height: 1.4em;
        }
        .late {
            margin-top: 0.6rem;
            color: var(--late);
            font-size: 0.95rem;
            font-weight: 500;
        }
        .waiting {
            margin-top: auto;
            padding-top: 1rem;
            border-top: 1px solid var(--line);
        }
        .tile.calling .waiting { border-top-color: #cbd5e1; }
        .waiting-row {
            display: flex;
            gap: 0.5rem;
            font-size: 1.05rem;
            margin-top: 0.35rem;
        }
        .waiting-serial { color: #fff; font-weight: 500; }
        .tile.calling .waiting-serial { color: var(--call-ink); }
        .waiting-name { color: var(--name); }
        .empty {
            margin: auto;
            color: var(--micro);
            font-size: 1.5rem;
            text-align: center;
        }

        .sound-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.92);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 50;
        }
        .sound-overlay.hidden { display: none; }
        .sound-enable-btn {
            background: var(--call-fill);
            color: var(--call-ink);
            border: 0;
            border-radius: 1rem;
            padding: 1.25rem 1.75rem;
            font-size: 1.25rem;
            font-weight: 500;
            cursor: pointer;
            text-align: center;
        }
        .sound-enable-btn small { display: block; margin-top: 0.4rem; color: #475569; font-size: 0.9rem; }
        .sound-toggle {
            position: fixed;
            top: 1rem;
            right: 1rem;
            z-index: 40;
            background: rgba(0,0,0,0.45);
            color: var(--name);
            border: 1px solid var(--line);
            border-radius: 999px;
            padding: 0.4rem 0.85rem;
            font-weight: 500;
        }
        .sound-toggle.hidden { display: none; }
        .offline-chip {
            position: fixed;
            bottom: 1rem;
            right: 1rem;
            z-index: 45;
            padding: 0.5rem 0.85rem;
            border-radius: 999px;
            background: rgba(0, 0, 0, 0.55);
            border: 1px solid var(--line);
            color: var(--name);
            font-size: 0.85rem;
            display: none;
        }
        .offline-chip.visible { display: block; }
    </style>
</head>
<body>
    <div class="theme-bar" aria-hidden="true"></div>

    <div id="soundOverlay" class="sound-overlay" role="button" tabindex="0" aria-label="{{ __('Tap to enable sound') }}">
        <button type="button" class="sound-enable-btn" id="soundEnableBtn">
            {{ __('Tap to enable sound') }}
            <small>{{ __('Required once so call chimes and voice announcements can play on this screen') }}</small>
        </button>
    </div>

    <button type="button" id="soundToggle" class="sound-toggle hidden" aria-pressed="true">{{ __('Sound on') }}</button>

    <div class="header">
        <div class="chamber-name">{{ $chamber->name }}</div>
        <div class="header-date" id="screenDate">{{ \Carbon\Carbon::parse($sessionDate)->translatedFormat('j F Y') }}</div>
    </div>

    <div class="grid" id="roomGrid">
        <div class="empty" id="emptyState">{{ __('No live rooms yet') }}</div>
    </div>

    <div id="offlineChip" class="offline-chip" aria-live="polite"></div>

    @if($usesCallChime)
    <audio id="chimeAudio" src="{{ $callAudioUrl }}" preload="auto"></audio>
    @endif
    @if($usesCallVoice)
    <audio id="announceAudio" preload="auto"></audio>
    @endif

    <script>
        const statusUrl = @json($statusUrl);
        const callAnnounceLocale = @json($callAnnounceLocale);
        const usesCallChime = @json($usesCallChime);
        const usesCallVoice = @json($usesCallVoice);
        const labels = {
            nowServing: @json(__('Now serving')),
            nowCalling: @json(__('Now calling you')),
            paused: @json(__('Break in progress')),
            delayed: @json(__('Running late')),
            next: @json(__('Next')),
        };
        const cacheKey = 'cq-screen:' + statusUrl;
        const offlineChipTemplate = @json(__('Line dropped — last update :time'));
        const MAX_ANNOUNCE_QUEUE = 4;
        const ANNOUNCE_REPEATS = 3;
        const ANNOUNCE_GAP_MS = 700;

        let soundUnlocked = false;
        let soundMuted = false;
        let lastGoodPayload = null;
        let lastGoodAt = null;
        let pageDate = @json($sessionDate);
        const lastAnnouncedByRoom = {};
        const announceQueue = [];
        let announcing = false;
        let announceSequence = 0;

        const audio = document.getElementById('chimeAudio');
        const announceAudio = document.getElementById('announceAudio');
        const overlay = document.getElementById('soundOverlay');
        const toggle = document.getElementById('soundToggle');
        const offlineChip = document.getElementById('offlineChip');
        const grid = document.getElementById('roomGrid');

        const audioDebug = new URLSearchParams(window.location.search).has('debug');
        function logAudio(...args) {
            if (audioDebug) console.log('[chamber screen audio]', ...args);
        }

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
            if ('speechSynthesis' in window) {
                try { window.speechSynthesis.getVoices(); } catch (e) {}
            }
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
            if (!Number.isFinite(n) || n < 1 || n > 99) return Promise.resolve(false);

            return new Promise(function (resolve) {
                const url = announceBaseUrl + '/number-' + n + '.wav';
                const onEnded = function () { cleanup(); resolve(true); };
                const onError = function () { cleanup(); resolve(false); };
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

        function pickNameVoice() {
            if (!('speechSynthesis' in window)) return null;
            const voices = window.speechSynthesis.getVoices() || [];
            if (!voices.length) return null;
            const wantBn = callAnnounceLocale === 'bn';
            const prefix = wantBn ? 'bn' : 'en';
            return voices.find(function (v) {
                return String(v.lang || '').toLowerCase().startsWith(prefix);
            }) || voices.find(function (v) {
                return String(v.lang || '').toLowerCase().startsWith('en');
            }) || voices[0];
        }

        function speakName(name) {
            if (!soundUnlocked || soundMuted) return Promise.resolve(false);
            if (!('speechSynthesis' in window)) return Promise.resolve(false);
            const text = String(name || '').trim();
            if (!text) return Promise.resolve(false);

            return new Promise(function (resolve) {
                try { window.speechSynthesis.cancel(); } catch (e) {}
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
                try { window.speechSynthesis.speak(utterance); } catch (e) {
                    logAudio('name TTS failed', e);
                    done(false);
                }
            });
        }

        async function speakCall(roomLabel, serial, name) {
            if (!soundUnlocked || soundMuted) return;
            const mySequence = ++announceSequence;
            for (let i = 0; i < ANNOUNCE_REPEATS; i++) {
                if (soundMuted || mySequence !== announceSequence) return;
                await speakName(roomLabel);
                if (soundMuted || mySequence !== announceSequence) return;
                const played = await playAnnounceClip(serial);
                if (!played) return;
                if (soundMuted || mySequence !== announceSequence) return;
                await speakName(name);
                if (i < ANNOUNCE_REPEATS - 1) {
                    await new Promise(function (r) { setTimeout(r, ANNOUNCE_GAP_MS); });
                }
            }
        }

        async function drainAnnounceQueue() {
            if (announcing) return;
            announcing = true;
            while (announceQueue.length) {
                const item = announceQueue.shift();
                if (usesCallChime) {
                    playChime();
                    await new Promise(function (r) { setTimeout(r, 900); });
                }
                if (usesCallVoice) {
                    await speakCall(item.roomLabel, item.serial, item.name);
                }
            }
            announcing = false;
        }

        function enqueueCall(room) {
            if (!room.is_called || !room.announce_key || !room.now_serving) return;
            if (lastAnnouncedByRoom[room.session_id] === room.announce_key) return;
            lastAnnouncedByRoom[room.session_id] = room.announce_key;
            if (announceQueue.length >= MAX_ANNOUNCE_QUEUE) return;
            announceQueue.push({
                roomLabel: room.label,
                serial: room.now_serving,
                name: room.now_serving_name,
            });
            drainAnnounceQueue();
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
                announceQueue.length = 0;
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
                return JSON.parse(raw)?.data || null;
            } catch (e) {
                return null;
            }
        }
        function saveCachedPayload(data) {
            try {
                localStorage.setItem(cacheKey, JSON.stringify({ saved_at: new Date().toISOString(), data }));
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
            offlineChip.textContent = offlineChipTemplate.replace(':time', formatChipTime(atIso || new Date().toISOString()));
            offlineChip.classList.add('visible');
        }

        function escapeHtml(value) {
            return String(value ?? '').replace(/[&<>"']/g, function (ch) {
                return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[ch];
            });
        }

        function renderRooms(data) {
            if (data.session_date && data.session_date !== pageDate) {
                window.location.reload();
                return;
            }

            const rooms = Array.isArray(data.rooms) ? data.rooms : [];
            if (!rooms.length) {
                grid.innerHTML = '<div class="empty">{{ __('No live rooms yet') }}</div>';
                return;
            }

            grid.innerHTML = rooms.map(function (room) {
                const calling = !!room.is_called;
                const serial = room.now_serving ? '#' + room.now_serving : '—';
                const statusLabel = calling
                    ? labels.nowCalling
                    : (room.status === 'paused'
                        ? labels.paused
                        : (room.status === 'delayed' ? labels.delayed : labels.nowServing));
                const late = room.status === 'delayed' && room.delay_minutes
                    ? '<div class="late">' + escapeHtml(labels.delayed) + ' · ' + escapeHtml(String(room.delay_minutes)) + ' min</div>'
                    : (room.status === 'paused' && room.estimated_resume_time
                        ? '<div class="late">' + escapeHtml(labels.paused) + ' · ' + escapeHtml(room.estimated_resume_time) + '</div>'
                        : '');
                const next = (room.next || []).slice(0, 3).map(function (row) {
                    return '<div class="waiting-row"><span class="waiting-serial">#' + escapeHtml(row.serial) + '</span><span class="waiting-name">' + escapeHtml(row.name) + '</span></div>';
                }).join('');

                return '<div class="tile' + (calling ? ' calling' : '') + '">'
                    + '<div class="room-label">' + escapeHtml(room.label) + '</div>'
                    + '<div class="micro">' + escapeHtml(statusLabel) + '</div>'
                    + '<div class="serial">' + escapeHtml(serial) + '</div>'
                    + '<div class="patient">' + escapeHtml(room.now_serving_name || '') + '</div>'
                    + late
                    + '<div class="waiting"><div class="micro">' + escapeHtml(labels.next) + '</div>' + (next || '<div class="waiting-name">—</div>') + '</div>'
                    + '</div>';
            }).join('');

            rooms.forEach(enqueueCall);
        }

        async function updateScreen() {
            try {
                const res = await fetch(statusUrl);
                if (!res.ok) throw new Error('bad status');
                const data = await res.json();
                lastGoodPayload = data;
                lastGoodAt = new Date().toISOString();
                saveCachedPayload(data);
                setOfflineChip(false);
                renderRooms(data);
            } catch (e) {
                const cached = lastGoodPayload || loadCachedPayload();
                if (cached) {
                    lastGoodPayload = cached;
                    const raw = localStorage.getItem(cacheKey);
                    let at = lastGoodAt;
                    try { at = JSON.parse(raw || '{}').saved_at || at; } catch (err) {}
                    setOfflineChip(true, at);
                    renderRooms(cached);
                } else {
                    console.error('Error fetching chamber screen data:', e);
                }
            }
        }

        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function () {
                navigator.serviceWorker.register(@json(tenant_web_url('/sw.js'))).catch(function () {});
            });
        }

        const initialCache = loadCachedPayload();
        if (initialCache) lastGoodPayload = initialCache;
        updateScreen();
        setInterval(updateScreen, 2000);
    </script>
</body>
</html>
