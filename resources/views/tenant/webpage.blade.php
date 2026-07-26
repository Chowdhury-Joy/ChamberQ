@php
    $tenant = tenant();
    $fontFamily = $tenant->font_family ?? 'Inter';
    $themeColor = $tenant->theme_color ?? '#0ea5e9';
    $fontUrl = match($fontFamily) {
        'Outfit' => 'https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap',
        'Roboto' => 'https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap',
        'Hind Siliguri' => 'https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&display=swap',
        default => 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap',
    };
    $customPages = \App\Models\WebPage::where('is_published', true)->where('slug', '!=', '/')->get();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="{{ $themeColor }}">
    <title>{{ $page->title }} | {{ $tenant->displayName() }}</title>
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="{{ $fontUrl }}">
    @if($tenant->favicon_url)
    <link rel="icon" href="{{ $tenant->favicon_url }}">
    @endif
    <link rel="stylesheet" href="/css/theme.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        :root {
            --color-primary: {{ $themeColor }};
            --font-family-base: '{{ $fontFamily }}', system-ui, -apple-system, sans-serif;
        }
        body { font-family: var(--font-family-base); }
    </style>
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw.js');
            });
        }
    </script>
</head>
<body class="bg-slate-50 text-slate-900 min-h-screen flex flex-col pt-20">
    <nav class="navbar fixed top-0 left-0 right-0 z-50 bg-white/90 backdrop-blur-md border-b border-slate-200 py-3">
        <div class="max-w-[1320px] w-full mx-auto px-4 md:px-6 xl:px-8 flex items-center justify-between">
            <a href="/" class="navbar-brand text-lg font-bold text-sky-600 flex items-center gap-2">
                @if($tenant->logo_url)
                    <img src="{{ $tenant->logo_url }}" alt="{{ $tenant->displayName() }}" class="h-9 w-auto">
                @else
                    <span>🏥 {{ $tenant->displayName() }}</span>
                @endif
            </a>
            <div class="navbar-nav flex items-center gap-4 text-sm font-medium text-black">
                <a href="/" class="hover:text-sky-600 transition-colors">{{ __('Home') }}</a>
                @foreach($customPages as $customPage)
                    <a href="{{ $customPage->slug }}" class="hover:text-sky-600 transition-colors">{{ $customPage->title }}</a>
                @endforeach
                <a href="/book" class="hover:text-sky-600 transition-colors">{{ __('Book Appointment') }}</a>
                
                <div class="flex items-center gap-1 border-l border-slate-200 pl-3">
                    <a href="/lang/en" class="text-xs text-slate-500 hover:text-black">EN</a>
                    <span class="text-slate-300">|</span>
                    <a href="/lang/bn" class="text-xs text-slate-500 hover:text-black">BN</a>
                </div>
                
                <a href="/portal" class="btn btn-primary ml-2 shadow-sm">{{ __('Patient Portal') }}</a>
            </div>
        </div>
    </nav>

    <main class="flex-grow w-full">
        @foreach ($page->content ?? [] as $block)
            @php $blockType = $block['type'] ?? ''; @endphp
            @if(empty($block['data']['is_hidden']) && view()->exists('tenant.sections.' . $blockType))
                @include('tenant.sections.' . $blockType, ['data' => $block['data'] ?? [], 'doctors' => $doctors ?? []])
            @endif
        @endforeach
    </main>

    <footer class="w-full bg-slate-900 text-slate-400 py-12 border-t border-slate-800 text-sm mt-12">
        <div class="max-w-[1320px] mx-auto px-4 md:px-6 xl:px-8 grid grid-cols-1 md:grid-cols-3 gap-8">
            <div>
                <h3 class="text-white font-bold text-base mb-3">{{ $tenant->displayName() }}</h3>
                <p class="text-xs text-slate-400 leading-relaxed max-w-sm">Providing compassionate, high-quality medical care tailored for patients and families.</p>
            </div>
            <div>
                <h4 class="text-white font-bold text-sm mb-3">Quick Links</h4>
                <ul class="space-y-2 text-xs">
                    <li><a href="/" class="hover:text-white transition-colors">Home</a></li>
                    @foreach($customPages as $customPage)
                        <li><a href="{{ $customPage->slug }}" class="hover:text-white transition-colors">{{ $customPage->title }}</a></li>
                    @endforeach
                    <li><a href="/book" class="hover:text-white transition-colors">Book Appointment</a></li>
                    <li><a href="/portal" class="hover:text-white transition-colors">Patient Portal</a></li>
                </ul>
            </div>
            <div>
                <h4 class="text-white font-bold text-sm mb-3">Contact Information</h4>
                <p class="text-xs text-slate-400">Phone: {{ $tenant->contact_phone ?? 'Contact Clinic Admin' }}</p>
                <p class="text-xs text-slate-400 mt-1">&copy; {{ date('Y') }} {{ $tenant->displayName() }}. All rights reserved.</p>
            </div>
        </div>
    </footer>
</body>
</html>
