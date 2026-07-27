@php
    $image = $data['image_url'] ?? 'https://images.unsplash.com/photo-1612349317150-e413f6a5b16d?auto=format&fit=crop&w=1200&q=80';
    $headline = $data['headline'] ?? __('Expert care for your health');
    $credentials = $data['credentials'] ?? null;
    $roleLocation = $data['role_location'] ?? null;
    $subheadline = $data['subheadline'] ?? null;
    $ctaText = $data['cta_text'] ?? __('Book Appointment');
    $ctaLink = \App\Support\SafeUrl::href($data['cta_link'] ?? '/book', '/book');
@endphp

<section class="solo-section w-full bg-white" aria-label="{{ __('Introduction') }}">
    <div class="mx-auto grid max-w-[1280px] gap-8 px-3 sm:px-10 lg:grid-cols-2 lg:gap-10">
        <div class="solo-fade-up flex flex-col justify-between gap-8 lg:min-h-[584px]">
            <div>
                <h1 class="font-display text-[2.35rem] leading-[1.05] tracking-tight text-slate-900 sm:text-5xl lg:text-[5.5rem] lg:leading-[0.98]">
                    {{ $headline }}
                </h1>

                <div class="mt-6 flex flex-col gap-3 text-base text-slate-800 sm:mt-10 sm:flex-row sm:flex-wrap sm:gap-x-10 sm:gap-y-2 sm:text-lg lg:mt-14">
                    @if(filled($credentials))
                        <p class="font-medium">{{ $credentials }}</p>
                    @elseif(filled($subheadline))
                        <p class="max-w-xl leading-relaxed text-slate-600">{{ $subheadline }}</p>
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

        <div class="solo-fade-up-delay overflow-hidden rounded-none bg-slate-100 lg:min-h-[584px]">
            <img
                src="{{ $image }}"
                alt="{{ $headline }}"
                class="h-full min-h-[320px] w-full object-cover object-top sm:min-h-[420px] lg:min-h-[584px]"
            >
        </div>
    </div>
</section>
