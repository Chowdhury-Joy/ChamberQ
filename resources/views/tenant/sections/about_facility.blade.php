@if(tenant()?->isClinic())
<section class="w-full py-12 md:py-16 bg-slate-50/50">
    <div class="max-w-[1320px] mx-auto px-4 md:px-6 xl:px-8">
        @if(!empty($data['heading']))
            <h2 class="text-2xl sm:text-3xl font-bold text-slate-900 tracking-tight text-center mb-6">
                {{ $data['heading'] }}
            </h2>
        @endif

        @if(!empty($data['mission_statement']))
            <p class="text-base sm:text-lg text-slate-600 text-center max-w-3xl mx-auto mb-10 leading-relaxed">
                "{{ $data['mission_statement'] }}"
            </p>
        @endif

        @if(!empty($data['gallery']))
            <x-card-grid :count="count($data['gallery'])" class="gap-6">
                @foreach($data['gallery'] as $item)
                    <div class="bg-white rounded-2xl overflow-hidden border border-slate-200/80 shadow-sm">
                        <img src="{{ $item['image_url'] }}" alt="{{ $item['title'] }}" class="w-full h-48 object-cover">
                        <div class="p-4">
                            <h3 class="text-sm font-bold text-slate-900">{{ $item['title'] }}</h3>
                        </div>
                    </div>
                @endforeach
            </x-card-grid>
        @endif
    </div>
</section>
@endif
