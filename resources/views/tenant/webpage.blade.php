@php
    $tenant = tenant();
    $fontFamily = $tenant->font_family ?? 'Outfit';
    $themeColor = $tenant->theme_color ?: \App\Models\Tenant::DEFAULT_THEME_COLOR;
    $fontUrl = match($fontFamily) {
        'Inter' => 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap',
        'Roboto' => 'https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap',
        'Hind Siliguri' => 'https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&display=swap',
        default => 'https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap',
    };
    $customPages = \App\Models\WebPage::where('is_published', true)->where('slug', '!=', '/')->get();
    $banglaHomepage = $tenant->hasFeature('bangla_homepage');
    $locale = app()->getLocale();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}" class="h-full" style="color-scheme: light;">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light only">
    <meta name="theme-color" content="{{ $themeColor }}">
    <title>{{ $page->title }} | {{ $tenant->displayName() }}</title>
    <link rel="manifest" href="{{ tenant_web_url('/manifest.webmanifest') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="{{ $fontUrl }}">
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
                        sans: ['{{ $fontFamily }}', 'system-ui', 'sans-serif'],
                    },
                    maxWidth: {
                        site: '1320px',
                    },
                },
            },
        };
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="/css/theme.css">
    <link rel="stylesheet" href="{{ asset('css/card-grid.css') }}">
    <style>
        :root {
            --color-primary: {{ $themeColor }};
            --color-primary-hover: #1d4ed8;
            --font-family-base: '{{ $fontFamily }}', system-ui, sans-serif;
            color-scheme: light;
        }
        html { color-scheme: light only; }
        body { font-family: var(--font-family-base); }
        [x-cloak] { display: none !important; }
        .text-brand { color: var(--color-primary); }
        .bg-brand { background-color: var(--color-primary); }
        .bg-brand-soft { background-color: color-mix(in srgb, var(--color-primary) 12%, white); }
        .hover\:bg-brand-dark:hover { background-color: var(--color-primary-hover); }
        .ring-brand { --tw-ring-color: var(--color-primary); }
        .border-brand\/20 { border-color: color-mix(in srgb, var(--color-primary) 20%, transparent); }
    </style>
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => navigator.serviceWorker.register(@json(tenant_web_url('/sw.js'))));
        }
    </script>
