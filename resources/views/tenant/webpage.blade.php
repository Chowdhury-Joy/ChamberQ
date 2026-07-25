<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0ea5e9">
    <title>{{ $page->title }} | {{ tenant('id') }}</title>
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="stylesheet" href="/css/theme.css">
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw.js');
            });
        }
    </script>
</head>
<body>
    <nav class="navbar">
        <a href="/" class="navbar-brand">{{ tenant('id') }}</a>
        <div class="navbar-nav">
            <a href="/">{{ __('Home') }}</a>
            <a href="/lang/en" style="margin-left: 1rem; font-size: 0.8rem;">EN</a>
            <a href="/lang/bn" style="margin-left: 0.5rem; font-size: 0.8rem;">BN</a>
            <a href="/book" class="btn btn-primary" style="margin-left: 1.5rem;">{{ __('Book Appointment') }}</a>
        </div>
    </nav>

    <main>
        @foreach ($page->content ?? [] as $block)
            @switch($block['type'])
                @case('hero')
                    <section class="hero">
                        <div class="hero-content">
                            <h1>{{ $block['data']['headline'] }}</h1>
                            @if(!empty($block['data']['subheadline']))
                                <p>{{ $block['data']['subheadline'] }}</p>
                            @endif
                            @if(!empty($block['data']['cta_text']))
                                <a href="{{ $block['data']['cta_link'] ?? '/book' }}" class="btn btn-primary">{{ $block['data']['cta_text'] }}</a>
                            @endif
                        </div>
                    </section>
                    @break

                @case('rich_text')
                    <section class="section">
                        <div class="rich-text-content">
                            {!! $block['data']['content'] !!}
                        </div>
                    </section>
                    @break

                @case('doctors_list')
                    <section class="section">
                        <h2 class="section-heading">{{ __($block['data']['heading']) }}</h2>
                        <div class="grid">
                            @foreach(App\Models\Doctor::all() as $doctor)
                                <div class="card">
                                    <h3>{{ $doctor->name }}</h3>
                                    <p>{{ __('Specialist at') }} {{ tenant('id') }}</p>
                                </div>
                            @endforeach
                        </div>
                    </section>
                    @break

                @case('services')
                    <section class="section">
                        <h2 class="section-heading">{{ __($block['data']['heading']) }}</h2>
                        <div class="grid">
                            @foreach($block['data']['items'] ?? [] as $item)
                                <div class="card">
                                    @if(!empty($item['icon']))
                                        <div class="icon">{{ $item['icon'] }}</div>
                                    @endif
                                    <h3>{{ $item['title'] }}</h3>
                                    <p>{{ $item['description'] ?? '' }}</p>
                                </div>
                            @endforeach
                        </div>
                    </section>
                    @break
            @endswitch
        @endforeach
    </main>
</body>
</html>
