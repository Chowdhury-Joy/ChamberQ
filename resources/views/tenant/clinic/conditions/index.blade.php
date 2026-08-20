@extends('tenant.clinic.layout')

@section('content')
    <section class="space-section" data-reveal-section>
        <div class="layout-container">
            <div class="stack-header">
                <div class="eyebrow" data-reveal-block data-reveal-kind="fade">{{ __('Conditions') }}</div>
                <h1 class="fx-heading" data-fx-words data-reveal-block data-reveal-kind="heading">{{ __('Conditions we treat') }}</h1>
            </div>
            <ul class="why-grid grid-cards" data-card-count="{{ count($topics) }}" data-reveal-block data-reveal-kind="stagger">
                @foreach($topics as $topic)
                    <li>
                        <a class="why-card" href="{{ tenant_safe_href(null, '/conditions/'.$topic['slug']) }}">
                            <h2>{{ $topic['name'] }}</h2>
                            @if($topic['description'] !== '')
                                <p>{{ $topic['description'] }}</p>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>
            <p style="margin-top:2rem;">
                <a class="btn-pink sm" href="{{ tenant_safe_href(null, '/book') }}"><span>{{ __('Book appointment') }}</span></a>
            </p>
        </div>
    </section>
@endsection
