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
                    <h1>Give patients their <em>time back.</em></h1>
                    <p class="mk-hero-lead">
                        Your chamber can feel calm before the patient even arrives.
                        Online serials, a live queue, and fewer interruptions for everyone.
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
                        <img src="{{ asset($heroPath) }}" alt="Serial ticket on a patient’s phone" width="780" height="780">
                    @else
                        @include('marketing.partials.product-preview', ['preview' => 'ticket'])
                    @endif
                </div>
            </div>
        </div>
    </div>
</section>
