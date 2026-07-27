@php
    $heading = $data['heading'] ?? __('What My Patients Say About My Treatments');
    $items = $data['items'] ?? [];
@endphp

<section class="w-full bg-black text-white">
    <div class="mx-auto max-w-[1280px] px-3 py-14 sm:px-10 sm:py-16 lg:py-20">
        <div class="hidden gap-6 lg:grid lg:grid-cols-3">
            <div class="p-2">
                <h2 class="font-display text-[2.35rem] leading-[1.1] tracking-tight">
                    {{ $heading }}
                </h2>
            </div>
            @foreach($items as $index => $item)
                <article class="flex min-h-[22rem] flex-col justify-between rounded-2xl bg-[#1a1a1a] p-8">
                    <p class="text-base leading-relaxed text-white/90">{{ $item['quote'] ?? '' }}</p>
                    <div class="mt-8">
                        <p class="text-sm font-semibold uppercase tracking-wide">{{ $item['name'] ?? '' }}</p>
                        <p class="mt-1 text-sm text-white/50">{{ $item['label'] ?? __('Verified Patient') }}</p>
                    </div>
                </article>
            @endforeach
        </div>

        <div class="lg:hidden">
            <h2 class="font-display text-3xl leading-tight tracking-tight">
                {{ $heading }}
            </h2>
            <div class="mt-8 flex gap-4 overflow-x-auto pb-2">
                @foreach($items as $item)
                    <article class="flex w-[85%] shrink-0 flex-col justify-between rounded-2xl bg-[#1a1a1a] p-6 sm:w-[70%]">
                        <p class="text-sm leading-relaxed text-white/90 sm:text-base">{{ $item['quote'] ?? '' }}</p>
                        <div class="mt-8">
                            <p class="text-sm font-semibold uppercase tracking-wide">{{ $item['name'] ?? '' }}</p>
                            <p class="mt-1 text-sm text-white/50">{{ $item['label'] ?? __('Verified Patient') }}</p>
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    </div>
</section>
