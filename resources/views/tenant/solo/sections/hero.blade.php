@php
    $image = $data['image_url'] ?? 'https://images.unsplash.com/photo-1612349317150-e413f6a5b16d?auto=format&fit=crop&w=1200&q=80';
    $headline = $data['headline'] ?? __('Expert care for your health');
    $credentials = $data['credentials'] ?? null;
    $roleLocation = $data['role_location'] ?? null;
    $subheadline = $data['subheadline'] ?? null;
    $ctaText = $data['cta_text'] ?? __('Book Appointment');
    $ctaLink = tenant_safe_href($data['cta_link'] ?? '/book', '/book');
@endphp

<section class="solo-section-hero w-full bg-white" aria-label="{{ __('Introduction') }}">
    <div class="mx-auto grid max-w-[1280px] gap-8 px-3 sm:px-10 lg:grid-cols-2 lg:gap-10">
        <div class="solo-fade-up flex flex-col justify-start gap-8 lg:py-2">
            <div class="flex flex-col gap-6 sm:gap-8">
                <h1 class="solo-h1 text-slate-900">
                    {{ $headline }}
                </h1>

                <div class="solo-body-lg flex flex-col gap-2 text-slate-800 sm:gap-3">
                    @if(filled($credentials))
                        <p class="font-medium">{{ $credentials }}</p>
                    @elseif(filled($subheadline))
                        <p class="max-w-xl text-slate-600">{{ $subheadline }}</p>
                    @endif
                    @if(filled($roleLocation))
                        <p class="font-medium">{{ $roleLocation }}</p>
                    @endif
                </div>
            </div>

            <div class="solo-fade-up-delay">
                <a href="{{ $ctaLink }}" class="solo-cta w-full sm:w-auto">
                    <span>{{ $ctaText }}</span>
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 17L17 7M17 7H7M17 7v10"/>
                    </svg>
                </a>
            </div>
        </div>

        <div class="solo-fade-up-delay overflow-hidden rounded-2xl bg-slate-100 lg:min-h-[584px]">
            <img
                src="{{ $image }}"
                alt="{{ $headline }}"
                class="h-full min-h-[320px] w-full object-cover object-top sm:min-h-[420px] lg:min-h-[584px]"
            >
        </div>
    </div>
</section>
