<section class="w-full py-12 md:py-16 bg-white">
    <div class="max-w-[1320px] mx-auto px-4 md:px-6 xl:px-8">
        @if(!empty($data['heading']))
            <h2 class="text-2xl sm:text-3xl font-bold text-slate-900 tracking-tight text-center mb-10">
                {{ $data['heading'] }}
            </h2>
        @endif

        <div class="max-w-3xl mx-auto space-y-4">
            @foreach($data['faqs'] ?? [] as $faq)
                <details class="group bg-slate-50 rounded-2xl p-6 border border-slate-200/80 [&_summary::-webkit-details-marker]:none">
                    <summary class="flex items-center justify-between cursor-pointer font-bold text-base md:text-lg text-slate-900">
                        <span>{{ $faq['question'] }}</span>
                        <span class="ml-4 transition group-open:-rotate-180">
                            <svg class="w-5 h-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </span>
                    </summary>
                    <p class="mt-4 text-sm text-slate-600 leading-relaxed pt-2 border-t border-slate-200/60">
                        {{ $faq['answer'] }}
                    </p>
                </details>
            @endforeach
        </div>
    </div>
</section>
