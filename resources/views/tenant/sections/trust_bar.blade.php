<section class="w-full bg-slate-50 border-y border-slate-200/60 py-4">
    <div class="max-w-[1320px] mx-auto px-4 md:px-6 xl:px-8">
        <div class="flex flex-wrap items-center justify-center gap-3 sm:gap-6 text-xs sm:text-sm font-medium text-slate-700">
            @foreach($data['badges'] ?? [] as $index => $badge)
                @if($index > 0)
                    <span class="text-sky-600 font-bold select-none text-base" aria-hidden="true">🏥</span>
                @endif
                <span class="inline-flex items-center gap-1.5 whitespace-nowrap">
                    {{ $badge['text_badge'] ?? '' }}
                </span>
            @endforeach
        </div>
    </div>
</section>
