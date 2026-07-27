@php
    $heading = $data['heading'] ?? __('Everything You Need To Know');
    $faqs = $data['faqs'] ?? [];
@endphp

<section class="w-full bg-white py-10 sm:py-14 lg:py-16">
    <div class="mx-auto grid max-w-[1280px] gap-8 px-3 sm:px-10 lg:grid-cols-[minmax(0,0.4fr)_minmax(0,0.6fr)] lg:gap-12">
        <h2 class="font-display text-3xl leading-[1.1] tracking-tight text-slate-900 sm:text-4xl lg:text-[2.75rem]">
            {{ $heading }}
        </h2>

        <div class="space-y-3">
            @foreach($faqs as $index => $faq)
                <details class="group rounded-2xl border border-slate-200/80 bg-slate-50/80 open:bg-slate-50 [&_summary::-webkit-details-marker]:none" @if($index === 0) open @endif>
                    <summary class="flex cursor-pointer items-start justify-between gap-4 px-4 py-4 text-left sm:px-5">
                        <span class="text-xs font-semibold uppercase tracking-[0.06em] text-slate-800 sm:text-sm">
                            {{ $faq['question'] ?? '' }}
                        </span>
                        <svg class="mt-0.5 h-5 w-5 shrink-0 text-slate-500 transition group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </summary>
                    <div class="px-4 pb-4 sm:px-5">
                        <p class="border-t border-slate-200/70 pt-3 text-sm leading-relaxed text-slate-600">
                            {{ $faq['answer'] ?? '' }}
                        </p>
                    </div>
                </details>
            @endforeach
        </div>
    </div>
</section>
