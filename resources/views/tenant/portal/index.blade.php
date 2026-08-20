@php
    /*
     * Clinic patient portal, Clireo design.
     *
     * Unlike book/ticket this page owns all of its markup — there is no shared
     * partial underneath — so it is a full port, with no Tailwind CDN.
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
    <title>{{ __('Patient Portal') }} | {{ $brand }}</title>
    @include('partials.seo', ['seo' => \App\Support\PublicSeo::tenantPage($tenant, __('Patient Portal').' | '.$brand, null, false)])
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
        body { background: var(--bg); display: flex; flex-direction: column; min-height: 100vh; }
        main { flex: 1 0 auto; }

        .portal-lookup {
            max-width: 680px;
            margin: 0 auto var(--space-lg);
            background: var(--white);
            border: 1px solid var(--line);
            border-radius: 20px;
            padding: 1.75rem 1.25rem;
            box-shadow: 0 24px 60px color-mix(in srgb, var(--ink-deep) 10%, transparent);
        }
        @media (min-width: 640px) {
            .portal-lookup { padding: 2.5rem; }
        }
        .portal-lookup h1 {
            margin: 0;
            text-align: center;
            font-size: clamp(1.9rem, 1.2rem + 2vw, 2.75rem);
            font-weight: 400;
            letter-spacing: -0.045em;
            line-height: 1.1;
            color: var(--ink);
        }
        .portal-lead {
            max-width: 30rem;
            margin: 0.85rem auto 0;
            text-align: center;
            font-size: 0.95rem;
            color: var(--muted);
        }

        .portal-form { display: flex; flex-direction: column; gap: 0.75rem; margin-top: 2rem; }
        @media (min-width: 640px) {
            .portal-form { flex-direction: row; }
        }
        .form-control {
            width: 100%;
            padding: 0.9rem 1.15rem;
            border-radius: 40px;
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
        .portal-form .form-control { flex: 1; }

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
            white-space: nowrap;
        }
        .btn-primary {
            /* Arrow-pill machine comes from clinic-clireo.css (.btn.btn-primary). */
            min-height: 0;
            padding: 0.35rem 0.35rem 0.35rem 1.25rem;
        }
        .btn-ghost {
            border-radius: 40px;
            border: 1px solid color-mix(in srgb, var(--ink) 25%, transparent);
            background: var(--white);
            color: var(--ink);
            padding: 12px 26px;
            transition: background 0.15s ease;
        }
        .btn-ghost:hover { background: var(--bg); }

        .portal-error { margin: 1rem 0 0; text-align: center; font-size: 0.9rem; color: #dc2626; }
        .portal-rx-form { display: flex; flex-direction: column; gap: 0.75rem; max-width: 24rem; margin-top: 1rem; }
        .portal-rx-lock-lead, .portal-rx-hint { color: var(--muted); }
        .portal-rx-hint { font-size: 0.82rem; margin-top: 0.75rem; }
        .portal-rx-heading { display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: 0.75rem; margin-bottom: 1.5rem; }
        .portal-rx-heading h2 { margin: 0; }

        .portal-results { max-width: 900px; margin: 0 auto; }
        .portal-results h2 {
            margin: 0 0 1.5rem;
            font-size: clamp(1.5rem, 1rem + 1.5vw, 2rem);
            font-weight: 400;
            letter-spacing: -0.04em;
            color: var(--ink);
        }
        .portal-empty {
            background: var(--white);
            border: 1px solid var(--line);
            border-radius: 20px;
            padding: 2.5rem 1.5rem;
            text-align: center;
        }
        .portal-empty p { margin: 0 0 1.25rem; color: var(--muted); font-size: 0.95rem; }

        .portal-list { display: grid; gap: 1rem; }
        .portal-card {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            background: var(--white);
            border: 1px solid var(--line);
            border-radius: 20px;
            padding: 1.25rem;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        .portal-card:hover {
            border-color: color-mix(in srgb, var(--brand) 30%, var(--line));
            box-shadow: 0 18px 40px color-mix(in srgb, var(--ink-deep) 8%, transparent);
        }
        @media (min-width: 640px) {
            .portal-card { flex-direction: row; align-items: center; justify-content: space-between; padding: 1.5rem; }
        }
        .portal-card-meta { display: flex; flex-wrap: wrap; align-items: center; gap: 0.6rem; margin-bottom: 0.5rem; }
        .portal-chip {
            border-radius: 40px;
            padding: 0.2rem 0.7rem;
            font-size: 0.75rem;
            font-weight: 700;
            background: color-mix(in srgb, var(--brand) 12%, var(--white));
            color: var(--ink-deep);
        }
        .portal-date { font-size: 0.78rem; color: var(--muted); }
        .portal-card h3 { margin: 0; font-size: 1.05rem; font-weight: 600; color: var(--ink-deep); }
        .portal-phone { margin: 0.35rem 0 0; font-size: 0.82rem; color: var(--muted); }

        /* The homepage footer is a four-column block and earns .space-section.
           Here it is one line, so it gets the inline padding without the tall
           section rhythm above and below it. */
        .footer {
            border-top: 1px solid var(--line);
            padding: 1.5rem var(--space-section-x);
        }
        .footer-bottom { padding-top: 0; }

        .sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0; }
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
                <span class="nav-lang" aria-label="{{ __('Language') }}">
                    <a href="{{ tenant_web_url('/lang/en') }}" @class(['is-active' => $locale === 'en'])>EN</a>
                    <span aria-hidden="true">|</span>
                    <a href="{{ tenant_web_url('/lang/bn') }}" @class(['is-active' => $locale === 'bn'])>BN</a>
                </span>
            </nav>

            <a class="btn-contact btn-contact--always" href="{{ tenant_web_url('/book') }}">
                <span class="nav-cta-full">{{ __('Book Appointment') }}</span>
                <span class="nav-cta-short">{{ __('Book') }}</span>
            </a>
        </div>
    </header>

    <main class="space-section">
        <div class="layout-container">
            <div class="portal-lookup">
                <h1>{{ __('Patient Access Portal') }}</h1>
                <p class="portal-lead">
                    {{ __('Enter your mobile number to look up appointments, tickets, and prescriptions. After a visit you can optionally set a password for next time.') }}
                </p>

                <form class="portal-form" action="{{ tenant_web_url('/portal') }}" method="POST">
                    @csrf
                    <label class="sr-only" for="portal-phone">{{ __('Mobile phone number') }}</label>
                    <input
                        id="portal-phone"
                        class="form-control"
                        type="tel"
                        name="phone"
                        value=""
                        placeholder="{{ filled($phoneDisplay ?? null) ? $phoneDisplay : '017XXXXXXXX' }}"
                        required
                        inputmode="numeric"
                        autocomplete="tel"
                    >
                    <button class="btn btn-primary" type="submit">{{ __('Search Appointments') }}</button>
                </form>

                @if(! empty($error))
                    <p class="portal-error" role="alert">{{ $error }}</p>
                @endif
            </div>

            @if(filled($phone) && empty($error))
                <div class="portal-results">
                    @include('tenant.partials.portal-prescription-lock', ['rxSetupButtonClass' => 'btn btn-primary'])

                    @if(($prescriptions ?? collect())->isNotEmpty())
                        <div class="portal-rx-heading">
                            <h2>{{ __('Your prescriptions') }}</h2>
                            <form action="{{ tenant_web_route('patient.portal.rx-lock', [], absolute: false) }}" method="POST">
                                @csrf
                                <button class="btn btn-ghost" type="submit">{{ __('Hide prescriptions') }}</button>
                            </form>
                        </div>
                        <div class="portal-list" style="margin-bottom: 2.5rem;">
                            @foreach($prescriptions as $prescription)
                                @php
                                    $rxBooking = $prescription->visitRecord?->booking;
                                    $rxDate = $rxBooking?->booking_date ?? $prescription->created_at;
                                @endphp
                                <div class="portal-card">
                                    <div>
                                        <div class="portal-card-meta">
                                            <span class="portal-chip">{{ __('Prescription') }}</span>
                                            @if ($rxDate)
                                                <span class="portal-date">{{ $rxDate->format('M d, Y') }}</span>
                                            @endif
                                        </div>
                                        <h3>{{ $prescription->patient?->name ?? $rxBooking?->patient_name }}</h3>
                                        <p class="portal-phone">
                                            {{ trans_choice(':count medicine|:count medicines', $prescription->items->count(), ['count' => $prescription->items->count()]) }}
                                        </p>
                                    </div>
                                    <a
                                        class="btn btn-ghost"
                                        href="{{ tenant_web_route('prescriptions.portal', ['prescription' => $prescription], absolute: false) }}"
                                    >
                                        {{ __('View prescription') }}
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <h2>{{ __('Search Results for') }} “{{ $phoneDisplay ?? $phone }}”</h2>

                    @if($bookings->isEmpty())
                        <div class="portal-empty">
                            <p>{{ __('No appointment records found for this phone number.') }}</p>
                            <a class="btn btn-ghost" href="{{ tenant_web_url('/book') }}">{{ __('Book a new appointment') }}</a>
                        </div>
                    @else
                        <div class="portal-list">
                            @foreach($bookings as $booking)
                                <div class="portal-card">
                                    <div>
                                        <div class="portal-card-meta">
                                            <span class="portal-chip">{{ __('Ticket') }} #{{ $booking->serial_number ?? $booking->id }}</span>
                                            <span class="portal-date">{{ $booking->created_at?->format('M d, Y') }}</span>
                                        </div>
                                        <h3>{{ $booking->patient_name }}</h3>
                                        <p class="portal-phone">{{ __('Phone') }}: {{ $booking->patient_phone }}</p>
                                    </div>
                                    <a class="btn btn-ghost" href="{{ tenant_web_route('bookings.show', $booking, absolute: false) }}" target="_blank" rel="noopener noreferrer">
                                        {{ __('View Digital Ticket') }}
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </main>

    <footer class="footer">
        <div class="layout-container footer-bottom">
            <span>&copy; {{ date('Y') }} {{ $brand }}. {{ __('All rights reserved.') }}</span>
            <span>{{ __('Powered by ChamberQ') }}</span>
        </div>
    </footer>
</body>
</html>
