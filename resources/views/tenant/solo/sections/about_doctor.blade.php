@php
    $heading = $data['heading'] ?? __('Meet Your Doctor');
    $subheadline = $data['subheadline'] ?? '';
    $ctaText = $data['cta_text'] ?? __('Book Appointment');
    $ctaLink = \App\Support\SafeUrl::href($data['cta_link'] ?? '/book', '/book');
    $highlights = $data['highlights'] ?? [];
@endphp

<section id="about" class="w-full bg-black text-white">
    <div class="mx-auto grid min-h-[85vh] max-w-[1280px] gap-10 px-3 py-14 sm:px-10 sm:py-16 lg:grid-cols-2 lg:gap-12 lg:py-20">
        <div class="flex h-full flex-col justify-between gap-10">
            <div>
                <h2 class="font-display text-4xl leading-[1.05] tracking-tight sm:text-5xl lg:text-[3.5rem]">
                    {{ $heading }}
                </h2>
                @if(filled($subheadline))
                    <p class="mt-5 max-w-md text-base leading-relaxed text-white/80 sm:text-lg">
                        {{ $subheadline }}
                    </p>
                @endif
            </div>
            <div>
                <a href="{{ $ctaLink }}" class="solo-cta w-full sm:w-auto">
                    <span>{{ $ctaText }}</span>
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 17L17 7M17 7H7M17 7v10"/>
                    </svg>
                </a>
            </div>
        </div>

        <div class="flex flex-col gap-6 lg:h-full">
            @foreach($highlights as $item)
                <article class="flex flex-col justify-between rounded-2xl bg-[#1a1a1a] p-6 sm:p-7 lg:flex-1">
                    <h3 class="font-display text-2xl text-white sm:text-[1.75rem]">{{ $item['title'] ?? '' }}</h3>
                    @if(!empty($item['description']))
                        <p class="mt-10 text-sm leading-relaxed text-white/75 sm:mt-16 sm:text-base">
                            {{ $item['description'] }}
                        </p>
                    @endif
                </article>
            @endforeach
        </div>
    </div>
</section>
