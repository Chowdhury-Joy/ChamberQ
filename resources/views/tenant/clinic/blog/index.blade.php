@extends('tenant.clinic.layout')

@section('content')
    <section class="space-section" data-reveal-section>
        <div class="layout-container">
            <div class="stack-header">
                <div class="eyebrow" data-reveal-block data-reveal-kind="fade">{{ __('Health tips') }}</div>
                <h1 class="fx-heading" data-fx-words data-reveal-block data-reveal-kind="heading">{{ __('Latest articles') }}</h1>
            </div>

            <div class="blog-grid grid-cards" data-card-count="{{ $posts->count() }}" data-reveal-block data-reveal-kind="fade">
                @foreach($posts as $post)
                    <a class="blog-card" href="{{ tenant_web_url('/blog/'.$post->slug) }}">
                        @if(filled($post->image_url))
                            <img src="{{ $post->image_url }}" alt="{{ $post->title }}">
                        @endif
                        <div class="body">
                            @if($post->displayDate() !== '')
                                <div class="blog-meta">{{ $post->displayDate() }}</div>
                            @endif
                            <h2>{{ $post->title }}</h2>
                            @if(filled($post->excerpt))
                                <p>{{ $post->excerpt }}</p>
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    </section>
@endsection
