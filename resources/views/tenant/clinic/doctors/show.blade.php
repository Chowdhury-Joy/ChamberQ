@extends('tenant.clinic.layout')

@section('content')
    <section class="space-section" data-reveal-section>
        <div class="layout-container">
            <div class="doc-grid grid-cards" data-card-count="1" style="margin-bottom:2rem;" data-reveal-block data-reveal-kind="fade">
                <article class="doc-card @if(blank($doctor->photo_url)) doc-card--initial @endif">
                    @if(filled($doctor->photo_url))
                        <img src="{{ $doctor->photo_url }}" alt="{{ $doctor->name }}">
                    @else
                        <div class="doc-initial" aria-hidden="true">{{ mb_strtoupper(mb_substr($doctor->name, 0, 1)) }}</div>
                    @endif
                    <div class="meta">
                        <h1>{{ $doctor->name }}</h1>
                        <span>{{ $doctor->websiteSpecialtyLabel() }}</span>
                    </div>
                </article>
            </div>

            @if(filled($doctor->bio))
                <div class="rich-content" data-reveal-block data-reveal-kind="fade">
                    {!! $doctor->bio !!}
                </div>
            @endif

            <p style="margin-top:2rem;">
                <a class="btn-pink sm" href="{{ tenant_safe_href(null, '/book?doctor='.$doctor->id) }}"><span>{{ __('Book with this doctor') }}</span></a>
            </p>
        </div>
    </section>
@endsection
