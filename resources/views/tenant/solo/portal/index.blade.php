{{--
    Solo copy of the pre-Clireo patient portal, pinned on 2026-08-07.

    The portal used to be one shared view. Porting it to the clinic design
    would have restyled the solo portal too, so solo now renders this file and
    the clinic renders `tenant/portal/index.blade.php` (see
    BookingController::portal). Same split as book and ticket already use.
--}}
@php
    $tenant = tenant();
    $brand = $tenant->displayName();
    $themeColor = $tenant->theme_color ?: \App\Models\Tenant::DEFAULT_THEME_COLOR;
    $locale = app()->getLocale();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}" class="h-full" style="color-scheme: light;">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light only">
    <meta name="theme-color" content="{{ $themeColor }}">
    <title>{{ __('Patient Portal') }} | {{ $brand }}</title>
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
            --color-primary-hover: #1d4ed8;
            --font-family-base: 'DM Sans', system-ui, sans-serif;
            --font-family-display: 'Instrument Serif', Georgia, serif;
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
            transition: opacity 0.15s ease;
            border: none;
            cursor: pointer;
            text-decoration: none;
            font-family: inherit;
        }
        .solo-cta:hover { opacity: 0.92; color: #fff; }
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
        .form-control {
            width: 100%;
            padding: 0.85rem 1rem;
            border-radius: 9999px;
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
                <a href="{{ tenant_web_url('/book') }}" class="solo-cta">{{ __('Book Appointment') }}</a>
            </nav>

            <div class="flex items-center gap-2 md:hidden">
                <div class="locale-chip" aria-label="{{ __('Language') }}">
                    <a href="{{ tenant_web_url('/lang/en') }}" class="{{ $locale === 'en' ? 'is-active' : '' }}">EN</a>
                    <span aria-hidden="true">|</span>
                    <a href="{{ tenant_web_url('/lang/bn') }}" class="{{ $locale === 'bn' ? 'is-active' : '' }}">BN</a>
                </div>
                <a href="{{ tenant_web_url('/book') }}" class="solo-cta text-sm !px-5 !py-2">{{ __('Book') }}</a>
            </div>
        </div>
    </header>

    <main class="mx-auto w-full max-w-[1280px] flex-1 px-3 py-10 sm:px-10 sm:py-14">
        <div class="mx-auto mb-10 max-w-2xl rounded-2xl border p-6 sm:p-10" style="background-color: #FAFAFA; border-color: #E0E0E0;">
            <h1 class="font-display text-center text-3xl tracking-tight text-slate-900 sm:text-4xl">
                {{ __('Patient Access Portal') }}
            </h1>
            <p class="mx-auto mt-3 max-w-md text-center text-sm leading-relaxed text-slate-600">
                {{ __('Enter your mobile phone number to look up your appointments, queue tickets, and lab test status.') }}
            </p>

            <form action="{{ tenant_web_url('/portal') }}" method="GET" class="mt-8 flex flex-col gap-3 sm:flex-row">
                <label for="portal-phone" class="sr-only">{{ __('Mobile phone number') }}</label>
                <input
                    id="portal-phone"
                    type="tel"
                    name="phone"
                    value="{{ $phone ?? '' }}"
                    placeholder="017XXXXXXXX"
                    required
                    inputmode="numeric"
                    autocomplete="tel"
                    class="form-control flex-1"
                >
                <button type="submit" class="solo-cta shrink-0">
                    {{ __('Search Appointments') }}
                </button>
            </form>
            @if(! empty($error))
                <p class="mt-4 text-center text-sm text-red-600" role="alert">{{ $error }}</p>
            @endif
        </div>

        @if(filled($phone) && empty($error))
            <div class="mx-auto max-w-4xl">
                <h2 class="font-display text-2xl text-slate-900 sm:text-3xl">
                    {{ __('Search Results for') }} “{{ $phone }}”
                </h2>

                @if($bookings->isEmpty())
                    <div class="mt-6 rounded-2xl border bg-white p-8 text-center" style="border-color: #E0E0E0;">
                        <p class="text-sm text-slate-500">{{ __('No appointment records found for this phone number.') }}</p>
                        <a href="{{ tenant_web_url('/book') }}" class="solo-cta-outline mt-4 inline-flex">{{ __('Book a new appointment') }}</a>
                    </div>
                @else
                    <div class="mt-6 space-y-4">
                        @foreach($bookings as $booking)
                            <div class="flex flex-col justify-between gap-4 rounded-2xl border p-5 sm:flex-row sm:items-center sm:p-6" style="background-color: #FAFAFA; border-color: #E0E0E0;">
                                <div>
                                    <div class="mb-1 flex flex-wrap items-center gap-2">
                                        <span class="rounded-full px-2.5 py-0.5 text-xs font-bold" style="background-color: color-mix(in srgb, var(--color-primary) 14%, white); color: var(--color-primary);">
                                            {{ __('Ticket') }} #{{ $booking->serial_number ?? $booking->id }}
                                        </span>
                                        <span class="text-xs text-slate-400">
                                            {{ $booking->created_at ? $booking->created_at->format('M d, Y') : '' }}
                                        </span>
                                    </div>
                                    <h3 class="text-base font-semibold text-slate-900">{{ $booking->patient_name }}</h3>
                                    <p class="mt-1 text-xs text-slate-500">{{ __('Phone') }}: {{ $booking->patient_phone }}</p>
                                </div>
                                <a href="{{ tenant_web_route('bookings.show', $booking) }}" target="_blank" rel="noopener noreferrer" class="solo-cta-outline text-sm">
                                    {{ __('View Digital Ticket') }}
                                </a>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif
    </main>

    <footer class="mt-auto border-t border-slate-100 bg-white py-8 text-center text-sm text-slate-500">
        <p>&copy; {{ date('Y') }} {{ $brand }}. {{ __('Patient Access Portal') }}.</p>
    </footer>
</body>
</html>
