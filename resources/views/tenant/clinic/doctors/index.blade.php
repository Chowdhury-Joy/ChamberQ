@extends('tenant.clinic.layout')

@section('content')
    <section class="space-section" data-reveal-section>
        <div class="layout-container">
            <div class="stack-header">
                <div class="eyebrow" data-reveal-block data-reveal-kind="fade">{{ __('Our doctors') }}</div>
                <h1 class="fx-heading" data-fx-words data-reveal-block data-reveal-kind="heading">{{ __('Meet the team') }}</h1>
            </div>

            <div class="doc-grid doc-grid--team grid-cards" data-card-count="{{ $doctors->count() }}" data-reveal-block data-reveal-kind="fade">
                @foreach($doctors as $doctor)
                    <a class="doc-card @if(blank($doctor->photo_url)) doc-card--initial @endif" href="{{ tenant_web_url('/doctors/'.$doctor->public_slug) }}">
                        @if(filled($doctor->photo_url))
                            <img src="{{ $doctor->photo_url }}" alt="{{ $doctor->name }}">
                        @else
                            <div class="doc-initial" aria-hidden="true">{{ mb_strtoupper(mb_substr($doctor->name, 0, 1)) }}</div>
                        @endif
                        <div class="meta">
                            <h2>{{ $doctor->name }}</h2>
                            <span>{{ $doctor->websiteSpecialtyLabel() }}</span>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    </section>
@endsection
