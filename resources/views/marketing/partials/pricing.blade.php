<section class="mk-section" id="pricing" aria-labelledby="pricing-heading">
    <div class="mk-wrap">
        <div class="mk-section-head mk-narrow">
            <h2 id="pricing-heading">Simple pricing</h2>
            <p>Clear setup. Clear monthly.</p>
        </div>

        <div class="mk-pricing-grid">
            <article class="mk-plan mk-plan-featured">
                <span class="mk-plan-badge">For solo doctors</span>
                <h3>{{ $solo['name'] }}</h3>
                <p class="mk-plan-tag">{{ $solo['tagline'] }}</p>
                <p class="mk-plan-price">
                    <strong>{{ $taka($solo['setup']) }} setup</strong>
                    <span>then {{ $taka($solo['monthly']) }} / month</span>
                </p>
                <ul class="mk-plan-list">
                    @foreach($solo['features'] as $feature)
                        <li>{{ $feature }}</li>
                    @endforeach
                </ul>
                <a class="mk-btn mk-btn-primary" href="{{ $soloWa }}" target="_blank" rel="noopener noreferrer">
                    WhatsApp about Solo
                </a>
            </article>

            <article class="mk-plan">
                <h3>{{ $clinic['name'] }}</h3>
                <p class="mk-plan-tag">{{ $clinic['tagline'] }}</p>
                <p class="mk-plan-price">
                    <strong>{{ $taka($clinic['setup']) }} setup</strong>
                    <span>then {{ $taka($clinic['monthly']) }} / month</span>
                </p>
                <ul class="mk-plan-list">
                    @foreach($clinic['features'] as $feature)
                        <li>{{ $feature }}</li>
                    @endforeach
                </ul>
                <a class="mk-btn mk-btn-primary" href="{{ $clinicWa }}" target="_blank" rel="noopener noreferrer">
                    WhatsApp about Clinic
                </a>
            </article>
        </div>
    </div>
</section>
