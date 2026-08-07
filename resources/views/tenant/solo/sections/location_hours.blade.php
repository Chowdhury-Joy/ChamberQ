{{--
    Solo copy of the pre-Clireo shared section, pinned here on 2026-08-07.

    The solo shell falls back to `tenant.sections.*` for any block it does not
    override, so porting those blades to the clinic design would have changed
    the LOCKED solo homepage. This file is the markup solo rendered before the
    port; `tenant/sections/location_hours.blade.php` is now clinic-only.
    Do not restyle without the owner saying "update patient homepage".
--}}
@php
    $heading = $data['heading'] ?? __('Visit Our Clinic');
    $mapsUrl = \App\Support\SafeUrl::href($data['google_maps_url'] ?? '', '');
    $locations = $data['locations'] ?? [];
@endphp

<section id="locations" class="solo-section w-full bg-white">
    <div class="mx-auto max-w-[1280px] px-3 sm:px-10">
        <h2 class="font-display text-3xl tracking-tight text-slate-900 sm:text-4xl lg:text-[2.75rem]">
            {{ $heading }}
        </h2>

        @if(count($locations) > 0)
            <x-card-grid :count="count($locations)" class="mt-8 gap-5 sm:gap-6 lg:mt-12">
                @foreach($locations as $location)
                    @php
                        $locMaps = \App\Support\SafeUrl::href($location['google_maps_url'] ?? '', '');
                    @endphp
                    <article class="flex flex-col justify-between rounded-2xl border p-5 sm:p-6" style="background-color: #FAFAFA; border-color: #E0E0E0;">
                        <div>
                            <h3 class="font-display text-2xl text-slate-900 sm:text-[1.75rem]">{{ $location['name'] ?? __('Branch') }}</h3>
                            @if(!empty($location['address']))
                                <p class="mt-3 text-sm leading-relaxed text-slate-600">{{ $location['address'] }}</p>
                            @endif
                            @if(!empty($location['operating_hours']))
                                <p class="mt-2 text-sm font-medium text-slate-800">{{ $location['operating_hours'] }}</p>
                            @endif
                            @if(!empty($location['phone']))
                                <p class="mt-1 text-sm text-slate-600">{{ $location['phone'] }}</p>
                            @endif
                        </div>
                        @if($locMaps !== '')
                            <a href="{{ $locMaps }}" target="_blank" rel="noopener noreferrer" class="solo-cta mt-6 w-full sm:w-auto">
                                {{ __('Open in Google Maps') }}
                            </a>
                        @endif
                    </article>
                @endforeach
            </x-card-grid>
        @else
            <div class="mt-8 grid gap-8 rounded-2xl border p-6 sm:mt-10 sm:p-10 lg:grid-cols-2 lg:items-center" style="background-color: #FAFAFA; border-color: #E0E0E0;">
                <div class="space-y-5">
                    @if(!empty($data['address']))
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">{{ __('Address') }}</p>
                            <p class="mt-1 text-sm font-medium text-slate-800 sm:text-base">{{ $data['address'] }}</p>
                        </div>
                    @endif

                    @if(!empty($data['operating_hours']))
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">{{ __('Operating Hours') }}</p>
                            <p class="mt-1 text-sm font-medium text-slate-800 sm:text-base">{{ $data['operating_hours'] }}</p>
                        </div>
                    @endif

                    @if(!empty($data['phone']))
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">{{ __('Phone') }}</p>
                            <p class="mt-1 text-sm font-medium text-slate-800 sm:text-base">{{ $data['phone'] }}</p>
                        </div>
                    @endif

                    @if($mapsUrl !== '')
                        <div class="pt-2">
                            <a href="{{ $mapsUrl }}" target="_blank" rel="noopener noreferrer" class="solo-cta">
                                <span>{{ __('Open Google Maps Directions') }}</span>
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 17L17 7M17 7H7M17 7v10"/></svg>
                            </a>
                        </div>
                    @endif
                </div>

                <div class="flex min-h-[14rem] flex-col items-center justify-center rounded-2xl border bg-white p-6 text-center" style="border-color: #E0E0E0;">
                    <h4 class="font-display text-xl text-slate-900">{{ __('Map & directions') }}</h4>
                    <p class="mt-2 max-w-xs text-sm text-slate-500">{{ __('Open Google Maps for turn-by-turn navigation to the clinic.') }}</p>
                    @if($mapsUrl !== '')
                        <a href="{{ $mapsUrl }}" target="_blank" rel="noopener noreferrer" class="solo-cta-outline mt-5 text-sm">
                            {{ __('View location') }}
                        </a>
                    @endif
                </div>
            </div>
        @endif
    </div>
</section>
