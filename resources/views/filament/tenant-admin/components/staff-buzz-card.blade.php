{{-- Staff pocket buzz — subscribe from Daily Roster or Live Queue Control. --}}
@php
    $vapidPublicKey = (string) config('webpush.vapid.public_key');
    $pushUrl = url('/api/staff/push');
    $csrfToken = csrf_token();
@endphp

<div class="staff-buzz-card no-print" id="staffBuzzCard" style="margin-bottom:1rem;padding:1rem;border:1px solid rgb(228 228 231);border-radius:0.75rem;background:rgb(250 250 250);">
    <p style="font-weight:700;margin:0 0 0.35rem;">{{ __('Buzz this phone when a sitting needs you') }}</p>
    <p class="text-muted" style="margin:0 0 0.75rem;font-size:0.9rem;">
        @if ($vapidPublicKey !== '')
            {{ __('One tap — we ping you when a sticky note appears or changes, even if this tab is closed.') }}
        @else
            {{ __('Pocket buzz is not set up on this server yet. Sticky notes still show on screen.') }}
        @endif
    </p>
  @if ($vapidPublicKey !== '')
    <div id="staffBuzzActions" style="display:flex;flex-wrap:wrap;gap:0.5rem;">
        <button type="button" id="staffBuzzAllow" class="fi-btn fi-btn-size-sm fi-color-primary">Allow once</button>
        <button type="button" id="staffBuzzLater" class="fi-btn fi-btn-size-sm fi-color-gray">Not now</button>
    </div>
    <p id="staffBuzzDenied" hidden style="margin:0.75rem 0 0;font-size:0.875rem;color:rgb(185 28 28);">{{ __('Notifications blocked — check browser settings.') }}</p>
  @endif
</div>

@if ($vapidPublicKey !== '')
<script>
(function () {
    const card = document.getElementById('staffBuzzCard');
    const actions = document.getElementById('staffBuzzActions');
    const denied = document.getElementById('staffBuzzDenied');
    const allow = document.getElementById('staffBuzzAllow');
    const later = document.getElementById('staffBuzzLater');
    if (!card || !actions || !allow || !later) return;

    const hideActions = () => { actions.hidden = true; };
    later.addEventListener('click', hideActions);

    function urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        const raw = window.atob(base64);
        const out = new Uint8Array(raw.length);
        for (let i = 0; i < raw.length; ++i) out[i] = raw.charCodeAt(i);
        return out;
    }

    allow.addEventListener('click', async () => {
        if (!('Notification' in window) || !('serviceWorker' in navigator) || !('PushManager' in window)) {
            if (denied) denied.hidden = false;
            hideActions();
            return;
        }
        try {
            const perm = await Notification.requestPermission();
            if (perm !== 'granted') {
                if (denied) denied.hidden = false;
                hideActions();
                return;
            }
            const reg = await navigator.serviceWorker.ready;
            const sub = await reg.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(@json($vapidPublicKey)),
            });
            await fetch(@json($pushUrl), {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': @json($csrfToken),
                },
                credentials: 'same-origin',
                body: JSON.stringify(sub.toJSON()),
            });
            hideActions();
        } catch (e) {
            if (denied) denied.hidden = false;
            hideActions();
        }
    });
})();
</script>
@endif
