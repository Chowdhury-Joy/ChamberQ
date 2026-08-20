@extends('tenant.clinic.layout')

@section('content')
    <section class="space-section" data-reveal-section>
        <div class="layout-container">
            <div class="stack-header">
                <div class="eyebrow" data-reveal-block data-reveal-kind="fade">{{ __('Conditions') }}</div>
                <h1 class="fx-heading" data-fx-words data-reveal-block data-reveal-kind="heading">{{ $topic['name'] }}</h1>
            </div>
            @if($topic['description'] !== '')
                <p class="lead" data-reveal-block data-reveal-kind="fade">{{ $topic['description'] }}</p>
            @endif
            @if(count($topic['features']) > 0)
                <p class="cond-label">{{ __('Including:') }}</p>
                <ul class="cond-features">
                    @foreach($topic['features'] as $feature)
                        <li>{{ $feature }}</li>
                    @endforeach
                </ul>
            @endif
            <p style="margin-top:2rem;">
                <a class="btn-pink sm" href="{{ tenant_safe_href(null, '/book') }}"><span>{{ __('Book appointment') }}</span></a>
            </p>
        </div>
    </section>
@endsection
