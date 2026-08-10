@extends('tenant.clinic.layout')

@section('content')
    <section class="space-section" data-reveal-section>
        <div class="layout-container">
            <div class="stack-header">
                @if($post->displayDate() !== '')
                    <div class="blog-meta" data-reveal-block data-reveal-kind="fade">{{ $post->displayDate() }}</div>
                @endif
                <h1 class="fx-heading" data-fx-words data-reveal-block data-reveal-kind="heading">{{ $post->title }}</h1>
            </div>

            @if(filled($post->image_url))
                <img src="{{ $post->image_url }}" alt="{{ $post->title }}" style="width:100%;max-height:420px;object-fit:cover;border-radius:16px;margin-bottom:2rem;" data-reveal-block data-reveal-kind="fade">
            @endif

            @if(filled($post->excerpt))
                <p class="lead" data-reveal-block data-reveal-kind="fade">{{ $post->excerpt }}</p>
            @endif

            @if(filled($post->body))
                <div class="rich-content" data-reveal-block data-reveal-kind="fade">
                    {!! $post->body !!}
                </div>
            @endif
        </div>
    </section>
@endsection
