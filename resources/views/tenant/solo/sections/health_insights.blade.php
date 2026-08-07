{{--
    Solo copy of the pre-Clireo shared section, pinned here on 2026-08-07.

    The solo shell falls back to `tenant.sections.*` for any block it does not
    override, so porting those blades to the clinic design would have changed
    the LOCKED solo homepage. This file is the markup solo rendered before the
    port; `tenant/sections/health_insights.blade.php` is now clinic-only.
    Do not restyle without the owner saying "update patient homepage".
--}}
<section class="w-full py-12 md:py-16 bg-white">
    <div class="max-w-[1320px] mx-auto px-4 md:px-6 xl:px-8">
        @if(!empty($data['heading']))
            <h2 class="text-2xl sm:text-3xl font-bold text-slate-900 tracking-tight text-center mb-10">
                {{ $data['heading'] }}
            </h2>
        @endif

        <x-card-grid :count="count($data['articles'] ?? [])" class="gap-6">
            @foreach($data['articles'] ?? [] as $article)
                <div class="bg-slate-50 rounded-2xl overflow-hidden border border-slate-200/80 shadow-sm flex flex-col justify-between">
                    <div>
                        @if(!empty($article['image_url']))
                            <img src="{{ $article['image_url'] }}" alt="{{ $article['title'] }}" class="w-full h-44 object-cover">
                        @endif
                        <div class="p-5">
                            <h3 class="text-base font-bold text-slate-900 mb-2">{{ $article['title'] }}</h3>
                            @if(!empty($article['excerpt']))
                                <p class="text-xs text-slate-600 leading-relaxed line-clamp-3">{{ $article['excerpt'] }}</p>
                            @endif
                        </div>
                    </div>
                    @php $articleLink = \App\Support\SafeUrl::href($article['link'] ?? '', ''); @endphp
                    @if($articleLink !== '')
                        <div class="px-5 pb-5 pt-0">
                            <a href="{{ $articleLink }}" class="text-xs font-bold text-sky-600 hover:text-sky-700 inline-flex items-center gap-1">
                                <span>Read Full Article</span>
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </a>
                        </div>
                    @endif
                </div>
            @endforeach
        </x-card-grid>
    </div>
</section>
