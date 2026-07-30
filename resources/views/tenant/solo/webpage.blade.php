@php
    $tenant = tenant();
    $themeColor = $tenant->theme_color ?: '#30A9E5';
    $customPages = \App\Models\WebPage::where('is_published', true)->where('slug', '!=', '/')->get();
    $banglaHomepage = $tenant->hasFeature('bangla_homepage');
    $locale = app()->getLocale();
    $brand = $tenant->displayName();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}" class="h-full" style="color-scheme: light;">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light only">
    <meta name="theme-color" content="{{ $themeColor }}">
    <title>{{ $page->title }} | {{ $brand }}</title>
    <link rel="manifest" href="{{ tenant_web_url('/manifest.webmanifest') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet">
    @if($tenant->favicon_url)
    <link rel="icon" href="{{ $tenant->favicon_url }}">
    @endif
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: {
                            DEFAULT: '{{ $themeColor }}',
                            soft: '{{ $themeColor }}1a',
                        },
                    },
                    fontFamily: {
                        display: ['Instrument Serif', 'Georgia', 'serif'],
                        sans: ['DM Sans', 'system-ui', 'sans-serif'],
                    },
                    maxWidth: {
                        site: '1280px',
                    },
                },
            },
        };
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="/css/theme.css">
    <style>
        :root {
            --color-primary: {{ $themeColor }};
            --color-primary-hover: #1f8fc4;
            --font-family-base: 'DM Sans', system-ui, sans-serif;
            --font-family-display: 'Instrument Serif', Georgia, serif;
            color-scheme: light;
        }
        html { color-scheme: light only; }
        body { font-family: var(--font-family-base); }
        .font-display { font-family: var(--font-family-display); }
        [x-cloak] { display: none !important; }
        .text-brand { color: var(--color-primary); }
        .bg-brand { background-color: var(--color-primary); }
        .border-brand { border-color: var(--color-primary); }
        .solo-cta {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            border-radius: 9999px;
            background: var(--color-primary);
            color: #fff;
            font-weight: 600;
            font-size: 0.95rem;
            padding: 16px 32px;
            transition: opacity 0.15s ease, transform 0.15s ease;
        }
        .solo-cta:hover { opacity: 0.92; }
        .solo-cta-outline {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 9999px;
            border: 1.5px solid color-mix(in srgb, var(--color-primary) 55%, white);
            color: var(--color-primary);
            background: color-mix(in srgb, var(--color-primary) 6%, white);
            font-weight: 600;
            font-size: 0.95rem;
            padding: 8px 32px;
            transition: background 0.15s ease;
        }
        .solo-cta-outline:hover { background: color-mix(in srgb, var(--color-primary) 12%, white); }
        @keyframes soloFadeUp {
            from { opacity: 0; transform: translateY(16px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .solo-fade-up { animation: soloFadeUp 0.65s ease-out both; }
        .solo-fade-up-delay { animation: soloFadeUp 0.75s ease-out 0.12s both; }
        .solo-section {
            padding-top: 40px;
            padding-bottom: 40px;
        }
        @media (min-width: 640px) {
            .solo-section {
                padding-top: 56px;
                padding-bottom: 56px;
            }
        }
        @media (min-width: 1024px) {
            .solo-section {
                padding-top: 96px;
                padding-bottom: 96px;
            }
        }
        .solo-section-hero {
            padding-top: 32px;
            padding-bottom: 32px;
        }
    </style>
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => navigator.serviceWorker.register(@json(tenant_web_url('/sw.js'))));
        }
    </script>
</head>
<body class="min-h-full flex flex-col bg-white text-slate-900 antialiased">
    <header class="sticky top-0 z-50 border-b border-slate-100 bg-white/95 backdrop-blur">
        <div class="mx-auto flex h-[68px] max-w-[1280px] items-center justify-between gap-4 px-3 sm:h-[95px] sm:px-10">
            <a href="{{ tenant_web_url('/') }}" class="min-w-0 truncate font-display text-xl tracking-tight text-slate-900 sm:text-[1.65rem]">
                @if($tenant->logo_url)
                    <img src="{{ $tenant->logo_url }}" alt="{{ $brand }}" class="h-9 w-auto sm:h-11">
                @else
                    {{ $brand }}
                @endif
            </a>

            <nav class="hidden items-center gap-8 text-base font-medium text-slate-800 md:flex" aria-label="{{ __('Main') }}">
                <a href="{{ tenant_web_url('/') }}" class="transition hover:text-brand">{{ __('Home') }}</a>
                <a href="#services" class="transition hover:text-brand">{{ __('Services') }}</a>
                <a href="#about" class="transition hover:text-brand">{{ __('About') }}</a>
                @foreach($customPages as $customPage)
                    <a href="{{ $customPage->slug }}" class="transition hover:text-brand">{{ $customPage->title }}</a>
                @endforeach
                <a href="{{ tenant_web_url('/portal') }}" class="solo-cta-outline">{{ __('Patient’s Portal') }}</a>
                @if($banglaHomepage)
                    <span class="flex items-center gap-1.5 border-l border-slate-200 pl-4 text-xs font-semibold tracking-wide">
                        <a href="{{ tenant_web_url('/lang/en') }}" class="{{ $locale === 'en' ? 'text-brand' : 'text-slate-400 hover:text-slate-700' }}">EN</a>
                        <span class="text-slate-300">|</span>
                        <a href="{{ tenant_web_url('/lang/bn') }}" class="{{ $locale === 'bn' ? 'text-brand' : 'text-slate-400 hover:text-slate-700' }}">BN</a>
                    </span>
                @endif
            </nav>

            <a href="{{ tenant_web_url('/portal') }}" class="solo-cta-outline text-sm md:hidden">{{ __('Patient’s Portal') }}</a>
        </div>
    </header>

    <main class="w-full flex-1">
        @foreach ($page->content ?? [] as $block)
            @php
                $blockType = $block['type'] ?? '';
                $soloView = 'tenant.solo.sections.' . $blockType;
                $sharedView = 'tenant.sections.' . $blockType;
                $viewName = view()->exists($soloView) ? $soloView : (view()->exists($sharedView) ? $sharedView : null);
            @endphp
            @if(empty($block['data']['is_hidden']) && $viewName)
                @include($viewName, [
                    'data' => $block['data'] ?? [],
                    'doctors' => $doctors ?? [],
                    'tenant' => $tenant,
                ])
            @endif
        @endforeach
    </main>

    <footer class="mt-auto border-t border-slate-100 bg-white text-slate-600">
        <div class="mx-auto flex max-w-[1280px] flex-col gap-6 px-3 py-10 sm:px-10 sm:py-14 md:flex-row md:items-start md:justify-between">
            <div>
                <p class="font-display text-xl text-slate-900">{{ $brand }}</p>
                <p class="mt-2 max-w-md text-sm leading-relaxed">
                    {{ $tenant->tagline ?: __('Consultant physician care with online serial booking.') }}
                </p>
            </div>
            <div class="text-sm">
                <p>{{ __('Phone') }}: {{ $tenant->contact_phone ?? __('Contact the chamber') }}</p>
                <p class="mt-2">&copy; {{ date('Y') }}, {{ $brand }}</p>
            </div>
        </div>
    </footer>
</body>
</html>
