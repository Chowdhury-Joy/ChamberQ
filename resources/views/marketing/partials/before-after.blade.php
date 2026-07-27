@php
    $ba = config('marketing.before_after');
@endphp
<section class="mk-section mk-band" id="before-after" aria-labelledby="compare-heading">
    <div class="mk-wrap">
        <div class="mk-section-head mk-narrow">
            <h2 id="compare-heading">Before us vs after us</h2>
            <p>Same chamber. Very different day.</p>
        </div>
        <div class="mk-compare-grid">
            <article class="mk-compare-card">
                <h3>Before using us</h3>
                <p class="mk-compare-stat">{{ $ba['before']['value'] }} {{ $ba['before']['label'] }}</p>
                <ul>
                    @foreach($ba['before']['bullets'] as $bullet)
                        <li>{{ $bullet }}</li>
                    @endforeach
                </ul>
            </article>
            <article class="mk-compare-card is-after">
                <h3>After using us</h3>
                <p class="mk-compare-stat">{{ $ba['after']['value'] }} {{ $ba['after']['label'] }}</p>
                <ul>
                    @foreach($ba['after']['bullets'] as $bullet)
                        <li>{{ $bullet }}</li>
                    @endforeach
                </ul>
            </article>
        </div>
    </div>
</section>
