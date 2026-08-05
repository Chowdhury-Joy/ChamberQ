@php
    $heading = $data['heading'] ?? __('Conditions I Treat');
    $conditions = $data['conditions'] ?? [];
@endphp

<section id="services" class="solo-section w-full bg-white">
    <div class="mx-auto max-w-[1280px] px-3 sm:px-10">
        <h2 class="solo-h2 text-slate-900">
            {{ $heading }}
        </h2>

        {{-- Figma: equal treatment cards in one row on desktop (3-up). Inner feature pills stay a vertical list. --}}
        <x-card-grid :count="count($conditions)" class="mt-8 gap-6 lg:mt-12">
            @foreach($conditions as $condition)
                @php
                    $features = $condition['features'] ?? [];
                @endphp
                <article class="flex flex-col rounded-3xl border p-2" style="background-color: #FAFAFA; border-color: #E0E0E0;">
                    <div class="px-1.5 pb-2 pt-3 sm:px-2">
                        <h3 class="solo-h3 text-slate-900">
                            {{ $condition['name'] ?? '' }}
                        </h3>
                        @if(!empty($condition['description']))
                            <p class="solo-body-lg mt-2 text-slate-600">
                                {{ $condition['description'] }}
                            </p>
                        @endif
                    </div>

                    @if(count($features) > 0)
                        <div class="mt-6 flex w-full flex-col gap-3">
                            <p class="solo-tagline px-1.5 text-slate-500">
                                {{ __('Including:') }}
                            </p>
                            <ul class="flex flex-col gap-1.5 rounded-2xl border p-1.5" style="background-color: #F2F2F2; border-color: #E6E6E6;">
                                @foreach($features as $feature)
                                    @php
                                        $label = is_array($feature)
                                            ? ($feature['label'] ?? $feature['name'] ?? reset($feature) ?: '')
                                            : (string) $feature;
                                    @endphp
                                    @if($label !== '')
                                        <li class="flex items-center gap-1.5 rounded-xl p-3" style="background-color: #ffffff; box-shadow: 0 1px 2px 0 rgba(27, 27, 27, 0.03), 0 0 0 1px rgba(27, 27, 27, 0.03);">
                                            <span class="inline-flex h-4 w-4 shrink-0 items-center justify-center text-emerald-600" aria-hidden="true">
                                                {{-- Shared medical icon (stethoscope) for every service row --}}
                                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 3v7a6 6 0 0012 0V3"/>
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 3a2 2 0 01-2 2M18 3a2 2 0 002 2"/>
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 13v2a4 4 0 11-8 0v-1"/>
                                                    <circle cx="18" cy="19" r="2" fill="currentColor" stroke="none"/>
                                                </svg>
                                            </span>
                                            <span class="solo-body font-medium text-slate-800">{{ $label }}</span>
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
