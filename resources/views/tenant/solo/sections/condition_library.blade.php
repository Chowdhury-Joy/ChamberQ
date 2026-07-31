@php
    $heading = $data['heading'] ?? __('Conditions I Treat');
    $conditions = $data['conditions'] ?? [];
@endphp

<section id="services" class="solo-section w-full bg-white">
    <div class="mx-auto max-w-[1280px] px-3 sm:px-10">
        <h2 class="font-display text-3xl tracking-tight text-slate-900 sm:text-4xl lg:text-[2.75rem]">
            {{ $heading }}
        </h2>

        <x-card-grid :count="count($conditions)" class="mt-8 lg:mt-12">
            @foreach($conditions as $condition)
                @php
                    $features = $condition['features'] ?? [];
                @endphp
                <article class="flex flex-col rounded-2xl border p-2" style="background-color: #FAFAFA; border-color: #E0E0E0;">
                    <div class="px-2 pb-2 pt-3 sm:px-3">
                        <h3 class="font-display text-2xl text-slate-900 sm:text-[1.75rem]">
                            {{ $condition['name'] ?? '' }}
                        </h3>
                        @if(!empty($condition['description']))
                            <p class="mt-2 text-sm leading-relaxed text-slate-600 sm:text-[0.95rem]">
                                {{ $condition['description'] }}
                            </p>
                        @endif
                    </div>

                    @if(count($features) > 0)
                        <div class="mt-2 rounded-xl border p-2 sm:p-3" style="background-color: #F2F2F2; border-color: #E6E6E6;">
                            <p class="px-1 text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">
                                {{ __('Including:') }}
                            </p>
                            <ul class="mt-3 space-y-2">
                                @foreach($features as $feature)
                                    @php
                                        $label = is_array($feature)
                                            ? ($feature['label'] ?? $feature['name'] ?? reset($feature) ?: '')
                                            : (string) $feature;
                                    @endphp
                                    @if($label !== '')
                                        <li class="flex items-center gap-3 rounded-xl px-3 py-3" style="background-color: #ffffff; box-shadow: 0 1px 2px 0 rgba(27, 27, 27, 0.03), 0 0 0 1px rgba(27, 27, 27, 0.03);">
                                            <span class="inline-flex h-5 w-5 shrink-0 items-center justify-center text-emerald-600" aria-hidden="true">
                                                {{-- Shared medical icon (stethoscope) for every service row --}}
                                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 3v7a6 6 0 0012 0V3"/>
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 3a2 2 0 01-2 2M18 3a2 2 0 002 2"/>
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 13v2a4 4 0 11-8 0v-1"/>
                                                    <circle cx="18" cy="19" r="2" fill="currentColor" stroke="none"/>
                                                </svg>
                                            </span>
                                            <span class="text-sm font-medium text-slate-800 sm:text-[0.95rem]">{{ $label }}</span>
                                        </li>
                                    @endif
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </article>
            @endforeach
        </x-card-grid>
    </div>
</section>
