<section class="mk-section" id="how" aria-labelledby="how-heading">
    <div class="mk-wrap">
        <div class="mk-section-head mk-narrow">
            <h2 id="how-heading">How it works</h2>
            <p>From the patient’s phone to your day list — start to end.</p>
        </div>
        <div class="mk-steps-grid">
            @foreach(config('marketing.steps') as $index => $step)
                @php
                    $exists = file_exists(public_path($step['image']));
                @endphp
                <article class="mk-step-card">
                    <div class="mk-step-thumb">
                        @if($exists)
                            <img src="{{ asset($step['image']) }}" alt="{{ $step['title'] }}" width="800" height="600">
                        @else
                            <div class="mk-placeholder">
                                <strong>{{ $step['title'] }}</strong>
                                <span>{{ basename($step['image']) }}</span>
                            </div>
                        @endif
                    </div>
                    <div class="mk-step-body">
                        <span class="mk-step-num">Step {{ $index + 1 }}</span>
                        <h3>{{ $step['title'] }}</h3>
                        <p>{{ $step['caption'] }}</p>
                    </div>
                </article>
            @endforeach
        </div>
    </div>
</section>
