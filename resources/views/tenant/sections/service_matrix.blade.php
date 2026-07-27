<section id="services" class="w-full bg-white py-10 sm:py-14 lg:py-16">
    <div class="mx-auto max-w-[1320px] px-4 sm:px-6 lg:px-8">
        @if(!empty($data['heading']))
            <div class="mx-auto mb-8 max-w-2xl text-center sm:mb-10">
                <h2 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl lg:text-4xl">
                    {{ $data['heading'] }}
                </h2>
                @if(!empty($data['description']))
                    <p class="mt-2 text-sm leading-relaxed text-slate-600 sm:mt-3 sm:text-base lg:text-lg">{{ $data['description'] }}</p>
                @endif
            </div>
        @endif

        @php $items = $data['items'] ?? []; @endphp

        @if(count($items) < 8)
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 sm:gap-6 lg:grid-cols-3 lg:gap-8">
                @foreach($items as $item)
                    <div class="rounded-2xl border border-slate-200/80 bg-slate-50 p-5 shadow-sm transition hover:shadow-md sm:p-6 lg:p-7">
                        <div class="mb-4 flex h-10 w-10 items-center justify-center rounded-lg" style="background-color: color-mix(in srgb, var(--color-primary) 14%, white); color: var(--color-primary);">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                        </div>
                        <h3 class="text-lg font-bold text-slate-900">{{ $item['title'] }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-slate-600 sm:text-[0.95rem]">{{ $item['description'] ?? '' }}</p>
                    </div>
                @endforeach
            </div>
        @else
            <div class="rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm sm:p-8">
                <ul class="divide-y divide-slate-100">
                    @foreach($items as $item)
                        <li class="flex flex-col justify-between gap-4 py-4 first:pt-0 last:pb-0 sm:flex-row sm:items-center">
                            <div>
                                <h3 class="text-base font-bold text-slate-900 sm:text-lg">{{ $item['title'] }}</h3>
                                @if(!empty($item['description']))
                                    <p class="mt-1 text-sm text-slate-500">{{ $item['description'] }}</p>
                                @endif
                            </div>
                            <a href="/book" class="inline-flex shrink-0 items-center gap-1 text-sm font-semibold hover:opacity-80" style="color: var(--color-primary);">
                                <span>{{ __('Book Service') }}</span>
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</section>
