@php
    $tenant = tenant();
    $hasLabTests = $tenant->hasFeature('lab_tests');
    $hasMultipleDoctors = $tenant->hasFeature('multiple_doctors');
    $hasMultipleChambers = $tenant->hasFeature('multiple_chambers');
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
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="{{ $themeColor }}">
    <title>{{ __('Book Appointment') }} | {{ $tenant->displayName() }}</title>
    <link rel="manifest" href="{{ tenant_web_url('/manifest.webmanifest') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
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
        .booking-container { max-width: 650px; margin: 1.25rem auto; background: var(--bg-surface); padding: 1.25rem; border-radius: var(--radius-lg); box-shadow: var(--shadow-md); position: relative; }
        @media (min-width: 640px) {
            .booking-container { margin: 2rem auto; padding: 2rem; }
        }
        @media (min-width: 768px) {
            .booking-container { margin: 3rem auto; padding: 3rem; }
        }
        .booking-header { text-align: center; margin-bottom: 1.5rem; }
        @media (min-width: 640px) {
            .booking-header { margin-bottom: 2.5rem; }
        }
        main.section { padding: 1rem 0.75rem 2rem; }
        @media (min-width: 640px) {
            main.section { padding: 2rem 1.5rem; }
        }
        .step { display: none; animation: slideIn 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
        .step.active { display: block; }
        @keyframes slideIn { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }
        .alert { padding: 1rem; border-radius: var(--radius-md); margin-bottom: 1.5rem; display: none; }
        .alert-error { background: #fef2f2; color: #991b1b; border: 1px solid #f87171; display: block; }
        .field-error { color: #dc2626; font-size: 0.85rem; margin-top: 0.35rem; display: block; }
        .selection-grid { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom: 1.5rem; }
        .selection-grid.list-view { grid-template-columns: 1fr; }
        .selection-card { border: 2px solid #e2e8f0; border-radius: var(--radius-md); padding: 1.25rem; min-height: 48px; cursor: pointer; transition: all 0.2s ease; background: #fff; text-align: left; }
        .selection-card:hover { border-color: #cbd5e1; box-shadow: var(--shadow-sm); }
        .selection-card.selected { border-color: var(--color-primary); background: #f0f9ff; box-shadow: 0 0 0 1px var(--color-primary); }
        .selection-card h4 { margin: 0 0 0.5rem 0; color: #0f172a; font-size: 1.1rem; }
        .selection-card p { margin: 0; color: #64748b; font-size: 0.9rem; line-height: 1.4; }
        .selection-card .price { color: var(--color-primary); font-weight: 600; font-size: 1.1rem; float: right; }
        .selection-card.is-disabled { opacity: 0.55; cursor: not-allowed; pointer-events: none; background: #f8fafc; }
        .selection-card .seats { margin-top: 0.65rem; font-size: 0.85rem; font-weight: 600; color: var(--color-primary); }
        .selection-card .seats.is-full { color: #b91c1c; }
        .selection-card .seats.is-closed { color: #92400e; }
        .booking-review {
            background: #f8fafc; border: 1px solid #e2e8f0; border-radius: var(--radius-md);
            padding: 1rem 1.15rem; margin: 0 0 1.5rem; font-size: 0.95rem; line-height: 1.5; color: #334155;
        }
        .booking-review strong { color: #0f172a; }
        .booking-review .seats-line { margin-top: 0.35rem; font-weight: 600; color: var(--color-primary); }
        .booking-review .seats-line.is-full, .booking-review .seats-line.is-closed { color: #b91c1c; }
        .btn-group { display: flex; justify-content: space-between; gap: 0.75rem; margin-top: 2rem; border-top: 1px solid #e2e8f0; padding-top: 1.5rem; }
        .btn { min-height: 48px; }
        .btn-back { background: #f1f5f9; color: #475569; border: none; }
        .btn-back:hover { background: #e2e8f0; }
        /* Phones: keep Back/Continue reachable without scrolling to the end of
           a long step. Sticky (not fixed) so the bar settles inline on short
           steps and never covers content. */
        @media (max-width: 639.98px) {
            .btn-group {
                position: sticky;
                bottom: 0;
                z-index: 20;
                margin: 1.75rem -1.25rem 0;
                padding: 0.85rem 1.25rem calc(0.85rem + env(safe-area-inset-bottom));
                background: var(--bg-surface);
                box-shadow: 0 -8px 18px -14px rgba(15, 23, 42, 0.5);
            }
            .btn-group .btn { padding-left: 1.25rem; padding-right: 1.25rem; }
            .btn-group .btn-back { flex: 0 0 auto; }
            .btn-group .btn-primary { flex: 1 1 auto; }
        }
        .progress-bar { display: flex; justify-content: center; gap: 0.5rem; margin-bottom: 0.65rem; }
        .progress-dot { width: 8px; height: 8px; border-radius: 50%; background: #e2e8f0; transition: all 0.3s; }
        .progress-dot.active { background: var(--color-primary); transform: scale(1.3); }
        .progress-dot.completed { background: #94a3b8; }
        .step-label { margin: 0; font-size: 0.9rem; font-weight: 600; color: #64748b; }
        .lab-total { font-size: 1.25rem; font-weight: 600; text-align: right; margin-top: 1rem; padding-top: 1rem; border-top: 2px dashed #e2e8f0; color: #0f172a; }
        .lab-test-error { display: none; color: #dc2626; font-size: 0.9rem; margin-top: 0.75rem; }
        .hidden { display: none !important; }
    </style>
</head>
<body>
    <nav class="navbar">
        <a href="{{ tenant_web_url('/') }}" class="navbar-brand">
            @if($tenant->logo_url)
                <img src="{{ $tenant->logo_url }}" alt="{{ $tenant->displayName() }}" style="height: 36px; vertical-align: middle;">
            @else
                {{ $tenant->displayName() }}
            @endif
        </a>
        <div class="navbar-nav" style="display:flex;align-items:center;gap:1rem;">
            <div class="locale-chip" aria-label="{{ __('Language') }}">
                <a href="{{ tenant_web_url('/lang/en') }}" class="{{ $locale === 'en' ? 'is-active' : '' }}">EN</a>
                <span aria-hidden="true">|</span>
                <a href="{{ tenant_web_url('/lang/bn') }}" class="{{ $locale === 'bn' ? 'is-active' : '' }}">BN</a>
            </div>
            <a href="{{ tenant_web_url('/') }}">{{ __('Home') }}</a>
        </div>
    </nav>

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
