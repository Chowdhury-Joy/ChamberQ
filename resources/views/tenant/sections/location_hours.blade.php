@php
    /*
     * No counterpart in the reference — designed in its language. Two shapes,
     * as before: a card per branch when `locations[]` is filled, otherwise the
     * single-address detail list. Both keep the Google Maps link, which is the
     * one CTA on this section that patients actually use on the way in.
     */
    $heading = $data['heading'] ?? __('Visit Our Clinic');
    $mapsUrl = \App\Support\SafeUrl::href($data['google_maps_url'] ?? '', '');
    $locations = $data['locations'] ?? [];
@endphp

<section id="locations" class="space-section" data-reveal-section>
    <div class="layout-container">
        <div class="stack-header">
            <div class="eyebrow" data-reveal-block data-reveal-kind="fade">{{ __('Find us') }}</div>
            <h2 class="fx-heading" data-fx-words data-reveal-block data-reveal-kind="heading">{{ $heading }}</h2>
        </div>

        @if(count($locations) > 0)
            <div class="why-grid grid-cards" data-card-count="{{ count($locations) }}" data-reveal-block data-reveal-kind="stagger">
                @foreach($locations as $location)
                    @php $locMaps = \App\Support\SafeUrl::href($location['google_maps_url'] ?? '', ''); @endphp
                    <article class="why-card loc-card">
                        <h3>{{ $location['name'] ?? __('Branch') }}</h3>
                        @if(!empty($location['address']))
                            <p>{{ $location['address'] }}</p>
                        @endif

                        <dl class="loc-facts">
                            @if(!empty($location['operating_hours']))
                                <dt>{{ __('Hours') }}</dt>
                                <dd>{{ $location['operating_hours'] }}</dd>
                            @endif
                            @if(!empty($location['phone']))
                                <dt>{{ __('Phone') }}</dt>
                                <dd><a href="tel:{{ preg_replace('/[^0-9+]/', '', $location['phone']) }}">{{ $location['phone'] }}</a></dd>
                            @endif
                        </dl>

                        @if($locMaps !== '')
                            <a class="btn-outline" href="{{ $locMaps }}" target="_blank" rel="noopener noreferrer">{{ __('Open in Google Maps') }}</a>
                        @endif
                    </article>
                @endforeach
            </div>
        @else
            <div class="loc-single" data-reveal-block data-reveal-kind="fade">
                <dl class="loc-facts">
                    @if(!empty($data['address']))
                        <dt>{{ __('Address') }}</dt>
                        <dd>{{ $data['address'] }}</dd>
                    @endif
                    @if(!empty($data['operating_hours']))
                        <dt>{{ __('Operating Hours') }}</dt>
                        <dd>{{ $data['operating_hours'] }}</dd>
                    @endif
                    @if(!empty($data['phone']))
                        <dt>{{ __('Phone') }}</dt>
                        <dd><a href="tel:{{ preg_replace('/[^0-9+]/', '', $data['phone']) }}">{{ $data['phone'] }}</a></dd>
                    @endif
                </dl>

                @if($mapsUrl !== '')
                    <a class="btn-accent sm" href="{{ $mapsUrl }}" target="_blank" rel="noopener noreferrer"><span>{{ __('Open Google Maps Directions') }}</span></a>
                @endif
            </div>
        @endif
    </div>
</section>
