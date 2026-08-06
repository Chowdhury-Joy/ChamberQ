@php
    $tenant = $tenant ?? tenant();
    $brand = $tenant?->displayName() ?? '';
    $image = $data['image_url'] ?? 'https://images.unsplash.com/photo-1519494026892-80bbd2d6fd0d?auto=format&fit=crop&w=1800&q=80';
    $headline = $data['headline'] ?? __('Expert care for your health');
    $subheadline = $data['subheadline'] ?? __('Book a serial online and follow the live queue from your phone.');
    $ctaText = $data['cta_text'] ?? __('Book Appointment');
    $ctaLink = tenant_safe_href($data['cta_link'] ?? '/book', '/book');
    $secondaryText = $data['secondary_cta_text'] ?? null;
    $secondaryLink = tenant_safe_href($data['secondary_cta_link'] ?? '', '');
    $emergency = $data['emergency_phone'] ?? null;
@endphp

<section class="solo-section-hero relative isolate min-h-[70vh] overflow-hidden bg-slate-800 sm:min-h-[78vh] lg:min-h-[82vh]" aria-label="{{ __('Introduction') }}">
    <img
        src="{{ $image }}"
        alt=""
        class="absolute inset-0 -z-20 h-full w-full object-cover"
    >
    <div class="absolute inset-0 -z-10 bg-gradient-to-r from-slate-900/75 via-slate-900/45 to-slate-900/20"></div>
    <div class="absolute inset-0 -z-10 bg-gradient-to-t from-slate-900/55 via-transparent to-slate-900/15"></div>

    <div class="mx-auto flex min-h-[70vh] max-w-[1280px] flex-col justify-end px-3 pb-12 pt-28 sm:min-h-[78vh] sm:px-10 sm:pb-16 sm:pt-32 lg:min-h-[82vh] lg:pb-20">
        <div class="solo-fade-up max-w-2xl" style="color:#fff;">
            @if(filled($brand))
                <p class="mb-3 text-xs font-semibold uppercase tracking-[0.14em] sm:mb-4 sm:text-sm" style="color:rgba(255,255,255,0.8);">
                    {{ $brand }}
                </p>
            @endif

            <h1 class="font-display text-4xl leading-[1.05] tracking-tight sm:text-5xl lg:text-[4.25rem] lg:leading-[1.02]" style="color:#fff;">
                {{ $headline }}
            </h1>

            @if(filled($subheadline))
                <p class="mt-4 max-w-xl text-base leading-relaxed sm:mt-5 sm:text-lg" style="color:rgba(255,255,255,0.9);">
                    {{ $subheadline }}
                </p>
            @endif

            <div class="solo-fade-up-delay mt-8 flex flex-wrap items-center gap-3 sm:mt-10">
                <a href="{{ $ctaLink }}" class="solo-cta">
                    <span>{{ $ctaText }}</span>
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 17L17 7M17 7H7M17 7v10"/>
                    </svg>
                </a>
                @if(filled($secondaryText) && filled($secondaryLink))
                    <a
                        href="{{ $secondaryLink }}"
                        class="inline-flex items-center justify-center rounded-full border border-white/45 bg-white/10 px-8 py-4 text-[0.95rem] font-semibold text-white backdrop-blur transition hover:bg-white/20"
                    >
                        {{ $secondaryText }}
                    </a>
                @endif
            </div>
        </div>
    </div>
</section>

@if(filled($emergency))
    <div class="border-b border-slate-100 bg-white">
        <div class="mx-auto flex max-w-[1280px] flex-wrap items-center gap-x-3 gap-y-1 px-3 py-3 text-sm text-slate-600 sm:px-10 sm:text-[0.95rem]">
            <span>{{ __('Emergency hotline') }}:</span>
            <a href="tel:{{ preg_replace('/\s+/', '', $emergency) }}" class="font-semibold text-slate-900 hover:text-brand">{{ $emergency }}</a>
        </div>
    </div>
@endif
