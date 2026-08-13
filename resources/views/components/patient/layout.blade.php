@props(['title' => null])
@php
    $product = $product ?? config('marketing.product_name');
    $locale = app()->getLocale();
    $patient = auth('patient')->user();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="color-scheme" content="light only">
    <title>{{ $title ?? __('Find a doctor') }} — {{ $product }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter+Tight:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/marketing.css') }}">
    <link rel="stylesheet" href="{{ asset('css/card-grid.css') }}">
    <link rel="stylesheet" href="{{ asset('css/patient-find.css') }}">
    <link rel="icon" href="{{ asset('icons/health-favicon.svg') }}">
</head>
<body class="mk pf">
    <header class="mk-nav">
        <div class="mk-wrap mk-nav-inner">
            <a class="mk-nav-brand" href="/">
                <span class="mk-logo-mark">CQ</span>
                <span>{{ $product }}</span>
            </a>
            <nav class="mk-nav-links pf-nav-links" aria-label="{{ __('Find a doctor') }}">
                <a href="/find">{{ __('Find a doctor') }}</a>
                @if($patient)
                    <a href="/me">{{ __('My serials') }}</a>
                    <a href="/me/history">{{ __('History') }}</a>
                @else
                    <a href="/me/login">{{ __('Patient login') }}</a>
                @endif
            </nav>
            <div class="pf-nav-end">
                <a class="mk-nav-find" href="/find">{{ __('Find a doctor') }}</a>
                <a class="pf-lang" href="/lang/{{ $locale === 'bn' ? 'en' : 'bn' }}">{{ $locale === 'bn' ? 'EN' : 'বাং' }}</a>
                @if($patient)
                    <form method="POST" action="/me/logout" class="pf-logout">
                        @csrf
                        <button type="submit">{{ __('Log out') }}</button>
                    </form>
                @else
                    <a class="mk-nav-find" href="/me/login">{{ __('Patient login') }}</a>
                @endif
            </div>
        </div>
    </header>

    <main class="pf-main">
        <div class="mk-wrap">
            @if(session('status'))
                <p class="pf-flash" role="status">{{ session('status') }}</p>
            @endif
            {{ $slot }}
        </div>
    </main>
</body>
</html>
