@php
    $tenant = $tenant ?? tenant();
    $brand = $tenant?->displayName() ?? '';
    $image = $data['image_url'] ?? 'https://images.unsplash.com/photo-1631217868264-e5b90bb7e133?auto=format&fit=crop&w=1800&q=80';
    $headline = $data['headline'] ?? __('Expert care for your health');
    $subheadline = $data['subheadline'] ?? __('Book a serial online and follow the live queue from your phone.');
    $ctaText = $data['cta_text'] ?? __('Book Appointment');
    $ctaLink = \App\Support\SafeUrl::href($data['cta_link'] ?? '/book', '/book');
    $secondaryText = $data['secondary_cta_text'] ?? null;
    $secondaryLink = \App\Support\SafeUrl::href($data['secondary_cta_link'] ?? '', '');
    $emergency = $data['emergency_phone'] ?? null;
@endphp

<section class="relative isolate min-h-[70vh] overflow-hidden bg-slate-800 sm:min-h-[78vh] lg:min-h-[85vh]" aria-label="{{ __('Introduction') }}">
    <img
        src="{{ $image }}"
        alt=""
        class="absolute inset-0 -z-20 h-full w-full object-cover"
    >
    {{-- Light readable wash — not a dark-mode theme --}}
    <div class="absolute inset-0 -z-10 bg-gradient-to-r from-slate-900/70 via-slate-900/45 to-slate-900/25"></div>
    <div class="absolute inset-0 -z-10 bg-gradient-to-t from-slate-900/50 via-transparent to-slate-900/20"></div>

    <div class="mx-auto flex min-h-[70vh] max-w-[1320px] flex-col justify-end px-4 pb-12 pt-28 sm:min-h-[78vh] sm:px-6 sm:pb-16 sm:pt-32 lg:min-h-[85vh] lg:px-8 lg:pb-20">
        <div class="max-w-2xl animate-[fadeUp_0.7s_ease-out_both]" style="color:#fff;">
            @if(filled($brand))
                <p class="mb-3 text-xs font-semibold uppercase tracking-[0.14em] sm:mb-4 sm:text-sm" style="color:rgba(255,255,255,0.85);">
                    {{ $brand }}
                </p>
            @endif

            <h1 class="text-3xl font-extrabold leading-[1.1] tracking-tight sm:text-4xl md:text-5xl lg:text-[3.25rem]" style="color:#fff;">
                {{ $headline }}
            </h1>

            @if(filled($subheadline))
                <p class="mt-4 max-w-xl text-base leading-relaxed sm:mt-5 sm:text-lg md:text-xl" style="color:rgba(255,255,255,0.92);">
                    {{ $subheadline }}
                </p>
            @endif

            <div class="mt-8 flex flex-wrap items-center gap-3 sm:mt-10">
                <a
                    href="{{ $ctaLink }}"
                    class="inline-flex items-center justify-center gap-2 rounded-xl px-5 py-3 text-sm font-semibold shadow-lg shadow-slate-900/25 transition hover:opacity-90 sm:px-6 sm:py-3.5 sm:text-base"
                    style="background-color: var(--color-primary); color:#fff;"
                >
                    <span>{{ $ctaText }}</span>
                    <svg class="h-4 w-4 sm:h-5 sm:w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                </a>
                @if(filled($secondaryText) && filled($secondaryLink))
                    <a
                        href="{{ $secondaryLink }}"
                        class="inline-flex items-center justify-center rounded-xl border border-white/40 bg-white px-5 py-3 text-sm font-semibold text-slate-900 transition hover:bg-slate-50 sm:px-6 sm:py-3.5 sm:text-base"
                    >
                        {{ $secondaryText }}
                    </a>
                @endif
            </div>
        </div>
    </div>
</section>

@if(filled($emergency))
    <div class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-[1320px] flex-wrap items-center gap-x-3 gap-y-1 px-4 py-3 text-sm text-slate-600 sm:px-6 sm:text-[0.95rem] lg:px-8">
            <span>{{ __('Emergency hotline') }}:</span>
            <a href="tel:{{ preg_replace('/\s+/', '', $emergency) }}" class="font-semibold text-slate-900 hover:text-brand">{{ $emergency }}</a>
        </div>
    </div>
@endif

<style>
    @keyframes fadeUp {
        from { opacity: 0; transform: translateY(14px); }
        to { opacity: 1; transform: translateY(0); }
    }
</style>
