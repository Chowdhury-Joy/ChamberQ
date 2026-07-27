<section class="w-full py-12 md:py-16 bg-slate-50/50">
    <div class="max-w-[1320px] mx-auto px-4 md:px-6 xl:px-8">
        <div class="bg-white rounded-3xl p-6 md:p-10 border border-slate-200/80 shadow-sm grid grid-cols-1 lg:grid-cols-2 gap-8 items-center">
            <div class="space-y-6">
                <h2 class="text-2xl sm:text-3xl font-bold text-slate-900 tracking-tight">
                    {{ $data['heading'] ?? 'Visit Our Clinic' }}
                </h2>

                @if(!empty($data['address']))
                    <div class="flex items-start gap-3">
                        <div class="w-8 h-8 rounded-lg bg-sky-50 text-sky-600 flex items-center justify-center shrink-0">📍</div>
                        <div>
                            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Address</p>
                            <p class="text-sm font-medium text-slate-800">{{ $data['address'] }}</p>
                        </div>
                    </div>
                @endif

                @if(!empty($data['operating_hours']))
                    <div class="flex items-start gap-3">
                        <div class="w-8 h-8 rounded-lg bg-sky-50 text-sky-600 flex items-center justify-center shrink-0">🕒</div>
                        <div>
                            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Operating Hours</p>
                            <p class="text-sm font-medium text-slate-800">{{ $data['operating_hours'] }}</p>
                        </div>
                    </div>
                @endif

                @if(!empty($data['phone']))
                    <div class="flex items-start gap-3">
                        <div class="w-8 h-8 rounded-lg bg-sky-50 text-sky-600 flex items-center justify-center shrink-0">📞</div>
                        <div>
                            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Phone</p>
                            <p class="text-sm font-medium text-slate-800">{{ $data['phone'] }}</p>
                        </div>
                    </div>
                @endif

                @php $mapsUrl = \App\Support\SafeUrl::href($data['google_maps_url'] ?? '', ''); @endphp
                @if($mapsUrl !== '')
                    <div class="pt-2">
                        <a href="{{ $mapsUrl }}" target="_blank" rel="noopener noreferrer" class="btn btn-primary inline-flex items-center gap-2">
                            <span>Open Google Maps Directions</span>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                        </a>
                    </div>
                @endif
            </div>

            <div class="w-full h-64 lg:h-80 rounded-2xl bg-slate-100 border border-slate-200/80 flex flex-col items-center justify-center p-6 text-center">
                <div class="text-4xl mb-3">🗺️</div>
                <h4 class="text-base font-bold text-slate-800 mb-1">Interactive Map & Directions</h4>
                <p class="text-xs text-slate-500 max-w-xs mb-4">Click the button to open direct navigation on Google Maps.</p>
                @if($mapsUrl !== '')
                    <a href="{{ $mapsUrl }}" target="_blank" rel="noopener noreferrer" class="text-xs font-bold text-sky-600 underline">
                        View Location Link
                    </a>
                @endif
            </div>
        </div>
    </div>
</section>
