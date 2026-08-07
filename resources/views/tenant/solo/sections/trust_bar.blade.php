{{--
    Solo copy of the pre-Clireo shared section, pinned here on 2026-08-07.

    The solo shell falls back to `tenant.sections.*` for any block it does not
    override, so porting those blades to the clinic design would have changed
    the LOCKED solo homepage. This file is the markup solo rendered before the
    port; `tenant/sections/trust_bar.blade.php` is now clinic-only.
    Do not restyle without the owner saying "update patient homepage".
--}}
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
