<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="color-scheme" content="light only">
    <title>{{ $title }} — ChamberQ</title>
    <link rel="stylesheet" href="{{ asset('css/marketing.css') }}">
    <link rel="icon" href="{{ asset('icons/health-favicon.svg') }}">
    <style>
        .mk-error {
            padding: 4.5rem 0 6rem;
            text-align: center;
        }
        .mk-error h1 {
            margin: 0 0 0.85rem;
            font-size: clamp(2rem, 5.5vw, 3.15rem);
            font-weight: 600;
            letter-spacing: -0.04em;
            line-height: 1.05;
        }
        .mk-error p {
            margin: 0 auto 1.5rem;
            max-width: 28rem;
            color: var(--mk-muted);
        }
        .mk-error-actions {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 0.85rem 1rem;
        }
    </style>
</head>
<body class="mk">
    <header class="mk-nav">
        <div class="mk-wrap mk-nav-inner">
            <a class="mk-nav-brand" href="{{ url('/') }}">ChamberQ</a>
            <nav class="mk-nav-links" aria-label="Primary">
                <a href="{{ url('/find') }}">{{ __('Find a doctor') }}</a>
                <a href="{{ url('/me/login') }}">{{ __('Patient login') }}</a>
            </nav>
        </div>
    </header>
    <main class="mk-error" role="main">
        <div class="mk-wrap">
            <p class="mk-kicker"><span></span> {{ $code }}</p>
            <h1>{{ $heading }}</h1>
            <p>{{ $message }}</p>
            <div class="mk-error-actions">
                <a class="mk-btn mk-btn-primary" href="{{ url('/') }}">Back to ChamberQ</a>
                <a class="mk-link-quiet" href="{{ url('/find') }}">{{ __('Find a doctor') }}</a>
            </div>
        </div>
    </main>
</body>
</html>
