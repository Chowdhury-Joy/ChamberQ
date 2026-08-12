@php
    $tenant = tenant();
    $brand = $tenant->displayName();
    $hasLabTests = $tenant->hasFeature('lab_tests');
    $hasMultipleDoctors = $tenant->hasFeature('multiple_doctors');
    $hasMultipleChambers = $tenant->hasFeature('multiple_chambers');
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
    <title>{{ __('Book Appointment') }} | {{ $brand }}</title>
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
            --color-primary-hover: #1f8fc4;
            --font-family-base: 'DM Sans', system-ui, sans-serif;
            --font-family-display: 'Instrument Serif', Georgia, serif;
            --bg-base: #ffffff;
            --bg-surface: #ffffff;
            color-scheme: light;
        }
        html { color-scheme: light only; }
        body {
            margin: 0;
            font-family: var(--font-family-base);
            background: #ffffff;
            color: #0f172a;
            -webkit-font-smoothing: antialiased;
        }
        .font-display { font-family: var(--font-family-display); }
        .text-brand { color: var(--color-primary); }
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
            border: none;
            cursor: pointer;
            text-decoration: none;
            font-family: inherit;
        }
        .solo-cta:hover { opacity: 0.92; color: #fff; }
        .solo-cta:disabled { opacity: 0.45; cursor: not-allowed; }
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
            cursor: pointer;
        }
        .solo-cta-outline:hover { background: color-mix(in srgb, var(--color-primary) 12%, white); }
        .solo-cta-muted {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 9999px;
            border: 1.5px solid #E0E0E0;
            background: #FAFAFA;
            color: #475569;
            font-weight: 600;
            font-size: 0.95rem;
            padding: 16px 32px;
            transition: background 0.15s ease;
            border-style: solid;
            cursor: pointer;
            text-decoration: none;
            font-family: inherit;
        }
        .solo-cta-muted:hover { background: #F2F2F2; }

        main.section {
            max-width: 1280px;
            margin: 0 auto;
            padding: 1.5rem 0.75rem 3rem;
        }
        @media (min-width: 640px) {
            main.section { padding: 2rem 2.5rem 4rem; }
        }

        .booking-container {
            max-width: 720px;
            margin: 0 auto;
            background: #ffffff;
            padding: 1.5rem 1.25rem;
            border-radius: 1rem;
            border: 1px solid #E0E0E0;
            box-shadow: 0 1px 2px 0 rgba(27, 27, 27, 0.03), 0 0 0 1px rgba(27, 27, 27, 0.03);
            position: relative;
        }
        @media (min-width: 640px) {
            .booking-container { padding: 2.5rem; }
        }

        .booking-header { text-align: center; margin-bottom: 1.5rem; }
        @media (min-width: 640px) {
            .booking-header { margin-bottom: 2rem; }
        }
        .booking-header h2 {
            font-family: var(--font-family-display);
            font-size: 2rem;
            font-weight: 400;
            letter-spacing: -0.02em;
            color: #0f172a;
            margin: 0;
        }
        @media (min-width: 640px) {
            .booking-header h2 { font-size: 2.5rem; }
        }

        .step { display: none; animation: slideIn 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
        .step.active { display: block; }
        .step h3 {
            font-family: var(--font-family-display);
            font-size: 1.5rem;
            font-weight: 400;
            margin: 0 0 1.25rem;
            color: #0f172a;
        }
        @keyframes slideIn { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }

        .alert { padding: 1rem; border-radius: 0.75rem; margin-bottom: 1.5rem; display: none; }
        .alert-error { background: #fef2f2; color: #991b1b; border: 1px solid #f87171; display: block; }
        .field-error { color: #dc2626; font-size: 0.85rem; margin-top: 0.35rem; display: block; }

        .selection-grid { display: grid; gap: 0.85rem; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom: 1.5rem; }
        .selection-grid.list-view { grid-template-columns: 1fr; }
        .selection-card {
            /* Real <button>s so the flow is reachable by keyboard and screen
               reader — these were <div onclick>, which nothing but a mouse
               could operate. The first four properties are the button reset
               that keeps them rendering exactly as the divs did. */
            display: block;
            width: 100%;
            font: inherit;
            color: inherit;
            border: 1px solid #E0E0E0;
            border-radius: 1rem;
            padding: 1.15rem 1.25rem;
            min-height: 48px;
            cursor: pointer;
            transition: border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
            background: #FAFAFA;
            text-align: left;
        }
        .selection-card:focus-visible {
            outline: 2px solid var(--color-primary);
            outline-offset: 2px;
        }
        .selection-card:hover { border-color: #d4d4d4; box-shadow: 0 1px 2px 0 rgba(27, 27, 27, 0.03); }
        .selection-card.selected {
            border-color: var(--color-primary);
            background: color-mix(in srgb, var(--color-primary) 8%, white);
            box-shadow: 0 0 0 1px var(--color-primary);
        }
        /* `.sc-title` / `.sc-sub` are spans: the cards are <button>s now, and a
           heading or paragraph inside a button is invalid and reads badly to a
           screen reader. The old element selectors are kept alongside so any
           card still using them cannot silently lose its styling. */
        .selection-card h4,
        .selection-card .sc-title { display: block; margin: 0 0 0.5rem 0; color: #0f172a; font-size: 1.05rem; font-weight: 600; }
        .selection-card p,
        .selection-card .sc-sub { display: block; margin: 0; color: #64748b; font-size: 0.9rem; line-height: 1.4; }
        .selection-card .price { color: var(--color-primary); font-weight: 600; font-size: 1.1rem; float: right; }
        .selection-card.is-disabled { opacity: 0.55; cursor: not-allowed; pointer-events: none; background: #F2F2F2; }
        .selection-card .seats { margin-top: 0.65rem; font-size: 0.85rem; font-weight: 600; color: var(--color-primary); }
        .selection-card .seats.is-full { color: #b91c1c; }
        .selection-card .seats.is-closed { color: #92400e; }

        .booking-review {
            background: #FAFAFA;
            border: 1px solid #E0E0E0;
            border-radius: 1rem;
            padding: 1rem 1.15rem;
            margin: 0 0 1.5rem;
            font-size: 0.95rem;
            line-height: 1.5;
            color: #334155;
        }
        .booking-review strong { color: #0f172a; }
        .booking-review .seats-line { margin-top: 0.35rem; font-weight: 600; color: var(--color-primary); }
        .booking-review .seats-line.is-full, .booking-review .seats-line.is-closed { color: #b91c1c; }

        /* Keep Back/Continue reachable without scrolling past a long date list.
           Sticky (not fixed) so the bar settles inline on short steps. */
        .btn-group {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 0.75rem;
            position: sticky;
            bottom: 0;
            z-index: 20;
            margin: 1.75rem -1.25rem 0;
            padding: 0.85rem 1.25rem calc(0.85rem + env(safe-area-inset-bottom, 0px));
            background: #ffffff;
            box-shadow: 0 -8px 18px -14px rgba(15, 23, 42, 0.5);
            border-top: 1px solid #E6E6E6;
            flex-wrap: nowrap;
        }
        .btn-group .btn { padding-left: 1.25rem; padding-right: 1.25rem; }
        .btn-group .btn-back { flex: 0 0 auto; }
        .btn-group .btn-primary { flex: 1 1 auto; }

        @media (min-width: 640px) {
            .btn-group {
                margin-left: -2.5rem;
                margin-right: -2.5rem;
                padding-left: 2.5rem;
                padding-right: 2.5rem;
            }
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            min-height: 48px;
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
        .btn-primary:disabled { opacity: 0.45; cursor: not-allowed; }
        .btn-back {
            border-radius: 9999px;
            border: 1.5px solid #E0E0E0;
            background: #FAFAFA;
            color: #475569;
            padding: 16px 32px;
            transition: background 0.15s ease;
        }
        .btn-back:hover { background: #F2F2F2; }

        .progress-bar { display: flex; justify-content: center; gap: 0.5rem; margin-bottom: 0.65rem; }
        .progress-dot { width: 8px; height: 8px; border-radius: 50%; background: #E0E0E0; transition: all 0.3s; }
        .progress-dot.active { background: var(--color-primary); transform: scale(1.3); }
        .progress-dot.completed { background: #94a3b8; }
        .step-label { margin: 0; font-size: 0.9rem; font-weight: 600; color: #64748b; }

        .lab-total {
            font-size: 1.25rem;
            font-weight: 600;
            text-align: right;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 2px dashed #E6E6E6;
            color: #0f172a;
        }
        .lab-test-error { display: none; color: #dc2626; font-size: 0.9rem; margin-top: 0.75rem; }
        .hidden { display: none !important; }

        .patient-picker-options { display: flex; flex-direction: column; gap: 0.5rem; }
        .patient-picker-btn {
            width: 100%;
            text-align: left;
            padding: 0.85rem 1rem;
            border-radius: 0.75rem;
            border: 1px solid #E0E0E0;
            background: #ffffff;
            color: #0f172a;
            font-family: inherit;
            font-size: 1rem;
            cursor: pointer;
        }
        .patient-picker-btn:hover { border-color: #d4d4d4; }
        .patient-picker-btn.selected {
            border-color: var(--color-primary);
            background: color-mix(in srgb, var(--color-primary) 8%, white);
            font-weight: 600;
        }
        .patient-picker-btn-new { font-style: italic; color: #64748b; }

        .form-group { margin-bottom: 0.85rem; }
        .form-label { display: block; margin-bottom: 0.35rem; font-weight: 500; color: #0f172a; }
        .form-control {
            width: 100%;
            padding: 0.55rem 0.85rem;
            min-height: 42px;
            border-radius: 0.65rem;
            border: 1px solid #E0E0E0;
            background: #ffffff;
            color: #0f172a;
            font-family: inherit;
            font-size: 0.95rem;
            box-sizing: border-box;
        }
        .form-control:focus {
            outline: none;
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px color-mix(in srgb, var(--color-primary) 18%, transparent);
        }
        .field-row {
            display: flex;
            align-items: stretch;
            gap: 0.65rem;
        }
        .btn-change-date {
            flex: 0 0 auto;
            min-height: 42px;
            padding: 0.4rem 0.85rem;
            font-size: 0.85rem;
            white-space: nowrap;
            align-self: stretch;
        }
        .field-float {
            position: relative;
        }
        .field-float > .form-control {
            padding: 1.05rem 0.85rem 0.35rem;
            min-height: 42px;
        }
        .field-float .field-float-label {
            position: absolute;
            left: 0.85rem;
            top: 50%;
            transform: translateY(-50%);
            margin: 0;
            font-size: 0.95rem;
            font-weight: 500;
            color: #64748b;
            pointer-events: none;
            transition: top 0.12s ease, transform 0.12s ease, font-size 0.12s ease;
            line-height: 1;
        }
        .field-float > .form-control:focus ~ .field-float-label,
        .field-float > .form-control:not(:placeholder-shown) ~ .field-float-label,
        .field-float > .form-control:disabled ~ .field-float-label,
        .field-float--filled > .field-float-label {
            top: 0.4rem;
            transform: none;
            font-size: 0.68rem;
            font-weight: 600;
            color: #64748b;
        }
        .field-display {
            margin: 0;
            display: flex;
            align-items: flex-end;
            padding-bottom: 0.4rem !important;
            background: #f8fafc;
            line-height: 1.25;
        }
        .text-muted { color: #64748b; }

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

    <main class="section flex-1 w-full">
        <div class="booking-container">
            @include('tenant.partials.booking-wizard')
        </div>
    </main>

    <script>
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register(@json(tenant_web_url('/sw.js'))).catch(() => {});
        }
    </script>
</body>
</html>
