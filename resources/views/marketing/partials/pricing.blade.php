<section class="mk-section mk-pricing" id="pricing" aria-labelledby="pricing-heading">
    <div class="mk-wrap">
        <div class="mk-section-head mk-section-head-split">
            <div>
                <p class="mk-kicker">No mystery pricing</p>
                <h2 id="pricing-heading">Start with what you need.<br><em>Add more when ready.</em></h2>
            </div>
            <p>We set everything up with you. No technical team, long contract, or payment gateway needed.</p>
        </div>

        <x-card-grid :count="2">
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
                    Choose Maestro <span>→</span>
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
        </x-card-grid>

        <div class="mk-modules">
            <h3 class="mk-modules-title">Or pick modules (one doctor)</h3>
            <div class="mk-modules-table-wrap">
                <table class="mk-modules-table">
                    <thead>
                        <tr>
                            <th scope="col">What you want</th>
                            <th scope="col">Setup</th>
                            <th scope="col">Monthly</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Website + booking</td>
                            <td>{{ $taka((int) $frontDoor['setup']) }}</td>
                            <td>{{ $taka((int) $frontDoor['monthly']) }}</td>
                        </tr>
                        <tr>
                            <td>+ Prescription</td>
                            <td>+{{ $taka((int) $prescription['setup']) }}</td>
                            <td>+{{ $taka((int) $prescription['monthly']) }}</td>
                        </tr>
                        <tr>
                            <td>+ Live queue</td>
                            <td>+{{ $taka((int) $liveQueue['setup']) }}</td>
                            <td>+{{ $taka((int) $liveQueue['monthly']) }}</td>
                        </tr>
                        <tr class="mk-modules-bundle">
                            <td>All three = Maestro</td>
                            <td>{{ $taka((int) $bundle['setup']) }}</td>
                            <td>{{ $taka((int) $bundle['monthly']) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="mk-offer">
                <span class="mk-offer-label">Launch offer</span>
                <p class="mk-offer-text">Get your website by <strong>31 August</strong> and <strong>Prescription is free for life</strong> (৳2,500 setup + ৳250/month waived).</p>
            </div>
            <div class="mk-offer">
                <span class="mk-offer-label">Prepaid-year offer</span>
                <p class="mk-offer-text">Confirm one year of payment before <strong>30 September</strong> and your one-time setup is <strong>50% off</strong> (Maestro setup ৳15,000 → ৳7,500).</p>
            </div>
            <p class="mk-modules-note">SMS confirmations optional (prepaid credits). Walk-ins included with website.</p>
            <a class="mk-btn mk-btn-secondary mk-modules-cta" href="{{ $modulesWa }}" target="_blank" rel="noopener noreferrer">
                Tell us which pieces you need <span>→</span>
            </a>
        </div>
    </div>
</section>
