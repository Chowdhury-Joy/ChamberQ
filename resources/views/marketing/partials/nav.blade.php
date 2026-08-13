<header class="mk-nav">
    <div class="mk-wrap mk-nav-inner">
        <a class="mk-nav-brand" href="/">
            <span class="mk-logo-mark">DG</span>
            <span>{{ $product }}</span>
        </a>
        <nav class="mk-nav-links" aria-label="Primary">
            <a href="/find">{{ __('Find a doctor') }}</a>
            <a href="#how">How it works</a>
            <a href="#pricing">Pricing</a>
            @if(auth('patient')->check())
                <a href="/me">{{ __('My serials') }}</a>
            @else
                <a href="/me/login">{{ __('Patient login') }}</a>
            @endif
        </nav>
        <a class="mk-nav-find" href="/find">{{ __('Find a doctor') }}</a>
        <a class="mk-nav-cta" href="{{ $generalWa }}" target="_blank" rel="noopener noreferrer">Talk to us <span>↗</span></a>
    </div>
</header>
