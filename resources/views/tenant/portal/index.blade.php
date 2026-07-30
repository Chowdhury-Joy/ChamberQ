@php
    $tenant = tenant();
    $locale = app()->getLocale();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="{{ $tenant->theme_color ?: \App\Models\Tenant::DEFAULT_THEME_COLOR }}">
    <title>Patient Portal | {{ $tenant->displayName() }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="/css/theme.css">
    <style>
        :root { --color-primary: {{ $tenant->theme_color ?: \App\Models\Tenant::DEFAULT_THEME_COLOR }}; }
    </style>
</head>
<body class="bg-slate-50 text-slate-900 min-h-screen flex flex-col pt-20">
    <nav class="navbar fixed top-0 left-0 right-0 z-50 bg-white/90 backdrop-blur-md border-b border-slate-200 py-3">
        <div class="max-w-[1320px] w-full mx-auto px-4 md:px-6 xl:px-8 flex items-center justify-between">
            <a href="{{ tenant_web_url('/') }}" class="navbar-brand text-lg font-bold flex items-center gap-2" style="color: var(--color-primary);">
                @if($tenant->logo_url)
                    <img src="{{ $tenant->logo_url }}" alt="{{ $tenant->displayName() }}" style="height: 36px;">
                @else
                    <span>{{ $tenant->displayName() }}</span>
                @endif
            </a>
            <div class="navbar-nav flex items-center gap-4 text-sm font-medium text-slate-700">
                <div class="locale-chip">
                    <a href="{{ tenant_web_url('/lang/en') }}" class="{{ $locale === 'en' ? 'is-active' : '' }}">EN</a>
                    <span aria-hidden="true">|</span>
                    <a href="{{ tenant_web_url('/lang/bn') }}" class="{{ $locale === 'bn' ? 'is-active' : '' }}">BN</a>
                </div>
                <a href="{{ tenant_web_url('/') }}" class="hover:opacity-80 transition-colors">{{ __('Home') }}</a>
                <a href="{{ tenant_web_url('/book') }}" class="btn btn-primary ml-2">{{ __('Book Appointment') }}</a>
            </div>
        </div>
    </nav>

    <main class="flex-grow max-w-[1320px] w-full mx-auto px-4 md:px-6 xl:px-8 py-8 md:py-12">
        <div class="max-w-2xl mx-auto bg-white rounded-3xl p-6 sm:p-10 border border-slate-200/80 shadow-sm mb-10">
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight text-center mb-3">
                Patient Access Portal
            </h1>
            <p class="text-slate-600 text-sm text-center mb-8">
                Enter your mobile phone number to look up your appointments, queue tickets, and lab test status.
            </p>

            <form action="/portal" method="GET" class="flex flex-col sm:flex-row gap-3">
                <label for="portal-phone" class="sr-only">Mobile phone number</label>
                <input id="portal-phone" type="text" name="phone" value="{{ $phone ?? '' }}" placeholder="Enter mobile number (e.g. 01712345678)" required
                       class="flex-1 px-4 py-3 rounded-full border border-slate-300 focus:outline-none focus:border-sky-500 text-sm"
                       autocomplete="tel">
                <button type="submit" class="btn btn-primary px-8 py-3 rounded-full text-sm">
                    Search Appointments
                </button>
            </form>
            @if(! empty($error))
                <p class="mt-4 text-sm text-center text-red-600" role="alert">{{ $error }}</p>
            @endif
        </div>

        @if(filled($phone) && empty($error))
            <div class="max-w-4xl mx-auto">
                <h2 class="text-xl font-bold text-slate-900 mb-4">Search Results for "{{ $phone }}"</h2>
                
                @if($bookings->isEmpty())
                    <div class="bg-white rounded-2xl p-8 text-center border border-slate-200/80">
                        <p class="text-slate-500 text-sm">No appointment records found for this phone number.</p>
                        <a href="{{ tenant_web_url('/book') }}" class="inline-block mt-4 text-sm font-bold text-sky-600 underline">Book a new appointment</a>
                    </div>
                @else
                    <div class="space-y-4">
                        @foreach($bookings as $booking)
                            <div class="bg-white rounded-2xl p-6 border border-slate-200/80 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                <div>
                                    <div class="flex items-center gap-2 mb-1">
                                        <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-sky-100 text-sky-800">
                                            Ticket #{{ $booking->serial_number ?? $booking->id }}
                                        </span>
                                        <span class="text-xs text-slate-400">
                                            {{ $booking->created_at ? $booking->created_at->format('M d, Y') : '' }}
                                        </span>
                                    </div>
                                    <h3 class="text-base font-bold text-slate-900">{{ $booking->patient_name }}</h3>
                                    <p class="text-xs text-slate-500 mt-1">Phone: {{ $booking->patient_phone }}</p>
                                </div>
                                <div>
                                    <a href="{{ tenant_web_route('bookings.show', $booking) }}" target="_blank" rel="noopener noreferrer" class="btn border border-sky-500 text-sky-600 hover:bg-sky-50 text-xs px-4 py-2">
                                        View Digital Ticket
                                    </a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif
    </main>

    <footer class="w-full bg-slate-900 text-slate-400 py-8 border-t border-slate-800 text-xs text-center mt-12">
        <p>&copy; {{ date('Y') }} {{ $tenant->displayName() }}. Patient Access Portal.</p>
    </footer>
</body>
</html>
