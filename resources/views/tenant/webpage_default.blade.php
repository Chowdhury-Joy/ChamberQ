<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0ea5e9">
    <meta name="robots" content="noindex">
    <title>{{ tenant()->displayName() }}</title>
    <link rel="stylesheet" href="/css/theme.css">
    <style>
        .placeholder { max-width: 480px; margin: 4rem auto; padding: 0 1.5rem; text-align: center; }
        .placeholder p { margin: 1rem 0 2rem; }
    </style>
</head>
<body>
    <main class="placeholder">
        <h1>{{ tenant()->displayName() }}</h1>
        <p class="text-muted">{{ __('This website is not published yet, but you can still book an appointment online.') }}</p>
        <a href="{{ tenant_web_url('/book') }}" class="btn btn-primary">{{ __('Book Appointment') }}</a>
    </main>
</body>
</html>