</head>
<body class="min-h-full flex flex-col bg-slate-50 text-slate-900 antialiased" x-data="{ menuOpen: false }">
    <header class="fixed inset-x-0 top-0 z-50 border-b border-slate-200/80 bg-white">
        <div class="mx-auto flex h-16 max-w-[1320px] items-center justify-between gap-4 px-4 sm:h-[4.25rem] sm:px-6 lg:px-8">
            <a href="{{ tenant_web_url('/') }}" class="min-w-0 truncate text-base font-bold tracking-tight text-slate-900 sm:text-lg">
                @if($tenant->logo_url)
                    <img src="{{ $tenant->logo_url }}" alt="{{ $tenant->displayName() }}" class="h-9 w-auto">
                @else
                    {{ $tenant->displayName() }}
                @endif
            </a>

            <nav class="hidden items-center gap-6 text-sm font-medium text-slate-700 md:flex lg:gap-8" aria-label="{{ __('Main') }}">
                <a href="{{ tenant_web_url('/') }}" class="transition hover:text-brand">{{ __('Home') }}</a>
                @foreach($customPages as $customPage)
                    <a href="{{ $customPage->slug }}" class="transition hover:text-brand">{{ $customPage->title }}</a>
                @endforeach
                <a href="{{ tenant_web_url('/book') }}" class="transition hover:text-brand">{{ __('Book Appointment') }}</a>
                <a href="{{ tenant_web_url('/portal') }}" class="font-medium text-slate-500 transition hover:text-brand">{{ __('Patient Portal') }}</a>
                @if($banglaHomepage)
                    <span class="flex items-center gap-1.5 border-l border-slate-200 pl-4 text-xs font-semibold tracking-wide">
                        <a href="{{ tenant_web_url('/lang/en') }}" class="{{ $locale === 'en' ? 'text-brand' : 'text-slate-400 hover:text-slate-700' }}">EN</a>
                        <span class="text-slate-300">|</span>
                        <a href="{{ tenant_web_url('/lang/bn') }}" class="{{ $locale === 'bn' ? 'text-brand' : 'text-slate-400 hover:text-slate-700' }}">BN</a>
                    </span>
                @endif
            </nav>

            <button
                type="button"
                class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-800 md:hidden"
                @click="menuOpen = !menuOpen"
                :aria-expanded="menuOpen.toString()"
                aria-controls="site-mobile-nav"
                aria-label="{{ __('Menu') }}"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/></svg>
            </button>
        </div>

        <div id="site-mobile-nav" class="border-t border-slate-100 bg-white md:hidden" x-show="menuOpen" x-cloak>
            <div class="mx-auto flex max-w-[1320px] flex-col gap-1 px-4 py-3 text-base font-medium">
                <a href="{{ tenant_web_url('/') }}" class="rounded-lg px-3 py-3 hover:bg-slate-50" @click="menuOpen = false">{{ __('Home') }}</a>
                @foreach($customPages as $customPage)
                    <a href="{{ $customPage->slug }}" class="rounded-lg px-3 py-3 hover:bg-slate-50" @click="menuOpen = false">{{ $customPage->title }}</a>
                @endforeach
                <a href="{{ tenant_web_url('/book') }}" class="rounded-lg px-3 py-3 hover:bg-slate-50" @click="menuOpen = false">{{ __('Book Appointment') }}</a>
                <a href="{{ tenant_web_url('/portal') }}" class="rounded-lg px-3 py-3 text-slate-500 hover:bg-slate-50" @click="menuOpen = false">{{ __('Patient Portal') }}</a>
                @if($banglaHomepage)
                    <div class="flex gap-3 px-3 py-3 text-xs font-semibold">
                        <a href="{{ tenant_web_url('/lang/en') }}" class="{{ $locale === 'en' ? 'text-brand' : 'text-slate-400' }}">EN</a>
                        <a href="{{ tenant_web_url('/lang/bn') }}" class="{{ $locale === 'bn' ? 'text-brand' : 'text-slate-400' }}">BN</a>
                    </div>
                @endif
            </div>
        </div>
    </header>

    <main class="w-full flex-1 pt-16 sm:pt-[4.25rem]">
        @foreach ($page->content ?? [] as $block)
            @php $blockType = $block['type'] ?? ''; @endphp
            @if(empty($block['data']['is_hidden']) && view()->exists('tenant.sections.' . $blockType))
                @include('tenant.sections.' . $blockType, [
                    'data' => $block['data'] ?? [],
                    'doctors' => $doctors ?? [],
                    'tenant' => $tenant,
                ])
            @endif
        @endforeach
    </main>

    <footer class="mt-auto border-t border-slate-800 bg-slate-900 text-slate-400">
        <div class="mx-auto grid max-w-[1320px] gap-10 px-4 py-12 sm:px-6 md:grid-cols-3 lg:px-8 lg:py-16">
            <div>
                <h3 class="text-base font-bold text-white sm:text-lg">{{ $tenant->displayName() }}</h3>
                <p class="mt-3 max-w-sm text-sm leading-relaxed sm:text-[0.95rem]">
                    {{ $tenant->tagline ?: __('Compassionate care with online serial booking and live queue updates on your phone.') }}
                </p>
            </div>
            <div>
                <h4 class="text-sm font-bold text-white">{{ __('Quick Links') }}</h4>
                <ul class="mt-3 space-y-2 text-sm">
                    <li><a href="{{ tenant_web_url('/') }}" class="hover:text-white">{{ __('Home') }}</a></li>
                    @foreach($customPages as $customPage)
                        <li><a href="{{ $customPage->slug }}" class="hover:text-white">{{ $customPage->title }}</a></li>
                    @endforeach
                    <li><a href="{{ tenant_web_url('/book') }}" class="hover:text-white">{{ __('Book Appointment') }}</a></li>
                    <li><a href="{{ tenant_web_url('/portal') }}" class="hover:text-white">{{ __('Patient Portal') }}</a></li>
                </ul>
            </div>
            <div>
                <h4 class="text-sm font-bold text-white">{{ __('Contact') }}</h4>
                <p class="mt-3 text-sm">{{ __('Phone') }}: {{ $tenant->contact_phone ?? __('Contact the clinic') }}</p>
                <p class="mt-2 text-sm">&copy; {{ date('Y') }} {{ $tenant->displayName() }}</p>
            </div>
        </div>
    </footer>
</body>
</html>
