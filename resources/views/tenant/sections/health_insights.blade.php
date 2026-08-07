@php
    /*
     * 1:1 from public/previews/clireo-homepage.html #blog.
     */
    $heading = $data['heading'] ?? 'Latest Physiotherapy Tips & Insights';
    $viewAllText = preg_replace('/\s*[→➜➔]\s*$/u', '', (string) ($data['view_all_text'] ?? 'View all posts')) ?: 'View all posts';
    $viewAllLink = \App\Support\SafeUrl::href($data['view_all_link'] ?? '', '');
    $articles = $data['articles'] ?? [];
@endphp

<section class="space-section" id="blog" data-reveal-section>
    <div class="layout-container">
        <div class="treat-head">
            <div class="stack-header">
                <div class="eyebrow" data-reveal-block data-reveal-kind="fade">{{ __('Health tips') }}</div>
                <h2 class="fx-heading" data-fx-words data-reveal-block data-reveal-kind="heading">{{ $heading }}</h2>
            </div>
            @if($viewAllLink !== '')
                <a class="btn-pink sm" href="{{ $viewAllLink }}" target="_blank" rel="noopener noreferrer" data-reveal-block data-reveal-kind="fade">
                    <span>{{ $viewAllText }}</span>
                </a>
            @endif
        </div>

        <div class="blog-grid grid-cards" data-card-count="{{ count($articles) }}" data-reveal-block data-reveal-kind="fade">
            @foreach($articles as $article)
                @php $articleLink = \App\Support\SafeUrl::href($article['link'] ?? '', '#'); @endphp
                <a class="blog-card" href="{{ $articleLink }}" @if($articleLink !== '#' && str_starts_with($articleLink, 'http')) target="_blank" rel="noopener noreferrer" @endif>
                    @if(!empty($article['image_url']))
                        <img src="{{ $article['image_url'] }}" alt="{{ $article['title'] ?? '' }}">
                    @endif
                    <div class="body">
                        @if(!empty($article['meta']))
                            <div class="blog-meta">{{ $article['meta'] }}</div>
                        @endif
                        <h3>{{ $article['title'] ?? '' }}</h3>
                    </div>
                </a>
            @endforeach
        </div>
    </div>
</section>
