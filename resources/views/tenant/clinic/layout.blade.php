@php
    $tenant = $tenant ?? tenant();
    $themeColor = $themeColor ?? ($tenant->theme_color ?: '#1B2978');
    $brand = $brand ?? $tenant->displayName();
    $locale = $locale ?? app()->getLocale();
    $banglaHomepage = $banglaHomepage ?? $tenant->hasFeature('bangla_homepage');
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
    @include('tenant.partials.clinic-header')

    <main>
        @yield('content')
    </main>

    <footer class="footer space-section">
        <div class="layout-container footer-top space-section-y">
            <div>
                <a class="logo" href="{{ tenant_web_url('/') }}" aria-label="{{ $brand }}">
                    @if($logoSrc = \App\Support\SafeUrl::href($tenant->logo_url, ''))
                        <img class="logo-img logo-img--footer" src="{{ $logoSrc }}" alt="{{ $brand }}">
                    @else
                        {{ $brand }}
                    @endif
                </a>
                <p style="margin:0;font-size:0.92rem">{{ $tenant->tagline ?: __('Compassionate care with online serial booking and live queue updates.') }}</p>
            </div>
            <div>
                <h3>{{ __('Explore') }}</h3>
                @foreach(clinic_nav_items() as $item)
                    <a href="{{ $item['href'] }}">{{ $item['label'] }}</a>
                @endforeach
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
