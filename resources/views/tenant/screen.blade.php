<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Live Queue - {{ $scheduleSession->chamber->name }}</title>
    
    @php
        $tenant = tenant();
        $themeColor = $tenant->theme_color ?? '#0ea5e9';
        $fontFamily = $tenant->font_family ?? 'Inter';
        $callAudioUrl = $tenant->callAudioUrl();
        $callAnnounceLocale = $tenant->call_announce_locale ?? 'en';
        $usesCallChime = $tenant->usesCallChime();
        $usesCallVoice = $tenant->usesCallVoice();
        
        $fontUrl = match($fontFamily) {
            'Outfit' => 'https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap',
            'Roboto' => 'https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap',
            'Hind Siliguri' => 'https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&display=swap',
            default => 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap',
        };
    @endphp

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="{{ $fontUrl }}">
    
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
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 2rem;
            color: #cbd5e1;
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

        .next-up span {
            font-weight: 700;
            color: var(--color-primary);
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
            <div style="font-size: 1.5rem; color: #94a3b8;">{{ \Carbon\Carbon::parse($sessionDate)->translatedFormat('j F Y') }}</div>
        </div>
    </div>

    <div class="main-content">
        <div id="statusContainer" class="now-serving-box">
            <div id="defaultView">
                <div class="label" id="mainLabel">{{ __('NOW SERVING') }}</div>
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
        {{ __('Next:') }} <span id="nextSerial"></span>
    </div>

    @if($usesCallChime)
    <audio id="chimeAudio" src="{{ $callAudioUrl }}" preload="auto"></audio>
    @endif
    @if($usesCallVoice)
    <audio id="announceAudio" preload="auto"></audio>
    @endif

    <script>
        const statusUrl = @json(tenant_web_route('api.tenant.screen', ['session' => $scheduleSession->id, 'date' => $sessionDate]));
        const callAnnounceLocale = @json($callAnnounceLocale);
        const usesCallChime = @json($usesCallChime);
        const usesCallVoice = @json($usesCallVoice);
        let lastCalledTime = null;
        let soundUnlocked = false;
        let soundMuted = false;

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
                    console.log('Audio unlock failed', e);
                }
            }

            // Unlock recorded “Number twelve” clips the same way as the chime.
            if (usesCallVoice && announceAudio) {
                try {
                    announceAudio.src = @json(asset('audio/announce/number-1.wav'));
                    announceAudio.muted = true;
                    await announceAudio.play();
                    announceAudio.pause();
                    announceAudio.currentTime = 0;
                    announceAudio.muted = false;
                    announceAudio.removeAttribute('src');
                    announceAudio.load();
                } catch (e) {
                    console.log('Announce unlock failed', e);
                }
            }

            soundUnlocked = true;
            overlay.classList.add('hidden');
            toggle.classList.remove('hidden');
            updateToggleLabel();
        }

        function updateToggleLabel() {
            toggle.textContent = soundMuted ? @json(__('Sound off')) : @json(__('Sound on'));
            toggle.setAttribute('aria-pressed', soundMuted ? 'false' : 'true');
        }

        function playChime() {
            if (!soundUnlocked || soundMuted || !audio) return;
            audio.currentTime = 0;
            audio.play().catch(e => console.log('Audio play blocked by browser', e));
        }

        const announceBaseUrl = @json(rtrim(asset('audio/announce'), '/'));

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
                    console.log('Announce play blocked', e);
                    cleanup();
                    resolve(false);
                });
            });
        }

        async function speakCall(serial) {
            if (!soundUnlocked || soundMuted) return;
            // Pre-recorded only — never fall back to browser TTS (sounds ghostly).
            await playAnnounceClip(serial);
        }

        function announceCall(serial) {
            if (!soundUnlocked || soundMuted) return;

            if (usesCallChime) {
                playChime();
            }

            if (usesCallVoice) {
                const delay = usesCallChime ? 900 : 0;
                setTimeout(function () { speakCall(serial); }, delay);
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
                if (announceAudio) {
                    announceAudio.pause();
                    announceAudio.currentTime = 0;
                }
            }
            updateToggleLabel();
        });

        async function updateScreen() {
            try {
                const res = await fetch(statusUrl);
                if (!res.ok) return;
                const data = await res.json();

                const box = document.getElementById('statusContainer');
                const defaultView = document.getElementById('defaultView');
                const messageView = document.getElementById('messageView');
                const nextUp = document.getElementById('nextUpContainer');

                if (data.status === 'scheduled') {
                    defaultView.style.display = 'none';
                    messageView.style.display = 'block';
                    document.getElementById('messageText').textContent = 'সেশন এখনো শুরু হয়নি';
                    document.getElementById('messageText').style.color = '#f59e0b';
                    document.getElementById('messageSubtext').textContent = 'দয়া করে অপেক্ষা করুন';
                    box.className = 'now-serving-box';
                    nextUp.style.display = 'none';
                    return;
                }

                if (data.status === 'cancelled') {
                    defaultView.style.display = 'none';
                    messageView.style.display = 'block';
                    document.getElementById('messageText').textContent = '❌ আজকের সেশন বাতিল করা হয়েছে';
                    document.getElementById('messageText').style.color = '#ef4444';
                    document.getElementById('messageSubtext').textContent = 'রিসেপশনে যোগাযোগ করুন';
                    box.className = 'now-serving-box';
                    nextUp.style.display = 'none';
                    return;
                }

                if (data.status === 'completed') {
                    defaultView.style.display = 'none';
                    messageView.style.display = 'block';
                    document.getElementById('messageText').textContent = 'সেশন শেষ হয়েছে';
                    document.getElementById('messageText').style.color = '#94a3b8';
                    document.getElementById('messageSubtext').textContent = 'আজকের কিউ শেষ';
                    box.className = 'now-serving-box';
                    nextUp.style.display = 'none';
                    return;
                }

                if (data.status === 'paused') {
                    defaultView.style.display = 'none';
                    messageView.style.display = 'block';
                    document.getElementById('messageText').textContent = '⏸ বিরতি চলছে';
                    document.getElementById('messageText').style.color = '#f59e0b';
                    document.getElementById('messageSubtext').textContent = (data.pause_reason || '') + (data.estimated_resume_time ? (' — আনুমানিক শুরু: ' + data.estimated_resume_time) : '');
                    box.className = 'now-serving-box';
                    
                    if (data.next_booking) {
                        nextUp.style.display = 'block';
                        document.getElementById('nextSerial').textContent = '#' + data.next_booking;
                    } else {
                        nextUp.style.display = 'none';
                    }
                    return;
                }

                // Active or Delayed with active queue
                defaultView.style.display = 'block';
                messageView.style.display = 'none';
                
                document.getElementById('currentSerial').textContent = data.now_serving ? '#' + data.now_serving : '—';
                document.getElementById('currentName').textContent = data.now_serving_name ?? '';

                if (data.next_booking) {
                    nextUp.style.display = 'block';
                    document.getElementById('nextSerial').textContent = '#' + data.next_booking;
                } else {
                    nextUp.style.display = 'none';
                }

                // Handle 'Called' state and announce (chime / voice)
                if (data.is_called && data.now_serving) {
                    box.className = 'now-serving-box calling';
                    document.getElementById('mainLabel').textContent = 'আপনাকে ডাকা হচ্ছে';
                    
                    if (data.called_at !== lastCalledTime) {
                        lastCalledTime = data.called_at;
                        announceCall(data.now_serving);
                    }
                } else {
                    box.className = 'now-serving-box';
                    document.getElementById('mainLabel').textContent = 'NOW SERVING';
                }

            } catch (e) {
                console.error('Error fetching screen data:', e);
            }
        }

        updateScreen();
        setInterval(updateScreen, 2000);
    </script>
</body>
</html>
