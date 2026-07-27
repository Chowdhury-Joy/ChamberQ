<section class="mk-section mk-band" id="why" aria-labelledby="why-heading">
    <div class="mk-wrap">
        <div class="mk-section-head mk-narrow">
            <h2 id="why-heading">Why solo doctors switch</h2>
            <p>Less scramble for you. Less waiting for them. Your name spreads.</p>
        </div>
        <ul class="mk-value-list">
            @foreach(config('marketing.value_points') as $point)
                @php
                    $exists = file_exists(public_path($point['image']));
                    $featured = !empty($point['featured']);
                @endphp
                <li class="mk-value-item {{ $featured ? 'is-main' : '' }}">
                    <div class="mk-value-thumb">
                        @if($exists)
                            <img src="{{ asset($point['image']) }}" alt="{{ $point['title'] }}" width="640" height="400">
                        @else
                            <div class="mk-placeholder">
                                <strong>{{ $point['title'] }}</strong>
                                <span>{{ basename($point['image']) }}</span>
                            </div>
                        @endif
                    </div>
                    <div class="mk-value-body">
                        <h3>{{ $point['title'] }}</h3>
                        <p>{{ $point['caption'] }}</p>
                    </div>
                </li>
            @endforeach
        </ul>
    </div>
</section>
