@php($preview = $preview ?? 'ticket')

<div class="mk-ui mk-ui-{{ $preview }}" aria-hidden="true">
    @if($preview === 'chamber')
        <div class="mk-ui-top"><span class="mk-ui-avatar">MR</span><span>Dr. Mahfuz's Care</span></div>
        <div class="mk-ui-hero-copy">
            <small>CONSULTANT PHYSICIAN</small>
            <strong>Care that starts before you arrive.</strong>
            <span>Book your chamber visit online.</span>
        </div>
        <span class="mk-ui-button">Book a serial</span>
    @elseif($preview === 'session')
        <div class="mk-ui-top"><span>Choose a session</span><b>2/3</b></div>
        <div class="mk-ui-calendar"><b>Mon</b><b>Tue</b><b class="is-picked">Wed</b><b>Thu</b></div>
        <div class="mk-ui-option is-picked"><span>Evening</span><small>5:00–9:00 pm</small><em>7 seats</em></div>
        <div class="mk-ui-option"><span>Next Monday</span><small>9:00 am–1:00 pm</small><em>10 seats</em></div>
    @elseif($preview === 'confirm')
        <div class="mk-ui-top"><span>Your details</span><b>3/3</b></div>
        <label class="mk-ui-field"><small>Patient name</small><span>Rahim Ahmed</span></label>
        <label class="mk-ui-field"><small>Phone number</small><span>017••••••••</span></label>
        <span class="mk-ui-button">Confirm serial</span>
    @elseif($preview === 'queue')
        <div class="mk-ui-queue-head"><small>NOW SEEING</small><strong>07</strong></div>
        <div class="mk-ui-queue-row"><span>Up next</span><b>08 · 09 · 10</b></div>
        <div class="mk-ui-queue-note"><i></i> Queue is moving normally</div>
    @elseif($preview === 'doctor')
        <div class="mk-ui-top"><span>Today's patients</span><b>12 booked</b></div>
        @foreach([['07', 'In consultation'], ['08', 'Waiting'], ['09', 'Arriving']] as [$number, $status])
            <div class="mk-ui-patient"><b>{{ $number }}</b><span>Patient {{ $number }}</span><small>{{ $status }}</small></div>
        @endforeach
    @else
        <div class="mk-ui-ticket-brand"><span class="mk-ui-avatar">DG</span><span>Dr. Mahfuz's Care</span></div>
        <small class="mk-ui-ticket-label">YOUR SERIAL</small>
        <strong class="mk-ui-ticket-number">08</strong>
        <div class="mk-ui-ticket-status"><i></i><span>Now seeing serial 07</span></div>
        <div class="mk-ui-ticket-meta"><span>Wednesday</span><b>Evening · 5:00 pm</b></div>
        <span class="mk-ui-button mk-ui-button-soft">Follow live queue</span>
    @endif
</div>
