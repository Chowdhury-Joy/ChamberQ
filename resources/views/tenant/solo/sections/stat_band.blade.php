{{--
    Solo copy of the pre-Clireo shared section, pinned here on 2026-08-07.

    The solo shell falls back to `tenant.sections.*` for any block it does not
    override, so porting those blades to the clinic design would have changed
    the LOCKED solo homepage. This file is the markup solo rendered before the
    port; `tenant/sections/stat_band.blade.php` is now clinic-only.
    Do not restyle without the owner saying "update patient homepage".
--}}
@if(tenant()?->isClinic())
<section class="w-full py-12 bg-sky-900 text-white">
    <div class="max-w-[1320px] mx-auto px-4 md:px-6 xl:px-8">
        <x-card-grid :count="count($data['stats'] ?? [])" class="gap-6 text-center">
            @foreach($data['stats'] ?? [] as $stat)
                <div class="space-y-1">
                    <p class="text-3xl sm:text-4xl font-extrabold text-sky-300 tracking-tight">{{ $stat['value'] }}</p>
                    <p class="text-xs sm:text-sm font-medium text-sky-100 uppercase tracking-wider">{{ $stat['label'] }}</p>
                </div>
            @endforeach
        </x-card-grid>
    </div>
</section>
@endif
