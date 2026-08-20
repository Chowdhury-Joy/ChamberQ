@php
    $tenant = tenant();
    $themeColor = $tenant->cssThemeColor();
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
    @php $seo = \App\Support\PublicSeo::tenantHome($tenant, $page); @endphp
    <title>{{ $seo['title'] }}</title>
    @include('partials.seo', ['seo' => $seo])
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
    @include('tenant.partials.clinic-header')

    <main>
        @foreach ($page->content ?? [] as $block)
            @php $blockType = $block['type'] ?? ''; @endphp
            @if(empty($block['data']['is_hidden']) && view()->exists('tenant.sections.' . $blockType))
                @include('tenant.sections.' . $blockType, [
                    'data' => $block['data'] ?? [],
                    'doctors' => $doctors ?? [],
                    'chambers' => $chambers ?? collect(),
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
                    @if($logoSrc = \App\Support\SafeUrl::href($tenant->logo_url, ''))
                        <img class="logo-img logo-img--footer" src="{{ $logoSrc }}" alt="{{ $brand }}">
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
                @forelse($departments ?? [] as $department)
                    <a href="{{ tenant_web_url('/departments/'.$department->slug) }}">{{ $department->title }}</a>
                @empty
                    <a href="{{ tenant_web_url('/departments') }}">{{ __('Services') }}</a>
                @endforelse
            </div>
            <div>
                <h3>{{ __('Pages') }}</h3>
                @foreach(clinic_nav_items() as $item)
                    <a href="{{ $item['href'] }}">{{ $item['label'] }}</a>
                @endforeach
                <a href="{{ tenant_safe_href(null, '/book') }}">{{ __('Book appointment') }}</a>
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

</body>
</html>
