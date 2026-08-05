@php
    $heading = $data['heading'] ?? __('Meet Your Doctor');
    $subheadline = $data['subheadline'] ?? '';
    $ctaText = $data['cta_text'] ?? __('Book Appointment');
    $ctaLink = tenant_safe_href($data['cta_link'] ?? '/book', '/book');
    $highlights = $data['highlights'] ?? [];
@endphp

<section id="about" class="solo-section w-full overflow-visible bg-black text-white">
    <div class="mx-auto grid w-full max-w-[1280px] gap-10 px-3 sm:px-10 lg:grid-cols-2 lg:gap-12 lg:items-stretch">
        <div class="flex h-full flex-col justify-between gap-10">
            <div>
                <h2 class="solo-h2">
                    {{ $heading }}
                </h2>
                @if(filled($subheadline))
                    <p class="solo-body-lg mt-5 max-w-md text-white/80">
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

        <div class="flex flex-col gap-6">
            @foreach($highlights as $item)
                <article class="flex flex-col justify-between rounded-2xl bg-[#1a1a1a] p-6 sm:p-7">
                    <h3 class="solo-h3">{{ $item['title'] ?? '' }}</h3>
                    @if(!empty($item['description']))
                        <p class="solo-body mt-6 text-white/75 sm:mt-8">
                            {{ $item['description'] }}
                        </p>
                    @endif
                </article>
            @endforeach
        </div>
    </div>
</section>
