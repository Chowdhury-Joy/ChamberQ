{{--
    "The intervention room is prepared" — shown to the doctor while he is in
    the regular visit chamber, spoken aloud, and gone again in four seconds.

    Two halves on purpose:

    * The card sits inside `wire:ignore`, so the 3s consult-screen poll cannot
      morph a popup out from under the doctor mid-announcement.
    * One hidden, `wire:key`ed trigger per alert lives *outside* it. Livewire's
      morph keeps a node whose key it already has and only inserts genuinely
      new ones, so `x-init` fires exactly once per newly prepped room instead of
      once every three seconds. `localStorage` is the belt to that braces: a
      reload re-renders the same trigger, and the doctor must not be told twice.

    @param list<array{key: string, number: int, procedure: string, patient: string, speech: string}> $alerts
--}}
@php($alerts = $alerts ?? [])

<div wire:ignore>
    <div
        x-data="interventionReadyAlert()"
        x-show="current"
        x-cloak
        x-transition.opacity.duration.200ms
        class="cq-ot-ready"
        role="alert"
        aria-live="assertive"
    >
        <div class="cq-ot-ready__card" x-on:click="dismiss()">
            <div class="cq-ot-ready__icon" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
            </div>
            <div class="cq-ot-ready__body">
                <div class="cq-ot-ready__title">{{ __('Intervention room is prepared') }}</div>
                <div class="cq-ot-ready__line">
                    <span class="cq-ot-ready__number" x-text="current?.number"></span>
                    <span class="cq-ot-ready__procedure" x-text="current?.procedure || @js(__('Procedure'))"></span>
                </div>
                <div class="cq-ot-ready__patient" x-text="current?.patient"></div>
            </div>
        </div>
    </div>
</div>

@foreach ($alerts as $alert)
    <div
        wire:key="ot-ready-{{ $alert['key'] }}"
        x-data
        x-init="window.dispatchEvent(new CustomEvent('chamberq-intervention-ready', { detail: @js($alert) }))"
        hidden
    ></div>
@endforeach

@once
    <style>
        [x-cloak] { display: none !important; }
        .cq-ot-ready {
            position: fixed; inset-inline: 0; top: 1rem; z-index: 60;
            display: flex; justify-content: center; padding: 0 1rem; pointer-events: none;
        }
        .cq-ot-ready__card {
            pointer-events: auto; cursor: pointer;
            display: flex; align-items: center; gap: 0.875rem;
            width: 100%; max-width: 28rem; padding: 1rem 1.125rem;
            border-radius: 0.875rem;
            background-color: var(--warning-50);
            border: 1px solid var(--warning-300);
            box-shadow: 0 12px 30px -8px rgb(0 0 0 / 0.28);
        }
        .dark .cq-ot-ready__card {
            background-color: color-mix(in srgb, var(--warning-500) 16%, var(--gray-900));
            border-color: color-mix(in srgb, var(--warning-400) 45%, transparent);
        }
        .cq-ot-ready__icon { flex-shrink: 0; width: 2.25rem; height: 2.25rem; color: var(--warning-600); }
        .dark .cq-ot-ready__icon { color: var(--warning-300); }
        .cq-ot-ready__body { min-width: 0; }
        .cq-ot-ready__title {
            font-size: 0.9375rem; font-weight: 700; color: var(--warning-800);
        }
        .dark .cq-ot-ready__title { color: var(--warning-200); }
        .cq-ot-ready__line {
            display: flex; align-items: baseline; gap: 0.5rem; margin-top: 0.25rem;
            font-size: 0.9375rem; color: var(--gray-900);
        }
        .dark .cq-ot-ready__line { color: var(--color-white); }
        .cq-ot-ready__number {
            font-size: 1.375rem; font-weight: 800; line-height: 1;
        }
        .cq-ot-ready__number::before { content: '#'; font-size: 0.875rem; font-weight: 600; opacity: 0.6; }
        .cq-ot-ready__procedure { font-weight: 600; }
        .cq-ot-ready__patient { margin-top: 0.125rem; font-size: 0.8125rem; color: var(--gray-600); }
        .dark .cq-ot-ready__patient { color: var(--gray-400); }
    </style>

    @script
    <script>
        Alpine.data('interventionReadyAlert', () => ({
            /** How long the card stays up. The voice may still be finishing. */
            visibleMs: 4000,

            /** Announced keys, so a reload cannot repeat an announcement. */
            storageKey: 'cq.otReady.announced',

            current: null,
            queue: [],
            timer: null,

            init() {
                window.addEventListener('chamberq-intervention-ready', (event) => {
                    this.enqueue(event.detail);
                });
            },

            announced() {
                try {
                    const raw = window.localStorage.getItem(this.storageKey);
                    const parsed = raw ? JSON.parse(raw) : [];

                    return Array.isArray(parsed) ? parsed : [];
                } catch (e) {
                    return [];
                }
            },

            remember(key) {
                try {
                    // Capped: this list only exists to stop repeats, and an
                    // unbounded one would grow for every procedure ever prepped
                    // on this machine.
                    const keys = this.announced().concat([key]).slice(-50);
                    window.localStorage.setItem(this.storageKey, JSON.stringify(keys));
                } catch (e) {
                    // Private mode / full quota — the wire:key trigger already
                    // fires once per render, so losing this only risks a repeat
                    // after a reload.
                }
            },

            enqueue(alert) {
                if (! alert || ! alert.key || this.announced().includes(alert.key)) {
                    return;
                }

                this.remember(alert.key);
                this.queue.push(alert);

                if (! this.current) {
                    this.showNext();
                }
            },

            showNext() {
                clearTimeout(this.timer);
                this.current = this.queue.shift() || null;

                if (! this.current) {
                    return;
                }

                this.speak(this.current.speech);
                this.timer = setTimeout(() => this.showNext(), this.visibleMs);
            },

            dismiss() {
                clearTimeout(this.timer);
                this.queue = [];
                this.current = null;

                try {
                    window.speechSynthesis?.cancel();
                } catch (e) {}
            },

            /**
             * Browser TTS, same approach as the waiting-room screen. Silent
             * failure is deliberate: a browser that blocks speech (no voice
             * pack, sound off, no gesture yet on a freshly opened tab) still
             * leaves the doctor the card on screen.
             */
            speak(text) {
                if (! text || ! ('speechSynthesis' in window)) {
                    return;
                }

                try {
                    window.speechSynthesis.cancel();

                    const utterance = new SpeechSynthesisUtterance(String(text));
                    const voices = window.speechSynthesis.getVoices() || [];

                    // The sentence is translated, so ask for a voice in the
                    // page's language first. Bangla voice packs are rare on a
                    // clinic desktop — any English voice reading Bangla script
                    // is still better than silence.
                    const wanted = String(document.documentElement.lang || 'en').slice(0, 2).toLowerCase();
                    const match = voices.find((voice) => String(voice.lang || '').toLowerCase().startsWith(wanted))
                        || voices.find((voice) => String(voice.lang || '').toLowerCase().startsWith('en'));

                    if (match) {
                        utterance.voice = match;
                        utterance.lang = match.lang;
                    } else {
                        utterance.lang = wanted === 'bn' ? 'bn-BD' : 'en-US';
                    }

                    utterance.rate = 0.95;
                    window.speechSynthesis.speak(utterance);
                } catch (e) {}
            },
        }));
    </script>
    @endscript
@endonce
