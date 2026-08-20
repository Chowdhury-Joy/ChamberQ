<div class="book-serial-confirmed">
    <p class="book-serial-confirmed__kicker">{{ __('Serial') }}</p>
    <p class="book-serial-confirmed__serial">{{ $lastBooked['serial'] ?? '—' }}</p>
    <p class="book-serial-confirmed__name">{{ $lastBooked['name'] ?? '' }}</p>
    <p class="book-serial-confirmed__meta">
        {{ $lastBooked['phone'] ?? '' }}
        @if (filled($lastBooked['sitting'] ?? null))
            · {{ $lastBooked['sitting'] }}
        @endif
    </p>
    <p class="book-serial-confirmed__sms">
        @if (! empty($lastBooked['auto_sms']))
            {{ __('A confirmation SMS will go if the wallet has credit.') }}
        @elseif (filled($lastBooked['sms_url'] ?? null) || filled($lastBooked['whatsapp'] ?? null))
            {{ __('Tap Push SMS or Push WhatsApp below if this doctor uses those.') }}
        @else
            {{ __('No automatic text for this doctor. Open the ticket if you need to share it.') }}
        @endif
    </p>
</div>
