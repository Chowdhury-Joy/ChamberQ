@php
    /*
     * Entry point into the real /book wizard. Uses the dark `.stats-band`
     * panel rather than the full-bleed `.final-cta`, so a page carrying both
     * this block and `cta_banner` does not show the same band twice.
     */
    $heading = $data['heading'] ?? __('Book your appointment online in 60 seconds');
    $subheadline = $data['subheadline'] ?? __('Choose your doctor, date and time — you get a serial ticket at the end.');
@endphp

<section class="space-section" data-reveal-section>
    <div class="layout-container">
        <div class="stats-band book-band space-card" data-reveal-block data-reveal-kind="fade">
            <h3>{{ $heading }}</h3>
            @if(filled($subheadline))
                <p class="book-band-lead">{{ $subheadline }}</p>
            @endif
            <a class="btn-accent sm" href="{{ tenant_safe_href(null, '/book') }}"><span>{{ __('Start Booking') }}</span></a>
        </div>
    </div>
</section>
