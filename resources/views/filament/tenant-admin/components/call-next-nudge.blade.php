{{--
    Status line for the gap between "visit finished" and "next patient called".

    Usually a non-event: staff see the patient walk out and press Call next.
    This only speaks up when nobody has, so a doctor who got distracted after
    handing over the prescription does not quietly stall their own waiting room.

    Counted client-side because Live Queue Control does not poll — a server-side
    check would never fire there. Skew-corrected like the consult timer, so a
    mis-set desk clock cannot show a nonsense count.
--}}
@props(['booking'])

@once
    <style>
        .cn-status {
            display: flex; align-items: center; gap: 0.5rem;
            font-size: 0.9375rem; font-weight: 600;
        }
        .cn-status.cn-ok { color: var(--gray-950); }
        .dark .cn-status.cn-ok { color: var(--color-white); }
        .cn-status.cn-late { color: var(--warning-700); }
        .dark .cn-status.cn-late { color: var(--warning-400); }
        .cn-icon { width: 1.25rem; height: 1.25rem; flex-shrink: 0; }
        .cn-status.cn-ok .cn-icon { color: var(--success-600); }
        .dark .cn-status.cn-ok .cn-icon { color: var(--success-400); }
        .cn-status.cn-late .cn-icon { color: var(--warning-600); }
        .dark .cn-status.cn-late .cn-icon { color: var(--warning-400); }
    </style>
@endonce

<div
    wire:key="cn-nudge-{{ $booking->id }}"
    x-data="{
        completedAt: Date.parse(@js(optional($booking->completed_at)->toIso8601String())),
        skew: Date.now() - Date.parse(@js(now()->toIso8601String())),
        thresholdMs: {{ \App\Models\Booking::CALL_NEXT_NUDGE_SECONDS }} * 1000,
        late: false,
        secs: 0,
        timer: null,
        init() {
            if (!this.completedAt) return;
            const tick = () => {
                const elapsed = Date.now() - this.skew - this.completedAt;
                this.secs = Math.max(0, Math.floor(elapsed / 1000));
                this.late = elapsed >= this.thresholdMs;
            };
            tick();
            this.timer = setInterval(tick, 1000);
        },
        destroy() { clearInterval(this.timer) },
    }"
    class="cn-status"
    :class="late ? 'cn-late' : 'cn-ok'"
>
    <span x-show="!late">
        <x-filament::icon icon="heroicon-o-check-circle" class="cn-icon" />
    </span>
    <span x-show="late" x-cloak>
        <x-filament::icon icon="heroicon-o-bell-alert" class="cn-icon" />
    </span>

    <span x-show="!late">{{ __('Visit completed — ready for next patient') }}</span>
    <span x-show="late" x-cloak>
        {{ __('Nobody called yet') }} — <span x-text="secs"></span>s
    </span>
</div>
