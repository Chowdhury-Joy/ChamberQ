@php
    $tenant = $tenant ?? tenant();
    $themeColor = $themeColor ?? ($tenant->theme_color ?: '#30A9E5');
    $brand = $brand ?? $tenant->displayName();
    $locale = $locale ?? app()->getLocale();
    $banglaHomepage = $banglaHomepage ?? $tenant->hasFeature('bangla_homepage');
    $seo = $seo ?? \App\Support\PublicSeo::tenantPage($tenant, ($pageTitle ?? $brand).' | '.$brand);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}" class="h-full" style="color-scheme: light;">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light only">
    <meta name="theme-color" content="{{ $themeColor }}">
    <title>{{ $seo['title'] }}</title>
    @include('partials.seo', ['seo' => $seo])
    <link rel="manifest" href="{{ tenant_web_url('/manifest.webmanifest') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet">
    <link rel="icon" href="{{ $tenant->faviconHref() }}">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="/css/theme.css">
    <style>
        :root {
            --color-primary: {{ $themeColor }};
            --font-family-base: 'DM Sans', system-ui, sans-serif;
            --font-family-display: 'Instrument Serif', Georgia, serif;
            color-scheme: light;
        }
        html { color-scheme: light only; }
        body { margin: 0; font-family: var(--font-family-base); background: #fff; color: #0f172a; }
        .font-display { font-family: var(--font-family-display); }
        .solo-cta {
            display: inline-flex; align-items: center; justify-content: center;
            border-radius: 9999px; background: var(--color-primary); color: #fff;
            font-weight: 600; font-size: 0.95rem; padding: 16px 32px; text-decoration: none;
        }
        .solo-cta:hover { opacity: 0.92; color: #fff; }
    </style>
</head>
<body class="min-h-full bg-white">
    <header class="border-b border-slate-100">
        <div class="mx-auto flex max-w-[1280px] items-center justify-between gap-4 px-3 py-4 sm:px-10">
            <a href="{{ tenant_web_url('/') }}" class="font-display text-xl text-slate-900">{{ $brand }}</a>
            <nav class="flex items-center gap-4 text-sm font-medium">
                <a href="{{ tenant_web_url('/') }}" class="text-slate-700">{{ __('Home') }}</a>
                <a href="{{ tenant_safe_href(null, '/conditions') }}" class="text-slate-700">{{ __('Conditions we treat') }}</a>
                <a href="{{ tenant_safe_href(null, '/book') }}" class="solo-cta">{{ __('Book Appointment') }}</a>
            </nav>
        </div>
    </header>
    <main class="mx-auto max-w-[800px] px-3 py-10 sm:px-10 sm:py-14">
        @yield('content')
    </main>
</body>
</html>
