@php
    /*
     * Clinic ticket shell, Clireo design.
     *
     * The ticket itself is `tenant.partials.ticket-body`, shared with solo, and
     * is NOT touched here. It carries semantic classes (`.ticket-card`,
     * `.serial`, `.detail-row`…) that each shell styles, plus a few inline
     * `var(--color-primary)` / `var(--radius-md)` references — those two tokens
     * are therefore aliased onto the Clireo palette below and must stay defined.
     */
    $tenant = tenant();
    $brand = $tenant->displayName();
    $themeColor = $tenant->cssThemeColor();
    $locale = app()->getLocale();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}" style="color-scheme: light;">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light only">
    <meta name="theme-color" content="{{ $themeColor }}">
    <meta name="robots" content="noindex">
    <title>{{ __('Your Appointment') }} | {{ $brand }}</title>
    @include('partials.seo', ['seo' => \App\Support\PublicSeo::tenantPage($tenant, __('Your Appointment').' | '.$brand, null, false)])
    <link rel="manifest" href="{{ tenant_web_url('/manifest.webmanifest') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Golos+Text:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" href="{{ $tenant->faviconHref() }}">
    <link rel="stylesheet" href="{{ public_asset('css/getwebfield-spacing.css') }}">
    <link rel="stylesheet" href="{{ public_asset('css/clinic-clireo.css') }}">
    <style>
        :root {
            --brand: {{ $themeColor }};
            /* Aliases for the shared partial's inline styles. */
            --color-primary: var(--brand);
            --radius-md: 12px;
        }
        html { color-scheme: light only; }
        body { background: var(--bg); }

        .ticket {
            max-width: 720px;
            margin: 0 auto;
            padding: 1.5rem 0.75rem 3rem;
        }
        @media (min-width: 640px) {
            .ticket { padding: 2.5rem 2.5rem 4rem; }
        }

        /* Keeps the serial on screen once the big one scrolls past. The Clireo
           nav is in flow rather than sticky, so the strip pins to the viewport
           top; ticket-body reads this rect itself, no JS offset to keep in sync. */
        .serial-strip {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 40;
            background: var(--white);
            border-bottom: 1px solid var(--line);
            box-shadow: 0 10px 30px -18px color-mix(in srgb, var(--ink-deep) 60%, transparent);
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.18s ease, visibility 0.18s ease;
        }
        .serial-strip.is-visible { opacity: 1; visibility: visible; }
        .serial-strip.is-called {
            background: #dcfce7;
            border-bottom-color: #86efac;
        }
        .serial-strip-inner {
            max-width: 720px;
            margin: 0 auto;
            display: flex;
            align-items: baseline;
            gap: 0.5rem;
            padding: 0.6rem 1.25rem;
        }
        @media (min-width: 640px) {
            .serial-strip-inner { padding-left: 2.5rem; padding-right: 2.5rem; }
        }
        .serial-strip-label {
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }
        .serial-strip-serial {
            font-size: 1.4rem;
            font-weight: 600;
            letter-spacing: -0.03em;
            color: var(--brand);
            line-height: 1;
        }
        .serial-strip.is-called .serial-strip-serial { color: #166534; }
        .serial-strip-now {
            margin-left: auto;
            display: inline-flex;
            align-items: baseline;
            gap: 0.4rem;
            font-weight: 700;
            color: var(--ink);
        }

        .ticket-card {
            background: var(--white);
            padding: 1.75rem 1.25rem;
            border-radius: 20px;
            border: 1px solid var(--line);
            box-shadow: 0 24px 60px color-mix(in srgb, var(--ink-deep) 10%, transparent);
            text-align: center;
        }
        @media (min-width: 640px) {
            .ticket-card { padding: 2.5rem; }
        }
        .ticket-brand {
            font-size: 1.6rem;
            font-weight: 400;
            letter-spacing: -0.04em;
            color: var(--ink);
            margin: 0 0 1rem;
        }
        .serial {
            font-size: clamp(3rem, 2rem + 4vw, 4.25rem);
            font-weight: 400;
            letter-spacing: -0.055em;
            color: var(--brand);
            line-height: 1;
            margin: 0.5rem 0 1.5rem;
        }
        .now-serving {
            font-size: 2rem;
            font-weight: 400;
            letter-spacing: -0.04em;
            color: var(--ink);
            margin: 0.15rem 0;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            padding: 0.7rem 0;
            border-top: 1px solid var(--line);
            text-align: left;
        }
        .detail-row strong { color: var(--ink-deep); font-weight: 600; }
        .share-actions { display: flex; flex-wrap: wrap; gap: 0.75rem; margin-top: 1rem; }
        .share-actions .btn { flex: 1 1 140px; }
        .link-box { display: flex; gap: 0.5rem; margin-top: 1rem; }
        .link-box input { flex: 1; min-width: 0; }
        .eta-box {
            background: var(--bg);
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
        .handoff {
            margin-top: 1.5rem;
            padding: 1rem 1.15rem;
            border-radius: 16px;
            background: color-mix(in srgb, var(--brand) 7%, var(--white));
            border: 1px solid color-mix(in srgb, var(--brand) 22%, var(--white));
            color: var(--ink);
            text-align: left;
            font-size: 0.95rem;
            line-height: 1.5;
        }
        .handoff strong { color: var(--ink-deep); }

        /* Preparation notes keep an amber warning palette: a brand-tinted
           "do not eat before your test" is not a warning. */
        .prep {
            margin-top: 1.5rem;
            padding: 1rem 1.25rem;
            text-align: left;
            border-radius: 16px;
            background: #fffbeb;
            border: 1px solid #f59e0b;
            color: #713f12;
        }
        .prep h2 { font-size: 1.2rem; font-weight: 600; letter-spacing: -0.02em; margin: 0 0 0.5rem; }
        .prep ul { margin: 0; padding-left: 1.1rem; }
        .prep li { margin: 0.35rem 0; }
        .prep-note { margin-top: 0.75rem; font-size: 0.9rem; font-weight: 600; }

        .sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0; }
        .text-muted { color: var(--muted); }

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
            /* Arrow-pill machine comes from clinic-clireo.css (.btn.btn-primary). */
            min-height: 0;
            padding: 0.35rem 0.35rem 0.35rem 1.25rem;
        }
        .btn-back {
            border-radius: 40px;
            border: 1px solid color-mix(in srgb, var(--ink) 25%, transparent);
            background: var(--white);
            color: var(--ink);
            padding: 14px 28px;
            transition: background 0.15s ease;
        }
        .btn-back:hover { background: var(--bg); }
        .form-control {
            width: 100%;
            padding: 0.85rem 1rem;
            border-radius: 10px;
            border: 1px solid var(--line);
            background: #fafbfc;
            color: var(--ink-deep);
            font-family: inherit;
            font-size: 1rem;
            box-sizing: border-box;
        }
        .form-control:focus {
            outline: none;
            border-color: var(--ink);
            box-shadow: 0 0 0 3px color-mix(in srgb, var(--brand) 18%, transparent);
        }

        .print-only { display: none; }
        @media print {
            @page { margin: 1.25cm; }
            body { background: #fff !important; color: #000 !important; }
            header, .no-print { display: none !important; }
            .print-only { display: block !important; }
            .ticket { max-width: none; margin: 0; padding: 0; }
            .ticket-card {
                box-shadow: none !important;
                border: 1px solid #ccc;
                border-radius: 0;
                padding: 1.5rem;
            }
            .serial, .ticket-brand, .now-serving { color: #000 !important; }
            .print-footer {
                margin-top: 1.5rem;
                padding-top: 1rem;
                border-top: 1px solid #ccc;
                text-align: center;
                font-size: 0.85rem;
                color: #333;
            }
            .print-footer .print-url {
                word-break: break-all;
                font-size: 0.75rem;
                margin-top: 0.35rem;
            }
            a { color: #000 !important; text-decoration: none; }
        }
    </style>
    <script>document.documentElement.classList.add('has-js');</script>
</head>
<body>
    <header class="nav no-print">
        <div class="nav-inner">
            <a class="logo" href="{{ tenant_web_url('/') }}" aria-label="{{ $brand }}">
                @if($tenant->logo_url)
                    <img class="logo-img" src="{{ $tenant->logo_url }}" alt="{{ $brand }}">
                @else
                    {{ $brand }}
                @endif
            </a>

            <nav class="nav-links" aria-label="{{ __('Main') }}">
                <a href="{{ tenant_web_url('/') }}">{{ __('Home') }}</a>
                <a href="{{ tenant_web_url('/book') }}">{{ __('Book Appointment') }}</a>
                <span class="nav-lang" aria-label="{{ __('Language') }}">
                    <a href="{{ tenant_web_url('/lang/en') }}" @class(['is-active' => $locale === 'en'])>EN</a>
                    <span aria-hidden="true">|</span>
                    <a href="{{ tenant_web_url('/lang/bn') }}" @class(['is-active' => $locale === 'bn'])>BN</a>
                </span>
            </nav>

            {{-- No burger: a patient holding a live ticket wants the portal, not
                 a site menu. One always-visible link, at every width. --}}
            <a class="btn-contact btn-contact--always" href="{{ tenant_web_url('/portal') }}">
                <span class="nav-cta-full">{{ __('Patient’s Portal') }}</span>
                <span class="nav-cta-short">{{ __('Portal') }}</span>
            </a>
        </div>
    </header>

    @include('tenant.partials.ticket-body')
</body>
</html>
