@php
    /*
     * Rewritten without Alpine. The Clireo shell does not load Alpine, and this
     * was the only section that depended on it — with the library gone the
     * slides stacked and the arrows did nothing.
     *
     * Now it is a scroll-snap track: it works with no JS at all (swipe, or
     * scroll the strip), and `clinic-clireo.js` upgrades it with arrows, dots
     * and auto-advance when JS is available.
     */
    $ratio = $data['aspect_ratio'] ?? '16:9';
    $ratioValue = match($ratio) {
        '5:4' => '5 / 4',
        '4:3' => '4 / 3',
        '1:1' => '1 / 1',
        '21:9' => '21 / 9',
        default => '16 / 9',
    };
    $items = $data['items'] ?? [];
    $heading = $data['heading'] ?? null;
@endphp

@if(!empty($items))
<section class="space-section" data-reveal-section>
    <div class="layout-container">
        @if(filled($heading))
            <div class="stack-header">
                <h2 class="fx-heading" data-fx-words data-reveal-block data-reveal-kind="heading">{{ $heading }}</h2>
            </div>
        @endif

        <div class="slider" data-slider data-slider-autoplay="5000" style="--slide-ratio: {{ $ratioValue }};" data-reveal-block data-reveal-kind="fade">
            <div class="slider-track" data-slider-track>
                @foreach($items as $item)
                    @php $slideLink = \App\Support\SafeUrl::href($item['link_url'] ?? '', ''); @endphp
                    <div class="slide">
                        @if($slideLink !== '')<a href="{{ $slideLink }}" class="slide-link">@endif
                            <img src="{{ $item['image_url'] }}" alt="{{ $item['title'] ?? '' }}">
                            @if(!empty($item['title']) || !empty($item['description']))
                                <div class="slide-caption">
                                    @if(!empty($item['title']))<h3>{{ $item['title'] }}</h3>@endif
                                    @if(!empty($item['description']))<p>{{ $item['description'] }}</p>@endif
                                </div>
                            @endif
                        @if($slideLink !== '')</a>@endif
                    </div>
                @endforeach
            </div>

            @if(count($items) > 1)
                <button class="arrow-btn slider-arrow slider-arrow--prev" type="button" data-slider-prev aria-label="{{ __('Previous') }}">←</button>
                <button class="arrow-btn slider-arrow slider-arrow--next" type="button" data-slider-next aria-label="{{ __('Next') }}">→</button>
                <div class="slider-dots" data-slider-dots>
                    @foreach($items as $index => $item)
                        <button type="button" data-slider-dot="{{ $index }}" aria-label="{{ __('Slide :n', ['n' => $index + 1]) }}"></button>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</section>
@endif
