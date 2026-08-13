@php
    $ba = config('marketing.before_after');
@endphp
<section class="mk-section mk-compare" id="before-after" aria-labelledby="compare-heading">
    <div class="mk-wrap">
        <div class="mk-section-head">
            <p class="mk-kicker">The everyday difference</p>
            <h2 id="compare-heading">Same chamber.<br><em>A much better day.</em></h2>
        </div>
        <x-card-grid :count="2" class="mk-compare-grid">
            <article class="mk-compare-card mk-compare-before">
                <div class="mk-compare-top">
                    <span>Before using us</span>
                    <strong>{{ $ba['before']['value'] }}</strong>
                </div>
                <ul>
                    @foreach($ba['before']['bullets'] as $bullet)
                        <li><span>×</span>{{ $bullet }}</li>
                    @endforeach
                </ul>
            </article>
            <article class="mk-compare-card mk-compare-after">
                <div class="mk-compare-top">
                    <span>After using us</span>
                    <strong>{{ $ba['after']['value'] }}</strong>
                </div>
                <ul>
                    @foreach($ba['after']['bullets'] as $bullet)
                        <li><span>✓</span>{{ $bullet }}</li>
                    @endforeach
                </ul>
            </article>
        </x-card-grid>
    </div>
</section>
