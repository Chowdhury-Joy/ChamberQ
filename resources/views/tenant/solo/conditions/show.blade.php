@extends('tenant.solo.layouts.inner')

@section('content')
    <p class="text-sm text-slate-500">
        <a href="{{ tenant_safe_href(null, '/conditions') }}">{{ __('Conditions we treat') }}</a>
    </p>
    <h1 class="mt-2 font-display text-4xl tracking-tight text-slate-900">{{ $topic['name'] }}</h1>
    @if($topic['description'] !== '')
        <p class="mt-4 text-base leading-relaxed text-slate-600">{{ $topic['description'] }}</p>
    @endif
    @if(count($topic['features']) > 0)
        <p class="mt-8 text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">{{ __('Including:') }}</p>
        <ul class="mt-3 flex flex-col gap-2">
            @foreach($topic['features'] as $feature)
                <li class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-slate-800">{{ $feature }}</li>
            @endforeach
        </ul>
    @endif
    <p class="mt-10">
        <a class="solo-cta" href="{{ tenant_safe_href(null, '/book') }}">{{ __('Book Appointment') }}</a>
    </p>
@endsection
