{{--
    Solo copy of the pre-Clireo shared section, pinned here on 2026-08-07.

    The solo shell falls back to `tenant.sections.*` for any block it does not
    override, so porting those blades to the clinic design would have changed
    the LOCKED solo homepage. This file is the markup solo rendered before the
    port; `tenant/sections/service_matrix.blade.php` is now clinic-only.
    Do not restyle without the owner saying "update patient homepage".
--}}
<section id="services" class="solo-section w-full bg-white">
    <div class="mx-auto max-w-[1280px] px-3 sm:px-10">
        @if(!empty($data['heading']))
            <div class="mx-auto mb-8 max-w-2xl text-center sm:mb-10">
                <h2 class="font-display text-3xl tracking-tight text-slate-900 sm:text-4xl lg:text-[2.75rem]">
                    {{ $data['heading'] }}
                </h2>
                @if(!empty($data['description']))
                    <p class="mt-3 text-sm leading-relaxed text-slate-600 sm:text-base lg:text-lg">{{ $data['description'] }}</p>
                @endif
            </div>
        @endif

        @php $items = $data['items'] ?? []; @endphp

        @if(count($items) < 8)
            <x-card-grid :count="count($items)" class="gap-5 sm:gap-6">
                @foreach($items as $item)
                    <article class="rounded-2xl border p-5 sm:p-6" style="background-color: #FAFAFA; border-color: #E0E0E0;">
                        <div class="mb-4 flex h-10 w-10 items-center justify-center rounded-full" style="background-color: color-mix(in srgb, var(--color-primary) 14%, white); color: var(--color-primary);">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                        </div>
                        <h3 class="font-display text-2xl text-slate-900 sm:text-[1.75rem]">{{ $item['title'] }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-slate-600 sm:text-[0.95rem]">{{ $item['description'] ?? '' }}</p>
                    </article>
                @endforeach
            </x-card-grid>
        @else
            <div class="rounded-2xl border bg-white p-6 sm:p-8" style="border-color: #E0E0E0;">
                <ul class="divide-y divide-slate-100">
                    @foreach($items as $item)
                        <li class="flex flex-col justify-between gap-4 py-4 first:pt-0 last:pb-0 sm:flex-row sm:items-center">
                            <div>
                                <h3 class="text-base font-semibold text-slate-900 sm:text-lg">{{ $item['title'] }}</h3>
                                @if(!empty($item['description']))
                                    <p class="mt-1 text-sm text-slate-500">{{ $item['description'] }}</p>
                                @endif
                            </div>
                            <a href="{{ tenant_web_url('/book') }}" class="solo-cta-outline shrink-0 text-sm">
                                {{ __('Book Service') }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</section>
