{{--
    Solo copy of the pre-Clireo shared section, pinned here on 2026-08-07.

    The solo shell falls back to `tenant.sections.*` for any block it does not
    override, so porting those blades to the clinic design would have changed
    the LOCKED solo homepage. This file is the markup solo rendered before the
    port; `tenant/sections/patient_journey.blade.php` is now clinic-only.
    Do not restyle without the owner saying "update patient homepage".
--}}
<section class="w-full py-12 md:py-16 bg-white">
    <div class="max-w-[1320px] mx-auto px-4 md:px-6 xl:px-8">
        @if(!empty($data['heading']))
            <h2 class="text-2xl sm:text-3xl font-bold text-center text-slate-900 mb-10 tracking-tight">
                {{ $data['heading'] }}
            </h2>
        @endif

        @php $steps = $data['steps'] ?? []; @endphp
        <x-card-grid :count="count($steps)" class="gap-6 sm:gap-8">
            @foreach($steps as $step)
                <div class="relative bg-slate-50 rounded-2xl p-6 border border-slate-200/80 shadow-sm hover:shadow-md transition-shadow">
                    <div class="w-12 h-12 rounded-xl bg-sky-500 text-white font-bold text-lg flex items-center justify-center mb-4 shadow-md shadow-sky-500/20">
                        {{ $step['step_number'] ?? '0' . ($loop->index + 1) }}
                    </div>
                    <h3 class="text-lg font-bold text-slate-900 mb-2">{{ $step['title'] ?? '' }}</h3>
                    <p class="text-sm text-slate-600 leading-relaxed">{{ $step['description'] ?? '' }}</p>
                </div>
            @endforeach
        </x-card-grid>
    </div>
</section>
