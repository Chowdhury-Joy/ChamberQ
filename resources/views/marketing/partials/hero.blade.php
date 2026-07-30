@php
    $heroPath = config('marketing.hero_image');
    $heroExists = $heroPath && file_exists(public_path($heroPath));
@endphp
<section class="mk-hero" aria-label="Introduction" data-mk-hero>
    <span class="mk-orbit mk-orbit-one" aria-hidden="true"></span>
    <span class="mk-orbit mk-orbit-two" aria-hidden="true"></span>
    <div class="mk-wrap">
        <div class="mk-hero-grid">
            <div class="mk-hero-copy">
                <p class="mk-eyebrow"><span></span> Made for independent doctors</p>
                <h1>Give patients their <em>time back.</em></h1>
                <p class="mk-hero-lead">
                    Your chamber can feel calm before the patient even arrives.
                    Online serials, a live queue, and fewer interruptions for everyone.
                </p>
                <div class="mk-hero-ctas">
                    <a class="mk-btn mk-btn-primary" href="{{ $generalWa }}" target="_blank" rel="noopener noreferrer">
                        See it for your chamber <span>→</span>
                    </a>
                    <a class="mk-link-quiet" href="#how">Watch the flow <span>↓</span></a>
                </div>
            </div>
            <div class="mk-hero-visual">
                <div class="mk-hero-note mk-hero-note-top"><b>12</b><span>serials booked<br>before lunch</span></div>
                <div class="mk-phone mk-phone-hero">
                    <div class="mk-phone-screen">
                        @if($heroExists)
                            <img src="{{ asset($heroPath) }}" alt="Serial ticket on a patient’s phone" width="780" height="1688">
                        @else
                            @include('marketing.partials.product-preview', ['preview' => 'ticket'])
                        @endif
                    </div>
                </div>
                <div class="mk-hero-note mk-hero-note-bottom"><span class="mk-note-check">✓</span><span>Patient knows<br>when to arrive</span></div>
            </div>
        </div>
    </div>
</section>
