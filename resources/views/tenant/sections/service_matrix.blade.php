@php
    /*
     * 1:1 from public/previews/clireo-homepage.html #treatments.
     */
    $heading = $data['heading'] ?? 'Expert Physiotherapy For Every Recovery Need';
    $footerText = $data['footer_text'] ?? 'Explore evidence-based rehabilitation programs tailored to every recovery goal.';
    $viewAllText = preg_replace('/\s*[→➜➔]\s*$/u', '', (string) ($data['view_all_text'] ?? 'View all services')) ?: 'View all services';
    $viewAllLink = tenant_safe_href($data['view_all_link'] ?? '/book', '/book');
    $items = $data['items'] ?? [];
    $bookHref = tenant_safe_href(null, '/book');
@endphp

<section class="space-section" id="treatments" data-reveal-section>
    <div class="layout-container">
        <div class="treat-head">
            <div class="stack-header">
                <div class="eyebrow" data-reveal-block data-reveal-kind="fade">{{ __('Our services') }}</div>
                <h2 class="fx-heading" data-fx-words data-reveal-block data-reveal-kind="heading">{{ $heading }}</h2>
            </div>
            <a class="btn-pink sm btn-on-dark" href="{{ $viewAllLink }}" @if(str_starts_with($viewAllLink, 'http')) target="_blank" rel="noopener noreferrer" @endif data-reveal-block data-reveal-kind="fade">
                <span>{{ $viewAllText }}</span>
            </a>
        </div>

        <div class="treat-stage" data-reveal-block data-reveal-kind="fade">
            <div class="treat-scroller" data-treat-scroll>
                @foreach($items as $item)
                    <a class="treat-card" href="{{ $bookHref }}">
                        <div class="media">
                            @if(!empty($item['image_url']))
                                <img class="cover" src="{{ $item['image_url'] }}" alt="{{ $item['title'] ?? '' }}">
                            @endif
                        </div>
                        <div class="body">
                            @if(!empty($item['icon_url']))
                                <span class="icon"><img src="{{ $item['icon_url'] }}" alt=""></span>
                            @endif
                            <h3>{{ $item['title'] ?? '' }}</h3>
                            @if(!empty($item['description']))
                                <p>{{ $item['description'] }}</p>
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>
        </div>

        <div class="treat-nav" data-reveal-block data-reveal-kind="fade">
            <p>{{ $footerText }}</p>
            <div class="treat-arrows">
                <button class="arrow-btn" type="button" data-treat-prev aria-label="{{ __('Previous') }}">←</button>
                <button class="arrow-btn" type="button" data-treat-next aria-label="{{ __('Next') }}">→</button>
            </div>
        </div>
    </div>
</section>
