@php
    /*
     * Clinic booking shell, Clireo design.
     *
     * The wizard itself is `tenant.partials.booking-wizard`, shared with solo.
     * Its markup is NOT touched here — it uses semantic class names only
     * (`.step`, `.selection-card`, `.btn-group`…) which each shell styles, so
     * the clinic can be restyled without any risk to the solo booking flow.
     * Every rule below keeps the layout, sizing and sticky-footer behaviour of
     * the previous design; only colour and type were re-pointed at the Clireo
     * tokens. This is the conversion path — do not "tidy" its structure.
     */
    $tenant = tenant();
    $brand = $tenant->displayName();
    $hasLabTests = $tenant->hasFeature('lab_tests');
    $hasMultipleDoctors = $tenant->hasFeature('multiple_doctors');
    $hasMultipleChambers = $tenant->hasFeature('multiple_chambers');
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
    <title>{{ __('Book Appointment') }} | {{ $brand }}</title>
    <link rel="manifest" href="{{ tenant_web_url('/manifest.webmanifest') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Golos+Text:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" href="{{ $tenant->faviconHref() }}">
    <link rel="stylesheet" href="{{ public_asset('css/getwebfield-spacing.css') }}">
    <link rel="stylesheet" href="{{ public_asset('css/clinic-clireo.css') }}">
    <style>
        :root { --brand: {{ $themeColor }}; }
        html { color-scheme: light only; }
        body { background: var(--bg); }

        main.section {
            max-width: var(--layout-max-width);
            margin: 0 auto;
            padding: 1.5rem 0.75rem 3rem;
        }
        @media (min-width: 640px) {
            main.section { padding: 2rem 2.5rem 4rem; }
        }

        .booking-container {
            max-width: 720px;
            margin: 0 auto;
            background: var(--white);
            padding: 1.5rem 1.25rem;
            border-radius: 20px;
            border: 1px solid var(--line);
            box-shadow: 0 24px 60px color-mix(in srgb, var(--ink-deep) 10%, transparent);
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
            font-size: clamp(1.75rem, 1.1rem + 1.8vw, 2.5rem);
            font-weight: 400;
            letter-spacing: -0.045em;
            line-height: 1.1;
            color: var(--ink);
            margin: 0;
        }

        .step { display: none; animation: slideIn 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
        .step.active { display: block; }
        .step h3 {
            font-size: 1.5rem;
            font-weight: 400;
            letter-spacing: -0.035em;
            margin: 0 0 1.25rem;
            color: var(--ink);
        }
        @keyframes slideIn { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }

        /* Errors keep their own red — a brand-tinted failure state is not a
           failure state. */
        .alert { padding: 1rem; border-radius: 12px; margin-bottom: 1.5rem; display: none; }
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
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 1.15rem 1.25rem;
            min-height: 48px;
            cursor: pointer;
            transition: border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
            background: var(--bg);
            text-align: left;
        }
        .selection-card:focus-visible {
            outline: 2px solid var(--brand);
            outline-offset: 2px;
        }
        .selection-card:hover { border-color: color-mix(in srgb, var(--ink) 25%, var(--line)); }
        .selection-card.selected {
            border-color: var(--brand);
            background: color-mix(in srgb, var(--brand) 8%, white);
            box-shadow: 0 0 0 1px var(--brand);
        }
        /* `.sc-title` / `.sc-sub` are spans: the cards are <button>s now, and a
           heading or paragraph inside a button is invalid and reads badly to a
           screen reader. The old element selectors are kept alongside so any
           card still using them cannot silently lose its styling. */
        .selection-card h4,
        .selection-card .sc-title { display: block; margin: 0 0 0.5rem 0; color: var(--ink); font-size: 1.05rem; font-weight: 600; }
        .selection-card p,
        .selection-card .sc-sub { display: block; margin: 0; color: var(--muted); font-size: 0.9rem; line-height: 1.4; }
        .selection-card .price { color: var(--ink); font-weight: 600; font-size: 1.1rem; float: right; }
        .selection-card.is-disabled { opacity: 0.55; cursor: not-allowed; pointer-events: none; background: color-mix(in srgb, var(--ink) 6%, var(--bg)); }
        .selection-card .seats { margin-top: 0.65rem; font-size: 0.85rem; font-weight: 600; color: var(--ink); }
        .selection-card .seats.is-full { color: #b91c1c; }
        .selection-card .seats.is-closed { color: #92400e; }

        .booking-review {
            background: var(--ink-deep, #0f172a);
            border: none;
            border-radius: 12px;
            padding: 0.85rem 1rem;
            margin: 0 0 1.15rem;
            font-size: 0.9rem;
            line-height: 1.45;
            color: #f8fafc;
        }
        .booking-review strong { color: #ffffff; font-weight: 600; }
        .booking-review .seats-line { margin-top: 0.35rem; font-weight: 600; color: #e2e8f0; }
        .booking-review .seats-line.is-full, .booking-review .seats-line.is-closed { color: #fecaca; }

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
            background: var(--white);
            box-shadow: 0 -8px 18px -14px color-mix(in srgb, var(--ink-deep) 50%, transparent);
            border-top: 1px solid var(--line);
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
            /* Arrow-pill machine comes from clinic-clireo.css (.btn.btn-primary). */
            min-height: 0;
            padding: 0.35rem 0.35rem 0.35rem 1.25rem;
        }
        .btn-primary:disabled { opacity: 0.45; cursor: not-allowed; transform: none; }
        .btn-back {
            border-radius: 40px;
            border: 1px solid color-mix(in srgb, var(--ink) 25%, transparent);
            background: var(--white);
            color: var(--ink);
            padding: 16px 32px;
            transition: background 0.15s ease;
        }
        .btn-back:hover { background: var(--bg); }

        .progress-bar { display: flex; justify-content: center; gap: 0.5rem; margin-bottom: 0.65rem; }
        .progress-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--line); transition: all 0.3s; }
        .progress-dot.active { background: var(--brand); transform: scale(1.3); }
        .progress-dot.completed { background: color-mix(in srgb, var(--ink) 45%, var(--line)); }
        .step-label { margin: 0; font-size: 0.9rem; font-weight: 600; color: var(--muted); }

        .lab-total {
            font-size: 1.25rem;
            font-weight: 600;
            text-align: right;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 2px dashed var(--line);
            color: var(--ink);
        }
        .lab-test-error { display: none; color: #dc2626; font-size: 0.9rem; margin-top: 0.75rem; }
        .hidden { display: none !important; }

        .patient-picker-options { display: flex; flex-direction: column; gap: 0.5rem; }
        .patient-picker-btn {
            width: 100%;
            text-align: left;
            padding: 0.85rem 1rem;
            border-radius: 12px;
            border: 1px solid var(--line);
            background: var(--white);
            color: var(--ink);
            font-family: inherit;
            font-size: 1rem;
            cursor: pointer;
        }
        .patient-picker-btn:hover { border-color: color-mix(in srgb, var(--ink) 25%, var(--line)); }
        .patient-picker-btn.selected {
            border-color: var(--brand);
            background: color-mix(in srgb, var(--brand) 8%, white);
            font-weight: 600;
        }
        .patient-picker-btn-new { font-style: italic; color: var(--muted); }

        .form-group { margin-bottom: 0.85rem; }
        .form-label { display: block; margin-bottom: 0.35rem; font-weight: 600; color: var(--ink-deep); font-size: 0.8rem; }
        .form-control {
            width: 100%;
            padding: 0.55rem 0.85rem;
            min-height: 42px;
            border-radius: 10px;
            border: 1px solid var(--line);
            background: #fafbfc;
            color: var(--ink-deep);
            font-family: inherit;
            font-size: 0.95rem;
            box-sizing: border-box;
        }
        .form-control:focus {
            outline: none;
            border-color: var(--ink);
            box-shadow: 0 0 0 3px color-mix(in srgb, var(--brand) 18%, transparent);
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
            color: var(--muted);
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
            color: var(--muted);
        }
        .field-display {
            margin: 0;
            display: flex;
            align-items: flex-end;
            padding-bottom: 0.4rem !important;
            background: color-mix(in srgb, var(--ink) 4%, var(--white));
            line-height: 1.25;
        }
        .text-muted { color: var(--muted); }
    </style>
    <script>document.documentElement.classList.add('has-js');</script>
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

            <nav class="nav-links" aria-label="{{ __('Main') }}">
                <a href="{{ tenant_web_url('/') }}">{{ __('Home') }}</a>
                <a href="{{ tenant_web_url('/portal') }}">{{ __('Patient’s Portal') }}</a>
                <span class="nav-lang" aria-label="{{ __('Language') }}">
                    <a href="{{ tenant_web_url('/lang/en') }}" @class(['is-active' => $locale === 'en'])>EN</a>
                    <span aria-hidden="true">|</span>
                    <a href="{{ tenant_web_url('/lang/bn') }}" @class(['is-active' => $locale === 'bn'])>BN</a>
                </span>
            </nav>

            {{-- No burger here: the booking page has two links, and a drawer
                 between a patient and the wizard is friction, not navigation. --}}
            <a class="btn-contact btn-contact--always" href="{{ tenant_web_url('/portal') }}">
                <span class="nav-cta-full">{{ __('Patient’s Portal') }}</span>
                <span class="nav-cta-short">{{ __('Portal') }}</span>
            </a>
        </div>
    </header>

    <main class="section">
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
