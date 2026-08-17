@php
    $tenant = $tenant ?? tenant();
    $brand = $brand ?? $tenant->displayName();
    $locale = $locale ?? app()->getLocale();
    $banglaHomepage = $banglaHomepage ?? $tenant->hasFeature('bangla_homepage');
    $navCta = e(__('Book appointment'));
    $clinicNavItems = clinic_nav_items();
@endphp

<header class="nav">
    <div class="nav-inner">
        <a class="logo" href="{{ tenant_web_url('/') }}" aria-label="{{ $brand }}">
            @if($logoSrc = \App\Support\SafeUrl::href($tenant->logo_url, ''))
                <img class="logo-img" src="{{ $logoSrc }}" alt="{{ $brand }}">
            @else
                {{ $brand }}
            @endif
        </a>

        <nav class="nav-links" aria-label="{{ __('Primary') }}">
            @foreach($clinicNavItems as $item)
                <a class="fx-btn" href="{{ $item['href'] }}"><span class="fx-btn-track"><span>{{ $item['label'] }}</span><span aria-hidden="true">{{ $item['label'] }}</span></span></a>
            @endforeach
            @if($banglaHomepage)
                <span class="nav-lang">
                    <a href="{{ tenant_web_url('/lang/en') }}" @class(['is-active' => $locale === 'en'])>EN</a>
                    <span aria-hidden="true">|</span>
                    <a href="{{ tenant_web_url('/lang/bn') }}" @class(['is-active' => $locale === 'bn'])>BN</a>
                </span>
            @endif
        </nav>

        <a class="btn-contact" href="{{ tenant_safe_href(null, '/book') }}"><span>{{ $navCta }}</span></a>
        <button class="nav-burger" type="button" data-open-menu aria-label="{{ __('Menu') }}">☰</button>
    </div>
</header>

<div class="drawer" data-drawer aria-hidden="true">
    <div class="drawer-backdrop" data-close-menu></div>
    <div class="drawer-panel">
        <a class="logo drawer-logo" href="{{ tenant_web_url('/') }}" data-close-menu aria-label="{{ $brand }}">
            @if($logoSrc = \App\Support\SafeUrl::href($tenant->logo_url, ''))
                <img class="logo-img logo-img--drawer" src="{{ $logoSrc }}" alt="{{ $brand }}">
            @else
                {{ $brand }}
            @endif
        </a>
        <button class="drawer-close" type="button" data-close-menu>{{ __('Close') }}</button>
        @foreach($clinicNavItems as $item)
            <a href="{{ $item['href'] }}" data-close-menu>{{ $item['label'] }}</a>
        @endforeach
        <a href="{{ tenant_safe_href(null, '/book') }}" data-close-menu>{{ __('Book appointment') }}</a>
        @if($banglaHomepage)
            <span class="nav-lang">
                <a href="{{ tenant_web_url('/lang/en') }}" @class(['is-active' => $locale === 'en'])>EN</a>
                <span aria-hidden="true">|</span>
                <a href="{{ tenant_web_url('/lang/bn') }}" @class(['is-active' => $locale === 'bn'])>BN</a>
            </span>
        @endif
    </div>
</div>
