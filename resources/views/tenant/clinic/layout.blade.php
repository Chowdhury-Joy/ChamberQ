@php
    $tenant = $tenant ?? tenant();
    $themeColor = $themeColor ?? ($tenant->theme_color ?: '#1B2978');
    $brand = $brand ?? $tenant->displayName();
    $locale = $locale ?? app()->getLocale();
    $banglaHomepage = $banglaHomepage ?? $tenant->hasFeature('bangla_homepage');
    $customPages = $customPages ?? \App\Models\WebPage::where('is_published', true)->where('slug', '!=', '/')->get();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}" style="color-scheme: light;">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light only">
    <meta name="theme-color" content="{{ $themeColor }}">
    <title>{{ $pageTitle }} | {{ $brand }}</title>
    <link rel="manifest" href="{{ tenant_web_url('/manifest.webmanifest') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Golos+Text:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" href="{{ $tenant->faviconHref() }}">
    <link rel="stylesheet" href="{{ public_asset('css/getwebfield-spacing.css') }}">
    <link rel="stylesheet" href="{{ public_asset('css/clinic-clireo.css') }}">
    <style>:root { --brand: {{ $themeColor }}; }</style>
    <script>document.documentElement.classList.add('has-js');</script>
    <script defer src="{{ public_asset('js/clinic-clireo.js') }}"></script>
</head>
<body>
    <header class="nav">
        <div class="nav-inner">
            <a class="logo" href="{{ tenant_web_url('/') }}" aria-label="{{ $brand }}">
                @if($tenant->logo_url)
                    <img class="logo-img" src="{{ $tenant->logo_url }}" alt="{{ $brand }}">
                @else
                    {{ $brand }}
                @endif
            </a>

            <nav class="nav-links" aria-label="{{ __('Primary') }}">
                <a class="fx-btn" href="{{ tenant_web_url('/') }}"><span class="fx-btn-track"><span>{{ __('Home') }}</span><span aria-hidden="true">{{ __('Home') }}</span></span></a>
                <a class="fx-btn" href="{{ tenant_web_url('/departments') }}"><span class="fx-btn-track"><span>{{ __('Services') }}</span><span aria-hidden="true">{{ __('Services') }}</span></span></a>
                <a class="fx-btn" href="{{ tenant_web_url('/doctors') }}"><span class="fx-btn-track"><span>{{ __('Doctors') }}</span><span aria-hidden="true">{{ __('Doctors') }}</span></span></a>
                <a class="fx-btn" href="{{ tenant_web_url('/blog') }}"><span class="fx-btn-track"><span>{{ __('Health tips') }}</span><span aria-hidden="true">{{ __('Health tips') }}</span></span></a>
                @foreach($customPages as $customPage)
                    <a class="fx-btn" href="{{ $customPage->slug }}"><span class="fx-btn-track"><span>{{ $customPage->title }}</span><span aria-hidden="true">{{ $customPage->title }}</span></span></a>
                @endforeach
            </nav>

            <a class="btn-contact" href="{{ tenant_safe_href(null, '/book') }}"><span>{{ __('Book appointment') }}</span></a>
            <button class="nav-burger" type="button" data-open-menu aria-label="{{ __('Menu') }}">☰</button>
        </div>
    </header>

    <main>
        @yield('content')
    </main>

    <footer class="footer space-section">
        <div class="layout-container footer-top space-section-y">
            <div>
                <a class="logo" href="{{ tenant_web_url('/') }}" aria-label="{{ $brand }}">
                    @if($tenant->logo_url)
                        <img class="logo-img logo-img--footer" src="{{ $tenant->logo_url }}" alt="{{ $brand }}">
                    @else
                        {{ $brand }}
                    @endif
                </a>
                <p style="margin:0;font-size:0.92rem">{{ $tenant->tagline ?: __('Compassionate care with online serial booking and live queue updates.') }}</p>
            </div>
            <div>
                <h3>{{ __('Explore') }}</h3>
                <a href="{{ tenant_web_url('/departments') }}">{{ __('Departments') }}</a>
                <a href="{{ tenant_web_url('/doctors') }}">{{ __('Doctors') }}</a>
                <a href="{{ tenant_web_url('/blog') }}">{{ __('Health tips') }}</a>
                <a href="{{ tenant_safe_href(null, '/book') }}">{{ __('Book appointment') }}</a>
            </div>
        </div>
        <div class="layout-container footer-bottom">
            <span>&copy; {{ date('Y') }} {{ $brand }}. {{ __('All rights reserved.') }}</span>
            <span>{{ __('Powered by ChamberQ') }}</span>
        </div>
    </footer>
</body>
</html>
