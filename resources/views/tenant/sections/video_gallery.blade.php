@php
    /*
     * Video cards in the Clireo blog-card shape. Thumbnail resolution is
     * unchanged: explicit thumbnail, else the YouTube still, else a generic
     * clinic image.
     */
    $heading = $data['heading'] ?? __('From our clinic');
    $videos = array_slice($data['videos'] ?? [], 0, 10);
@endphp

<section class="space-section" id="videos" data-reveal-section>
    <div class="layout-container">
        <div class="stack-header">
            <div class="eyebrow" data-reveal-block data-reveal-kind="fade">{{ __('Watch') }}</div>
            @if(filled($heading))
                <h2 class="fx-heading" data-fx-words data-reveal-block data-reveal-kind="heading">{{ $heading }}</h2>
            @endif
        </div>

        <div class="blog-grid grid-cards" data-card-count="{{ count($videos) }}" data-reveal-block data-reveal-kind="stagger">
            @foreach($videos as $video)
                @php
                    $url = \App\Support\SafeUrl::href($video['video_url'] ?? '', '');
                    $thumbnail = \App\Support\SafeUrl::href($video['thumbnail_url'] ?? '', '');

                    if ($thumbnail === '' && $url !== '' && (str_contains($url, 'youtube.com') || str_contains($url, 'youtu.be'))) {
                        preg_match('/(?:youtu\.be\/|youtube\.com\/(?:embed\/|v\/|watch\?v=|watch\?.+&v=))([\w-]{11})/', $url, $matches);
                        if (!empty($matches[1])) {
                            $thumbnail = "https://img.youtube.com/vi/{$matches[1]}/hqdefault.jpg";
                        }
                    }

                    if ($thumbnail === '') {
                        $thumbnail = 'https://images.unsplash.com/photo-1576091160399-112ba8d25d1d?auto=format&fit=crop&w=600&q=80';
                    }

                    $title = $video['title'] ?? __('Video');
                @endphp

                @if($url !== '')
                    <a class="blog-card video-card" href="{{ $url }}" target="_blank" rel="noopener noreferrer">
                        <span class="video-thumb">
                            <img src="{{ $thumbnail }}" alt="{{ $title }}">
                            <span class="video-play" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
                            </span>
                        </span>
                        <span class="body"><h3>{{ $title }}</h3></span>
                    </a>
                @else
                    <article class="blog-card video-card">
                        <span class="video-thumb"><img src="{{ $thumbnail }}" alt="{{ $title }}"></span>
                        <span class="body"><h3>{{ $title }}</h3></span>
                    </article>
                @endif
            @endforeach
        </div>
    </div>
</section>
