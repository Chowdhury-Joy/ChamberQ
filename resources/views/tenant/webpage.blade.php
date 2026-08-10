@php
    $tenant = tenant();
    $themeColor = $tenant->theme_color ?: '#1B2978';
    $customPages = \App\Models\WebPage::where('is_published', true)->where('slug', '!=', '/')->get();
    $banglaHomepage = $tenant->hasFeature('bangla_homepage');
    $locale = app()->getLocale();
    $brand = $tenant->displayName();
@endphp
<!DOCTYPE html>
{{-- Converted from public/previews/clireo-homepage.html. --}}
<html lang="{{ str_replace('_', '-', $locale) }}" style="color-scheme: light;">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light only">
    <meta name="theme-color" content="{{ $themeColor }}">
    <title>{{ $page->title }} | {{ $brand }}</title>
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
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => navigator.serviceWorker.register(@json(tenant_web_url('/sw.js'))));
        }
    </script>
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
                <a class="fx-btn" href="#treatments"><span class="fx-btn-track"><span>{{ __('Services') }}</span><span aria-hidden="true">{{ __('Services') }}</span></span></a>
                <a class="fx-btn" href="#reviews"><span class="fx-btn-track"><span>{{ __('Reviews') }}</span><span aria-hidden="true">{{ __('Reviews') }}</span></span></a>
                <a class="fx-btn" href="#about"><span class="fx-btn-track"><span>{{ __('About') }}</span><span aria-hidden="true">{{ __('About') }}</span></span></a>
                <a class="fx-btn" href="#blog"><span class="fx-btn-track"><span>{{ __('Health tips') }}</span><span aria-hidden="true">{{ __('Health tips') }}</span></span></a>
                @foreach($customPages as $customPage)
                    <a class="fx-btn" href="{{ $customPage->slug }}"><span class="fx-btn-track"><span>{{ $customPage->title }}</span><span aria-hidden="true">{{ $customPage->title }}</span></span></a>
                @endforeach
                @if($banglaHomepage)
                    <span class="nav-lang">
                        <a href="{{ tenant_web_url('/lang/en') }}" @class(['is-active' => $locale === 'en'])>EN</a>
                        <span aria-hidden="true">|</span>
                        <a href="{{ tenant_web_url('/lang/bn') }}" @class(['is-active' => $locale === 'bn'])>BN</a>
                    </span>
                @endif
            </nav>

            @php $navCta = e(__('Book appointment')); @endphp
            <a class="btn-contact" href="{{ tenant_safe_href(null, '/book') }}"><span>{{ $navCta }}</span></a>
            <button class="nav-burger" type="button" data-open-menu aria-label="{{ __('Menu') }}">☰</button>
        </div>
    </header>

    <main>
        @foreach ($page->content ?? [] as $block)
            @php $blockType = $block['type'] ?? ''; @endphp
            @if(empty($block['data']['is_hidden']) && view()->exists('tenant.sections.' . $blockType))
                @include('tenant.sections.' . $blockType, [
                    'data' => $block['data'] ?? [],
                    'doctors' => $doctors ?? [],
                    'sessions' => $sessions ?? [],
                    'bookingAvailable' => $bookingAvailable ?? false,
                    'departments' => $departments ?? collect(),
                    'blogPosts' => $blogPosts ?? collect(),
                    'websiteDoctors' => $websiteDoctors ?? collect(),
                    'tenant' => $tenant,
                ])
            @endif
        @endforeach
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
                @if($tenant->contact_phone)
                    <p style="margin:0.75rem 0 0;font-size:0.8rem;opacity:0.65">{{ $tenant->contact_phone }}</p>
                @endif
                <form class="newsletter" onsubmit="return false;">
                    <input type="email" placeholder="{{ __('Your email for health tips') }}" required>
                    <button type="button" aria-label="{{ __('Subscribe') }}">→</button>
                </form>
            </div>
            <div>
                <h3>{{ __('Services') }}</h3>
                <a href="#treatments">{{ __('Stroke Rehabilitation') }}</a>
                <a href="#treatments">{{ __('Pain & Paralysis') }}</a>
                <a href="#treatments">{{ __('Sports Injury Rehab') }}</a>
                <a href="#treatments">{{ __('Neurological Rehab') }}</a>
                <a href="#treatments">{{ __('Orthopedic Physiotherapy') }}</a>
            </div>
            <div>
                <h3>{{ __('Pages') }}</h3>
                <a href="{{ tenant_web_url('/') }}">{{ __('Home') }}</a>
                <a href="#about">{{ __('About') }}</a>
                <a href="#treatments">{{ __('Services') }}</a>
                <a href="{{ tenant_safe_href(null, '/book') }}">{{ __('Book appointment') }}</a>
                <a href="#blog">{{ __('Health tips') }}</a>
                @foreach($customPages as $customPage)
                    <a href="{{ $customPage->slug }}">{{ $customPage->title }}</a>
                @endforeach
            </div>
            <div>
                <h3>{{ __('Socials') }}</h3>
                @if($tenant->whatsapp_number)
                    <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $tenant->whatsapp_number) }}" target="_blank" rel="noopener noreferrer">{{ __('WhatsApp') }}</a>
                @endif
                @if($tenant->contact_phone)
                    <a href="tel:{{ preg_replace('/[^0-9+]/', '', $tenant->contact_phone) }}">{{ __('Call') }} {{ $tenant->contact_phone }}</a>
                @endif
                <a href="{{ tenant_web_url('/portal') }}">{{ __('Patient’s Portal') }}</a>
            </div>
        </div>
        <div class="layout-container footer-bottom">
            <span>&copy; {{ date('Y') }} {{ $brand }}. {{ __('All rights reserved.') }}</span>
            <span>{{ __('Powered by ChamberQ') }}</span>
        </div>
    </footer>

    <div class="drawer" data-drawer aria-hidden="true">
        <div class="drawer-backdrop" data-close-menu></div>
        <div class="drawer-panel">
            <a class="logo drawer-logo" href="{{ tenant_web_url('/') }}" data-close-menu aria-label="{{ $brand }}">
                @if($tenant->logo_url)
                    <img class="logo-img logo-img--drawer" src="{{ $tenant->logo_url }}" alt="{{ $brand }}">
                @else
                    {{ $brand }}
                @endif
            </a>
            <button class="drawer-close" type="button" data-close-menu>{{ __('Close') }}</button>
            <a href="{{ tenant_web_url('/') }}" data-close-menu>{{ __('Home') }}</a>
            <a href="#treatments" data-close-menu>{{ __('Services') }}</a>
            <a href="#reviews" data-close-menu>{{ __('Reviews') }}</a>
            <a href="#about" data-close-menu>{{ __('About') }}</a>
            <a href="#blog" data-close-menu>{{ __('Health tips') }}</a>
            @foreach($customPages as $customPage)
                <a href="{{ $customPage->slug }}" data-close-menu>{{ $customPage->title }}</a>
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
</body>
</html>
