<section class="w-full bg-slate-50/50 py-12 md:py-16">
    <div class="max-w-[1320px] mx-auto px-4 md:px-6 xl:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 md:gap-12 items-center">
            <div class="space-y-6 text-left">
                @if(!empty($data['emergency_phone']))
                    <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs md:text-sm font-medium bg-red-50 text-red-700 border border-red-200">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                        <span>Emergency 24/7: <strong>{{ $data['emergency_phone'] }}</strong></span>
                    </div>
                @endif

                <h1 class="text-3xl sm:text-4xl md:text-5xl font-extrabold text-slate-900 tracking-tight leading-tight">
                    {{ $data['headline'] ?? 'Expert Care for Your Health & Well-being' }}
                </h1>

                @if(!empty($data['subheadline']))
                    <p class="text-base sm:text-lg text-slate-600 leading-relaxed max-w-xl">
                        {{ $data['subheadline'] }}
                    </p>
                @endif

                <div class="flex flex-wrap gap-4 pt-2">
                    <a href="{{ $data['cta_link'] ?? '/book' }}" class="btn btn-primary inline-flex items-center gap-2 shadow-lg shadow-sky-500/25">
                        <span>{{ $data['cta_text'] ?? 'Book Appointment' }}</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                    </a>
                    @if(!empty($data['secondary_cta_text']))
                        <a href="{{ $data['secondary_cta_link'] ?? '#services' }}" class="btn border border-slate-300 text-slate-700 hover:bg-slate-100">
                            {{ $data['secondary_cta_text'] }}
                        </a>
                    @endif
                </div>
            </div>

            <div class="relative w-full flex justify-center">
                <div class="w-full max-w-lg aspect-[5/4] rounded-2xl overflow-hidden shadow-xl border border-slate-200/80 bg-slate-200">
                    <img src="{{ $data['image_url'] ?? 'https://images.unsplash.com/photo-1629909613654-28e377c37b09?auto=format&fit=crop&w=1200&q=80' }}" 
                         alt="{{ $data['headline'] ?? 'Healthcare' }}" 
                         class="w-full h-full object-cover">
                </div>
            </div>
        </div>
    </div>
</section>
