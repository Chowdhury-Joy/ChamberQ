<x-filament-panels::page>
    <style>
        .book-serial-overlay {
            position: fixed;
            inset: 0;
            z-index: 60;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            background: rgba(24, 24, 27, 0.45);
        }
        .book-serial-dialog {
            width: min(28rem, 100%);
            max-height: calc(100vh - 2rem);
            overflow: auto;
            background: var(--color-white);
            border: 1px solid var(--gray-200);
            border-radius: 0.75rem;
            padding: 1.25rem 1.25rem 1rem;
        }
        .dark .book-serial-dialog {
            background: var(--gray-900);
            border-color: var(--gray-800);
        }
        .book-serial-dialog__title {
            margin: 0 0 0.75rem;
            font-size: 1rem;
            font-weight: 600;
            color: var(--gray-950);
        }
        .dark .book-serial-dialog__title { color: var(--color-white); }
        .book-serial-confirmed { text-align: center; padding: 0.25rem 0 0.75rem; }
        .book-serial-confirmed__kicker {
            margin: 0;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: var(--gray-500);
        }
        .book-serial-confirmed__serial {
            margin: 0.35rem 0 0.75rem;
            font-size: 3rem;
            font-weight: 700;
            font-variant-numeric: tabular-nums;
            line-height: 1.1;
            color: var(--gray-950);
        }
        .dark .book-serial-confirmed__serial { color: var(--color-white); }
        .book-serial-confirmed__name {
            margin: 0;
            font-size: 1.05rem;
            font-weight: 600;
            color: var(--gray-950);
        }
        .dark .book-serial-confirmed__name { color: var(--color-white); }
        .book-serial-confirmed__meta,
        .book-serial-confirmed__sms {
            margin: 0.35rem 0 0;
            font-size: 0.875rem;
            color: var(--gray-600);
        }
        .book-serial-confirmed__sms { font-size: 0.8125rem; color: var(--gray-500); }
        .book-serial-dialog__actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            justify-content: flex-end;
            margin-top: 1rem;
        }
        @media (max-width: 640px) {
            .book-serial-dialog__actions { flex-direction: column-reverse; }
            .book-serial-dialog__actions a,
            .book-serial-dialog__actions button { width: 100%; }
        }
    </style>

    <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
        {{ __('For a phone call or the front desk when the patient is not standing in the queue. Pick the date first. New Walk-In on Daily Roster is still for today only.') }}
    </p>

    <form wire:submit="book" class="space-y-6">
        {{ $this->form }}

        <div class="flex justify-end">
            <x-filament::button type="submit" size="lg">
                {{ __('Book serial') }}
            </x-filament::button>
        </div>
    </form>

    @if ($showBookedSerialModal && filled($lastBooked))
        <div
            class="book-serial-overlay"
            role="dialog"
            aria-modal="true"
            aria-labelledby="book-serial-dialog-title"
            wire:click.self="closeBookedSerialModal"
        >
            <div class="book-serial-dialog">
                <h2 id="book-serial-dialog-title" class="book-serial-dialog__title">
                    {{ __('Serial :n booked for :date', [
                        'n' => $lastBooked['serial'],
                        'date' => $lastBooked['date'],
                    ]) }}
                </h2>

                @include('filament.tenant-admin.pages.book-serial-confirmed', ['lastBooked' => $lastBooked])

                <div class="book-serial-dialog__actions" x-data="{
                    sending: false,
                    done: false,
                    error: null,
                    async sendSms() {
                        this.sending = true;
                        this.error = null;
                        try {
                            const res = await fetch(@js($lastBooked['sms_url'] ?? ''), {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content
                                        || document.querySelector('input[name=_token]')?.value
                                        || '',
                                    'X-Requested-With': 'XMLHttpRequest',
                                },
                                body: '{}',
                            });
                            const data = await res.json().catch(() => ({}));
                            if (! res.ok) {
                                this.error = data.message || @js(__('Could not send SMS'));
                            } else if (data.status === 'sent') {
                                this.done = true;
                            } else if (data.status === 'skipped_no_balance') {
                                this.error = @js(__('No SMS credits left'));
                            } else if (data.status === 'skipped_pref_off') {
                                this.error = @js(__('SMS is off for this doctor'));
                            } else if (data.status === 'skipped_disabled') {
                                this.error = @js(__('SMS is disabled'));
                            } else {
                                this.error = data.status || @js(__('Could not send SMS'));
                            }
                        } catch (e) {
                            this.error = @js(__('Could not send SMS'));
                        } finally {
                            this.sending = false;
                        }
                    }
                }">
                    <x-filament::button color="gray" wire:click="closeBookedSerialModal">
                        {{ __('Done') }}
                    </x-filament::button>
                    @if (filled($lastBooked['whatsapp'] ?? null))
                        <x-filament::button
                            tag="a"
                            href="{{ $lastBooked['whatsapp'] }}"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            {{ __('Push WhatsApp') }}
                        </x-filament::button>
                    @endif
                    @if (filled($lastBooked['sms_url'] ?? null))
                        <x-filament::button
                            type="button"
                            color="warning"
                            x-on:click="sendSms()"
                            x-bind:disabled="sending || done"
                        >
                            <span x-show="! done" x-text="sending ? @js(__('Sending…')) : @js(__('Push SMS'))"></span>
                            <span x-show="done" x-cloak>{{ __('Sent') }}</span>
                        </x-filament::button>
                    @endif
                    <p
                        class="basis-full text-xs text-danger-600 dark:text-danger-400"
                        style="flex-basis: 100%;"
                        x-show="error"
                        x-text="error"
                        x-cloak
                    ></p>
                    @if (filled($lastBooked['ticket'] ?? null))
                        <x-filament::button
                            tag="a"
                            color="gray"
                            href="{{ $lastBooked['ticket'] }}"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            {{ __('Open ticket') }}
                        </x-filament::button>
                    @endif
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>
