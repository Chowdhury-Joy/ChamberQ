<section class="w-full py-12 md:py-16 bg-slate-50/50">
    <div class="max-w-[1320px] mx-auto px-4 md:px-6 xl:px-8">
        @if(!empty($data['heading']))
            <h2 class="text-2xl sm:text-3xl font-bold text-slate-900 tracking-tight text-center mb-10">
                {{ $data['heading'] }}
            </h2>
        @endif

        <x-card-grid :count="count($data['conditions'] ?? [])" class="gap-4">
            @foreach($data['conditions'] ?? [] as $condition)
                <div class="bg-white rounded-xl p-5 border border-slate-200/80 shadow-sm hover:border-sky-300 transition-colors">
                    <h3 class="text-base font-bold text-slate-900 mb-1">🏥 {{ $condition['name'] }}</h3>
                    @if(!empty($condition['description']))
                        <p class="text-xs text-slate-600 leading-relaxed">{{ $condition['description'] }}</p>
                    @endif
                </div>
            @endforeach
        </x-card-grid>
    </div>
</section>
