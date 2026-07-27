@php
    $ratio = $data['aspect_ratio'] ?? '16:9';
    $aspectClass = match($ratio) {
        '5:4' => 'aspect-[5/4]',
        '4:3' => 'aspect-[4/3]',
        '1:1' => 'aspect-square',
        '21:9' => 'aspect-[21/9]',
        default => 'aspect-[16/9]',
    };
    $items = $data['items'] ?? [];
@endphp

<section class="w-full py-12 md:py-16 bg-slate-50/50">
    <div class="max-w-[1320px] mx-auto px-4 md:px-6 xl:px-8">
        @if(!empty($data['heading']))
            <h2 class="text-2xl sm:text-3xl font-bold text-slate-900 tracking-tight text-center mb-8">
                {{ $data['heading'] }}
            </h2>
        @endif

        @if(!empty($items))
            <div x-data="{ 
                    active: 0, 
                    total: {{ count($items) }},
                    next() { this.active = (this.active + 1) % this.total },
                    prev() { this.active = (this.active - 1 + this.total) % this.total }
                 }" 
                 x-init="setInterval(() => next(), 5000)"
                 class="relative w-full rounded-3xl overflow-hidden shadow-lg border border-slate-200/80 bg-slate-900 group">
                
                {{-- Carousel Slides --}}
                <div class="relative w-full {{ $aspectClass }} overflow-hidden">
                    @foreach($items as $index => $item)
                        <div x-show="active === {{ $index }}" 
                             x-transition:enter="transition ease-out duration-500"
                             x-transition:enter-start="opacity-0 scale-95"
                             x-transition:enter-end="opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-300"
                             x-transition:leave-start="opacity-100 scale-100"
                             x-transition:leave-end="opacity-0 scale-95"
                             class="absolute inset-0 w-full h-full">
                            
                            @php $slideLink = \App\Support\SafeUrl::href($item['link_url'] ?? '', ''); @endphp
                            @if($slideLink !== '')
                                <a href="{{ $slideLink }}" class="block w-full h-full">
                            @endif

                            <img src="{{ $item['image_url'] }}" alt="{{ $item['title'] ?? 'Slide' }}" class="w-full h-full object-cover">

                            @if(!empty($item['title']) || !empty($item['description']))
                                <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-slate-950/90 via-slate-950/40 to-transparent p-6 sm:p-8 text-white">
                                    @if(!empty($item['title']))
                                        <h3 class="text-lg sm:text-xl font-bold mb-1">{{ $item['title'] }}</h3>
                                    @endif
                                    @if(!empty($item['description']))
                                        <p class="text-xs sm:text-sm text-slate-200 max-w-xl">{{ $item['description'] }}</p>
                                    @endif
                                </div>
                            @endif

                            @if($slideLink !== '')
                                </a>
                            @endif
                        </div>
                    @endforeach
                </div>

                {{-- Previous Button --}}
                <button @click="prev()" aria-label="Previous Slide" 
                        class="absolute left-4 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full bg-white/80 text-slate-900 flex items-center justify-center shadow-md hover:bg-white transition-all opacity-0 group-hover:opacity-100">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </button>

                {{-- Next Button --}}
                <button @click="next()" aria-label="Next Slide" 
                        class="absolute right-4 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full bg-white/80 text-slate-900 flex items-center justify-center shadow-md hover:bg-white transition-all opacity-0 group-hover:opacity-100">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </button>

                {{-- Dot Indicators --}}
                <div class="absolute bottom-3 left-1/2 -translate-x-1/2 flex items-center gap-2 z-20">
                    @foreach($items as $index => $item)
                        <button @click="active = {{ $index }}" 
                                :class="active === {{ $index }} ? 'bg-sky-400 w-6' : 'bg-white/50 w-2 hover:bg-white'" 
                                class="h-2 rounded-full transition-all duration-300"></button>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</section>
