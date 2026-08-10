@extends('tenant.clinic.layout')

@section('content')
    <section class="space-section" data-reveal-section>
        <div class="layout-container">
            <div class="stack-header">
                <div class="eyebrow" data-reveal-block data-reveal-kind="fade">{{ __('Our services') }}</div>
                <h1 class="fx-heading" data-fx-words data-reveal-block data-reveal-kind="heading">{{ __('Departments') }}</h1>
            </div>

            <div class="blog-grid grid-cards" data-card-count="{{ $departments->count() }}" data-reveal-block data-reveal-kind="fade">
                @foreach($departments as $department)
                    <a class="blog-card" href="{{ tenant_web_url('/departments/'.$department->slug) }}">
                        @if(filled($department->image_url))
                            <img src="{{ $department->image_url }}" alt="{{ $department->title }}">
                        @endif
                        <div class="body">
                            <h2>{{ $department->title }}</h2>
                            @if(filled($department->excerpt))
                                <p>{{ $department->excerpt }}</p>
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    </section>
@endsection
