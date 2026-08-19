{{-- Staff pocket buzz — subscribe from Daily Roster or Live Queue Control. --}}
@php
    $vapidPublicKey = (string) config('webpush.vapid.public_key');
    $pushUrl = url('/api/staff/push');
    $csrfToken = csrf_token();
@endphp

<div class="staff-buzz-card no-print" id="staffBuzzCard">
    <p class="staff-buzz-card__title">{{ __('Buzz this phone when a sitting needs you') }}</p>
    <p class="staff-buzz-card__hint">
        @if ($vapidPublicKey !== '')
            {{ __('One tap — we ping you when a sticky note appears or changes, even if this tab is closed.') }}
        @else
            {{ __('Pocket buzz is not set up on this server yet. Sticky notes still show on screen.') }}
        @endif
    </p>
  @if ($vapidPublicKey !== '')
    <div id="staffBuzzActions" class="staff-buzz-card__actions">
        <button type="button" id="staffBuzzAllow" class="fi-btn fi-btn-size-sm fi-color-primary">Allow once</button>
        <button type="button" id="staffBuzzLater" class="fi-btn fi-btn-size-sm fi-color-gray">Not now</button>
    </div>
    <p id="staffBuzzDenied" class="staff-buzz-card__denied" hidden>{{ __('Notifications blocked — check browser settings.') }}</p>
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
