@extends('tenant.solo.layouts.inner')

@section('content')
    <h1 class="font-display text-4xl tracking-tight text-slate-900">{{ __('Conditions we treat') }}</h1>
    <p class="mt-3 text-slate-600">{{ $seo['description'] ?? '' }}</p>
    <ul class="mt-8 flex flex-col gap-3">
        @foreach($topics as $topic)
            <li>
                <a class="block rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 text-lg font-medium text-slate-900" href="{{ tenant_safe_href(null, '/conditions/'.$topic['slug']) }}">
                    {{ $topic['name'] }}
                </a>
            </li>
        @endforeach
    </ul>
    <p class="mt-10">
        <a class="solo-cta" href="{{ tenant_safe_href(null, '/book') }}">{{ __('Book Appointment') }}</a>
    </p>
@endsection
