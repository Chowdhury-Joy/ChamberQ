<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="{{ tenant()->theme_color ?: \App\Models\Tenant::DEFAULT_THEME_COLOR }}">
    <meta name="robots" content="noindex">
    <title>{{ __('Your Appointment') }} | {{ tenant()->displayName() }}</title>
    <link rel="manifest" href="{{ tenant_web_url('/manifest.webmanifest') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    @php
        $tenant = tenant();
        $fontFamily = $tenant->font_family ?? 'Outfit';
        $themeColor = $tenant->theme_color ?: \App\Models\Tenant::DEFAULT_THEME_COLOR;
        $locale = app()->getLocale();
        $fontUrl = match($fontFamily) {
            'Inter' => 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap',
            'Roboto' => 'https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap',
            'Hind Siliguri' => 'https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&display=swap',
            default => 'https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap',
        };
    @endphp
    <link rel="stylesheet" href="{{ $fontUrl }}">
    @if($tenant->favicon_url)
    <link rel="icon" href="{{ $tenant->favicon_url }}">
    @endif
    <link rel="stylesheet" href="/css/theme.css">
    <style>
        :root {
            --color-primary: {{ $themeColor }};
            --font-family-base: '{{ $fontFamily }}', system-ui, -apple-system, sans-serif;
        }
        body { font-family: var(--font-family-base); }
        .ticket { max-width: 520px; margin: 2rem auto; padding: 0 1rem; }

        /* Sticky serial strip. This shell has no navbar — only the floating locale
           chip — so the strip sits at the very top and the chip is raised above it. */
        .serial-strip {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 30;
            background: var(--bg-surface);
            border-bottom: 1px solid rgba(128, 128, 128, .25);
            box-shadow: 0 4px 12px -8px rgba(15, 23, 42, 0.45);
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.18s ease, visibility 0.18s ease;
        }
        .serial-strip.is-visible { opacity: 1; visibility: visible; }
        .serial-strip.is-called { background: #dcfce7; border-bottom-color: #86efac; }
        .serial-strip-inner {
            max-width: 520px;
            margin: 0 auto;
            display: flex;
            align-items: baseline;
            gap: 0.5rem;
            padding: 0.6rem 1rem;
            padding-right: 4.5rem; /* clears the fixed EN|BN chip on narrow screens */
        }
        @media (min-width: 640px) {
            .serial-strip-inner { padding-right: 1rem; }
        }
        .serial-strip-label {
            font-size: 0.7rem;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .serial-strip-serial { font-size: 1.4rem; font-weight: 700; color: var(--color-primary); line-height: 1; }
        .serial-strip.is-called .serial-strip-serial { color: #166534; }
        .serial-strip-now {
            margin-left: auto;
            display: inline-flex;
            align-items: baseline;
            gap: 0.4rem;
            font-weight: 700;
        }
        .ticket-card { background: var(--bg-surface); padding: 2rem; border-radius: var(--radius-lg); box-shadow: var(--shadow-md); text-align: center; }
        .ticket-brand { font-weight: 700; font-size: 1.15rem; color: var(--color-primary); margin: 0 0 1rem; }
        .serial { font-size: 3.5rem; font-weight: 700; color: var(--color-primary); line-height: 1; margin: .5rem 0 1.5rem; }
        .now-serving { font-size: 2rem; font-weight: 600; }
        .detail-row { display: flex; justify-content: space-between; gap: 1rem; padding: .65rem 0; border-top: 1px solid rgba(128,128,128,.18); text-align: left; }
        .share-actions { display: flex; flex-wrap: wrap; gap: .5rem; margin-top: 1rem; justify-content: stretch; }
        .share-actions .btn { flex: 1 1 140px; }
        .link-box { display: flex; gap: .5rem; margin-top: 1rem; }
        .link-box input { flex: 1; min-width: 0; }
        .eta-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: var(--radius-md); padding: 1rem; margin-bottom: 1.5rem; }
        .handoff { margin-top: 1.5rem; padding: 1rem 1.15rem; border-radius: var(--radius-md); background: #f0f9ff; border: 1px solid #bae6fd; color: #0c4a6e; text-align: left; font-size: 0.95rem; line-height: 1.45; }
        .prep { margin-top: 1.5rem; padding: 1rem 1.25rem; text-align: left; border-radius: var(--radius-md); background: #fffbeb; border: 1px solid #f59e0b; color: #713f12; }
        .prep h2 { font-size: 1.05rem; margin-bottom: .5rem; }
        .prep ul { margin: 0; padding-left: 1.1rem; }
        .prep li { margin: .35rem 0; }
        .prep-note { margin-top: .75rem; font-size: .9rem; font-weight: 600; }
        .sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0; }
        .print-only { display: none; }
        @media print {
            @page { margin: 1.25cm; }
            body { background: #fff !important; color: #000 !important; }
            .locale-chip, .no-print { display: none !important; }
            .print-only { display: block !important; }
            .ticket { max-width: none; margin: 0; padding: 0; }
            .ticket-card {
                box-shadow: none !important;
                border: 1px solid #ccc;
                border-radius: 0;
                padding: 1.5rem;
            }
            .serial { color: #000 !important; }
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
</head>
<body>
    <div style="position:fixed;top:0.75rem;right:1rem;z-index:40;" class="locale-chip">
        <a href="{{ tenant_web_url('/lang/en') }}" class="{{ $locale === 'en' ? 'is-active' : '' }}">EN</a>
        <span aria-hidden="true">|</span>
        <a href="{{ tenant_web_url('/lang/bn') }}" class="{{ $locale === 'bn' ? 'is-active' : '' }}">BN</a>
    </div>
    @include('tenant.partials.ticket-body')
</body>
</html>
