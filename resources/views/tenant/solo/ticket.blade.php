@php
    $tenant = tenant();
    $brand = $tenant->displayName();
    $themeColor = $tenant->theme_color ?: '#30A9E5';
    $locale = app()->getLocale();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}" class="h-full" style="color-scheme: light;">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light only">
    <meta name="theme-color" content="{{ $themeColor }}">
    <meta name="robots" content="noindex">
    <title>{{ __('Your Appointment') }} | {{ $brand }}</title>
    <link rel="manifest" href="{{ tenant_web_url('/manifest.webmanifest') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet">
    @if($tenant->favicon_url)
    <link rel="icon" href="{{ $tenant->favicon_url }}">
    @endif
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="/css/theme.css">
    <style>
        :root {
            --color-primary: {{ $themeColor }};
            --color-primary-hover: #1f8fc4;
            --font-family-base: 'DM Sans', system-ui, sans-serif;
            --font-family-display: 'Instrument Serif', Georgia, serif;
            --bg-base: #ffffff;
            --bg-surface: #ffffff;
            --radius-md: 12px;
            color-scheme: light;
        }
        html { color-scheme: light only; }
        body {
            margin: 0;
            font-family: var(--font-family-base);
            background: #ffffff;
            color: #0f172a;
            -webkit-font-smoothing: antialiased;
            min-height: 100%;
        }
        .font-display { font-family: var(--font-family-display); }
        .text-brand { color: var(--color-primary); }
        .text-muted { color: #64748b; }
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
            text-decoration: none;
            font-family: inherit;
        }
        .solo-cta-outline:hover { background: color-mix(in srgb, var(--color-primary) 12%, white); }
        .locale-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .locale-chip a { color: #94a3b8; text-decoration: none; }
        .locale-chip a.is-active, .locale-chip a:hover { color: var(--color-primary); }
        .locale-chip span { color: #cbd5e1; }

        .ticket {
            max-width: 720px;
            margin: 0 auto;
            padding: 1.5rem 0.75rem 3rem;
        }
        @media (min-width: 640px) {
            .ticket { padding: 2rem 2.5rem 4rem; }
        }
        .ticket-card {
            background: #ffffff;
            padding: 1.75rem 1.25rem;
            border-radius: 1rem;
            border: 1px solid #E0E0E0;
            box-shadow: 0 1px 2px 0 rgba(27, 27, 27, 0.03), 0 0 0 1px rgba(27, 27, 27, 0.03);
            text-align: center;
        }
        @media (min-width: 640px) {
            .ticket-card { padding: 2.5rem; }
        }
        .ticket-brand {
            font-family: var(--font-family-display);
            font-size: 1.75rem;
            color: #0f172a;
            margin: 0 0 1rem;
        }
        .serial {
            font-family: var(--font-family-display);
            font-size: 3.5rem;
            font-weight: 400;
            color: var(--color-primary);
            line-height: 1;
            margin: .5rem 0 1.5rem;
        }
        .now-serving {
            font-family: var(--font-family-display);
            font-size: 2rem;
            font-weight: 400;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            padding: .65rem 0;
            border-top: 1px solid #E6E6E6;
            text-align: left;
        }
        .share-actions { display: flex; flex-wrap: wrap; gap: .75rem; margin-top: 1rem; justify-content: stretch; }
        .share-actions .btn { flex: 1 1 140px; }
        .link-box { display: flex; gap: .5rem; margin-top: 1rem; }
        .link-box input { flex: 1; min-width: 0; }
        .eta-box {
            background: #FAFAFA;
            border: 1px solid #E0E0E0;
            border-radius: 1rem;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
        .handoff {
            margin-top: 1.5rem;
            padding: 1rem 1.15rem;
            border-radius: 1rem;
            background: color-mix(in srgb, var(--color-primary) 8%, white);
            border: 1px solid color-mix(in srgb, var(--color-primary) 25%, white);
            color: #0f172a;
            text-align: left;
            font-size: 0.95rem;
            line-height: 1.45;
        }
        .prep {
            margin-top: 1.5rem;
            padding: 1rem 1.25rem;
            text-align: left;
            border-radius: 1rem;
            background: #fffbeb;
            border: 1px solid #f59e0b;
            color: #713f12;
        }
        .prep h2 { font-family: var(--font-family-display); font-size: 1.25rem; font-weight: 400; margin-bottom: .5rem; }
        .prep ul { margin: 0; padding-left: 1.1rem; }
        .prep li { margin: .35rem 0; }
        .prep-note { margin-top: .75rem; font-size: .9rem; font-weight: 600; }
        .sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0; }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font-weight: 600;
            font-size: 0.95rem;
            font-family: inherit;
            cursor: pointer;
            text-decoration: none;
            border: none;
        }
        .btn-primary {
            border-radius: 9999px;
            background: var(--color-primary);
            color: #fff !important;
            padding: 16px 32px;
            transition: opacity 0.15s ease;
        }
        .btn-primary:hover { opacity: 0.92; }
        .btn-back {
            border-radius: 9999px;
            border: 1.5px solid #E0E0E0;
            background: #FAFAFA;
            color: #475569;
            padding: 16px 32px;
            transition: background 0.15s ease;
        }
        .btn-back:hover { background: #F2F2F2; }
        .form-control {
            width: 100%;
            padding: 0.85rem 1rem;
            border-radius: 0.75rem;
            border: 1px solid #E0E0E0;
            background: #ffffff;
            color: #0f172a;
            font-family: inherit;
            font-size: 1rem;
            box-sizing: border-box;
        }
        .form-control:focus {
            outline: none;
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px color-mix(in srgb, var(--color-primary) 18%, transparent);
        }
    </style>
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

            <nav class="hidden items-center gap-6 text-base font-medium text-slate-800 md:flex" aria-label="{{ __('Main') }}">
                <div class="locale-chip" aria-label="{{ __('Language') }}">
                    <a href="{{ tenant_web_url('/lang/en') }}" class="{{ $locale === 'en' ? 'is-active' : '' }}">EN</a>
                    <span aria-hidden="true">|</span>
                    <a href="{{ tenant_web_url('/lang/bn') }}" class="{{ $locale === 'bn' ? 'is-active' : '' }}">BN</a>
                </div>
                <a href="{{ tenant_web_url('/') }}" class="transition hover:text-brand">{{ __('Home') }}</a>
                <a href="{{ tenant_web_url('/portal') }}" class="solo-cta-outline">{{ __('Patient’s Portal') }}</a>
            </nav>

            <div class="flex items-center gap-2 md:hidden">
                <div class="locale-chip" aria-label="{{ __('Language') }}">
                    <a href="{{ tenant_web_url('/lang/en') }}" class="{{ $locale === 'en' ? 'is-active' : '' }}">EN</a>
                    <span aria-hidden="true">|</span>
                    <a href="{{ tenant_web_url('/lang/bn') }}" class="{{ $locale === 'bn' ? 'is-active' : '' }}">BN</a>
                </div>
                <a href="{{ tenant_web_url('/portal') }}" class="solo-cta-outline text-sm">{{ __('Patient’s Portal') }}</a>
            </div>
        </div>
    </header>

    <div class="flex-1 w-full">
        @include('tenant.partials.ticket-body')
    </div>
</body>
</html>
