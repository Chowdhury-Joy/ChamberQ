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
    </style>
</head>
<body>
    <div class="header">
        <div>
            <div class="chamber-name">{{ $scheduleSession->chamber->name }}</div>
            <div class="doctor-name">{{ $scheduleSession->doctor->name }}</div>
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

    <!-- Audio element for chime -->
    <audio id="chimeAudio" src="/audio/chime.mp3" preload="auto"></audio>

    <script>
        const statusUrl = @json(route('api.tenant.screen', ['session' => $scheduleSession->id, 'date' => $sessionDate]));
        let lastCalledSerial = null;
        let lastCalledTime = null;

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

                if (data.status === 'paused') {
                    defaultView.style.display = 'none';
                    messageView.style.display = 'block';
                    document.getElementById('messageText').textContent = '⏸ বিরতি চলছে';
                    document.getElementById('messageSubtext').innerHTML = `${data.pause_reason}<br>আনুমানিক শুরু: ${data.estimated_resume_time}`;
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

                // Handle 'Called' state and Chime
                if (data.is_called && data.now_serving) {
                    box.className = 'now-serving-box calling';
                    document.getElementById('mainLabel').textContent = 'আপনাকে ডাকা হচ্ছে';
                    
                    // Play chime if this is a new call
                    if (data.called_at !== lastCalledTime) {
                        lastCalledTime = data.called_at;
                        const audio = document.getElementById('chimeAudio');
                        // Play requires user interaction normally, but screens might be configured to allow autoplay
                        audio.play().catch(e => console.log('Audio play blocked by browser', e));
                    }
                } else {
                    box.className = 'now-serving-box';
                    document.getElementById('mainLabel').textContent = 'NOW SERVING';
                }

            } catch (e) {
                console.error('Error fetching screen data:', e);
            }
        }

        // Must click screen once to allow audio to play (browser policy)
        document.body.addEventListener('click', function() {
            document.getElementById('chimeAudio').load();
        }, { once: true });

        updateScreen();
        setInterval(updateScreen, 2000); // Fast polling for screen
    </script>
</body>
</html>
