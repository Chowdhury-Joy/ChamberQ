<section id="services" class="w-full py-12 md:py-16 bg-slate-50/50">
    <div class="max-w-[1320px] mx-auto px-4 md:px-6 xl:px-8">
        @if(!empty($data['heading']))
            <div class="text-center max-w-2xl mx-auto mb-10">
                <h2 class="text-2xl sm:text-3xl font-bold text-slate-900 tracking-tight mb-3">
                    {{ $data['heading'] }}
                </h2>
                @if(!empty($data['description']))
                    <p class="text-sm sm:text-base text-slate-600">{{ $data['description'] }}</p>
                @endif
            </div>
        @endif

        @php $items = $data['items'] ?? []; @endphp

        @if(count($items) < 8)
            {{-- Render as Cards Grid --}}
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($items as $item)
                    <div class="bg-white rounded-2xl p-6 border border-slate-200/80 shadow-sm hover:shadow-lg transition-all">
                        <div class="w-10 h-10 rounded-lg bg-sky-50 text-sky-600 flex items-center justify-center font-bold text-xl mb-4">
                            🩺
                        </div>
                        <h3 class="text-lg font-bold text-slate-900 mb-2">{{ $item['title'] }}</h3>
                        <p class="text-sm text-slate-600 leading-relaxed">{{ $item['description'] ?? '' }}</p>
                    </div>
                @endforeach
            </div>
        @else
            {{-- Render as Clean List Layout --}}
            <div class="bg-white rounded-2xl border border-slate-200/80 p-6 md:p-8 shadow-sm">
                <ul class="divide-y divide-slate-100">
                    @foreach($items as $item)
                        <li class="py-4 first:pt-0 last:pb-0 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                            <div>
                                <h3 class="text-base font-bold text-slate-900">{{ $item['title'] }}</h3>
                                @if(!empty($item['description']))
                                    <p class="text-sm text-slate-500 mt-1">{{ $item['description'] }}</p>
                                @endif
                            </div>
                            <a href="/book" class="inline-flex items-center gap-1 text-sm font-semibold text-sky-600 hover:text-sky-700 whitespace-nowrap">
                                <span>Book Service</span>
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</section>
