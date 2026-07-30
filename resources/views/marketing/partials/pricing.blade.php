<section class="mk-section mk-pricing" id="pricing" aria-labelledby="pricing-heading">
    <div class="mk-wrap">
        <div class="mk-section-head mk-section-head-split">
            <div>
                <p class="mk-kicker">No mystery pricing</p>
                <h2 id="pricing-heading">Start small.<br><em>Feel the difference.</em></h2>
            </div>
            <p>We set everything up with you. No technical team, long contract, or payment gateway needed.</p>
        </div>

        <div class="mk-pricing-grid">
            <article class="mk-plan mk-plan-featured">
                <div class="mk-plan-head">
                    <span class="mk-plan-badge">Most popular</span>
                    <h3>{{ $solo['name'] }}</h3>
                    <p class="mk-plan-tag">{{ $solo['tagline'] }}</p>
                </div>
                <p class="mk-plan-price">
                    <strong>{{ $taka($solo['monthly']) }}<small>/month</small></strong>
                    <span>One-time setup {{ $taka($solo['setup']) }}</span>
                </p>
                <ul class="mk-plan-list">
                    @foreach($solo['features'] as $feature)
                        <li>{{ $feature }}</li>
                    @endforeach
                </ul>
                <a class="mk-btn mk-btn-primary" href="{{ $soloWa }}" target="_blank" rel="noopener noreferrer">
                    Choose Solo <span>→</span>
                </a>
            </article>

            <article class="mk-plan">
                <div class="mk-plan-head">
                    <span class="mk-plan-badge mk-plan-badge-muted">For growing teams</span>
                    <h3>{{ $clinic['name'] }}</h3>
                    <p class="mk-plan-tag">{{ $clinic['tagline'] }}</p>
                </div>
                <p class="mk-plan-price">
                    <strong>{{ $taka($clinic['monthly']) }}<small>/month</small></strong>
                    <span>One-time setup {{ $taka($clinic['setup']) }}</span>
                </p>
                <ul class="mk-plan-list">
                    @foreach($clinic['features'] as $feature)
                        <li>{{ $feature }}</li>
                    @endforeach
                </ul>
                <a class="mk-btn mk-btn-secondary" href="{{ $clinicWa }}" target="_blank" rel="noopener noreferrer">
                    Choose Clinic <span>→</span>
                </a>
            </article>
        </div>
    </div>
</section>
