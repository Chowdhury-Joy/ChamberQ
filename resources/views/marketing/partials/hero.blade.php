@php
    $heroPath = config('marketing.hero_image');
    $heroExists = $heroPath && file_exists(public_path($heroPath));
@endphp
<section class="mk-hero" aria-label="Introduction" data-mk-hero>
    <div class="mk-wrap">
        <div class="mk-hero-grid">
            <div class="mk-hero-copy mk-fade-up">
                <div>
                    <p class="mk-eyebrow"><span></span> Made for independent doctors</p>
                    <h1>Keep your chamber <em>in order.</em></h1>
                    <p class="mk-hero-lead">
                        Clearer serials, a waiting-room screen that matches your sittings, and a portfolio site under your name.
                        Fewer phone interruptions while you are in consult.
                    </p>
                </div>
                <div class="mk-hero-ctas mk-fade-up-delay">
                    <a class="mk-btn mk-btn-primary" href="{{ $generalWa }}" target="_blank" rel="noopener noreferrer">
                        See it for your chamber <span>→</span>
                    </a>
                    <a class="mk-link-quiet" href="#how">Watch the flow <span>↓</span></a>
                </div>
            </div>
            <div class="mk-hero-visual mk-fade-up-delay">
                <div class="mk-hero-frame">
                    @if($heroExists)
                        <img src="{{ asset($heroPath) }}" alt="ChamberQ serial ticket and day list" width="780" height="780">
                    @else
                        @include('marketing.partials.product-preview', ['preview' => 'ticket'])
                    @endif
                </div>
            </div>
        </div>
    </div>
</section>
