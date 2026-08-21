<div class="book-serial-confirmed">
    <p class="book-serial-confirmed__kicker">{{ __('Serial') }}</p>
    <p class="book-serial-confirmed__serial">{{ $lastBooked['serial'] ?? '—' }}</p>
    <p class="book-serial-confirmed__name">{{ $lastBooked['name'] ?? '' }}</p>

    @if (filled($lastBooked['come_around'] ?? null))
        <p class="book-serial-confirmed__come">
            {{ __('Come around :time', ['time' => $lastBooked['come_around']]) }}
        </p>
        <p class="book-serial-confirmed__hint">
            {{ __('This is a guess if the sitting starts on time. The ticket updates after the queue starts.') }}
        </p>
    @elseif (filled($lastBooked['overflow_phrase'] ?? null))
        <p class="book-serial-confirmed__come">{{ $lastBooked['overflow_phrase'] }}</p>
    @endif

    <dl class="book-serial-confirmed__rows">
        @if (filled($lastBooked['date'] ?? null))
            <div class="book-serial-confirmed__row">
                <dt>{{ __('Date') }}</dt>
                <dd>{{ $lastBooked['date'] }}</dd>
            </div>
        @endif
        @if (filled($lastBooked['hours'] ?? null))
            <div class="book-serial-confirmed__row">
                <dt>{{ __('Hours') }}</dt>
                <dd>{{ $lastBooked['hours'] }}</dd>
            </div>
        @endif
        @if (filled($lastBooked['chamber'] ?? null))
            <div class="book-serial-confirmed__row">
                <dt>{{ __('Centre') }}</dt>
                <dd>{{ $lastBooked['chamber'] }}</dd>
            </div>
        @endif
        @if (filled($lastBooked['sitting'] ?? null))
            <div class="book-serial-confirmed__row">
                <dt>{{ __('Sitting') }}</dt>
                <dd>{{ $lastBooked['sitting'] }}</dd>
            </div>
        @endif
        @if (filled($lastBooked['room'] ?? null))
            <div class="book-serial-confirmed__row">
                <dt>{{ __('Room') }}</dt>
                <dd>{{ $lastBooked['room'] }}</dd>
            </div>
        @endif
        @if (filled($lastBooked['doctor'] ?? null))
            <div class="book-serial-confirmed__row">
                <dt>{{ __('Doctor') }}</dt>
                <dd>{{ $lastBooked['doctor'] }}</dd>
            </div>
        @endif
        @if (filled($lastBooked['procedure'] ?? null))
            <div class="book-serial-confirmed__row">
                <dt>{{ __('Procedure') }}</dt>
                <dd>{{ $lastBooked['procedure'] }}</dd>
            </div>
        @endif
        @if (filled($lastBooked['phone'] ?? null))
            <div class="book-serial-confirmed__row">
                <dt>{{ __('Phone') }}</dt>
                <dd>{{ $lastBooked['phone'] }}</dd>
            </div>
        @endif
    </dl>

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
