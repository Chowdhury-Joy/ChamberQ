@php
    $heroPath = config('marketing.hero_image');
    $heroExists = $heroPath && file_exists(public_path($heroPath));
    $ba = config('marketing.before_after');
@endphp
<section class="mk-hero" aria-label="Introduction" data-mk-hero>
    <div class="mk-wrap">
        <div class="mk-hero-grid">
            <div class="mk-hero-copy">
                <p class="mk-hero-brand">{{ $product }}</p>
                <h1>Patients wait less. They tell others.</h1>
                <p class="mk-hero-lead">
                    Built for solo doctors. Online serials and a calm queue —
                    so happier patients become word of mouth.
                </p>
                <div class="mk-hero-ctas">
                    <a class="mk-btn mk-btn-primary" href="{{ $generalWa }}" target="_blank" rel="noopener noreferrer">
                        Chat on WhatsApp
                    </a>
                    <a class="mk-link-quiet" href="#how">See how it works</a>
                </div>
                <div class="mk-hero-metrics" aria-label="Wait time impact">
                    <div class="mk-hero-metric mk-hero-metric-before">
                        <strong>{{ $ba['before']['value'] }}</strong>
                        <span>{{ $ba['before']['label'] }}</span>
                    </div>
                    <span class="mk-hero-metrics-arrow" aria-hidden="true">→</span>
                    <div class="mk-hero-metric mk-hero-metric-after">
                        <strong>{{ $ba['after']['value'] }}</strong>
                        <span>{{ $ba['after']['label'] }}</span>
                    </div>
                </div>
            </div>
            <div class="mk-hero-visual">
                <div class="mk-phone">
                    <div class="mk-phone-screen">
                        @if($heroExists)
                            <img src="{{ asset($heroPath) }}" alt="Serial ticket on a patient’s phone" width="780" height="1688">
                        @else
                            <div class="mk-placeholder">
                                <strong>Serial ticket</strong>
                                <span>Add step-4-serial-ticket.png</span>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
