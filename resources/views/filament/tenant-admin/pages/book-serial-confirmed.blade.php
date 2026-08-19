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
        {{ __('A confirmation SMS will go if the wallet has credit. You can also send WhatsApp or open the ticket.') }}
    </p>
</div>
