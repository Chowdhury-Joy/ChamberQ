@extends('tenant.clinic.layout')

@section('content')
    <section class="space-section" data-reveal-section>
        <div class="layout-container">
            <div class="stack-header">
                <div class="eyebrow" data-reveal-block data-reveal-kind="fade">{{ __('Department') }}</div>
                <h1 class="fx-heading" data-fx-words data-reveal-block data-reveal-kind="heading">{{ $department->title }}</h1>
            </div>

            @if(filled($department->image_url))
                <img src="{{ $department->image_url }}" alt="{{ $department->title }}" style="width:100%;max-height:420px;object-fit:cover;border-radius:16px;margin-bottom:2rem;" data-reveal-block data-reveal-kind="fade">
            @endif

            @if(filled($department->excerpt))
                <p class="lead" data-reveal-block data-reveal-kind="fade">{{ $department->excerpt }}</p>
            @endif

            @if(filled($department->body))
                <div class="rich-content" data-reveal-block data-reveal-kind="fade">
                    {!! \App\Support\HtmlSanitizer::clean($department->body) !!}
                </div>
            @endif

            <p style="margin-top:2rem;">
                <a class="btn-pink sm" href="{{ tenant_safe_href(null, '/book') }}"><span>{{ __('Book appointment') }}</span></a>
            </p>
        </div>
    </section>
@endsection
