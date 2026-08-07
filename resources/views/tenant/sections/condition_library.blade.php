@php
    /* Conditions as Clireo feature cards, each with its "including" list. */
    $heading = $data['heading'] ?? __('What we help with');
    $conditions = $data['conditions'] ?? [];
@endphp

<section class="space-section" id="conditions" data-reveal-section>
    <div class="layout-container">
        <div class="stack-header">
            <div class="eyebrow" data-reveal-block data-reveal-kind="fade">{{ __('Conditions') }}</div>
            <h2 class="fx-heading" data-fx-words data-reveal-block data-reveal-kind="heading">{{ $heading }}</h2>
        </div>

        <div class="why-grid grid-cards" data-card-count="{{ count($conditions) }}" data-reveal-block data-reveal-kind="stagger">
            @foreach($conditions as $condition)
                @php $features = $condition['features'] ?? []; @endphp
                <article class="why-card">
                    <h3>{{ $condition['name'] ?? '' }}</h3>
                    @if(!empty($condition['description']))
                        <p>{{ $condition['description'] }}</p>
                    @endif

                    @if(count($features) > 0)
                        <p class="cond-label">{{ __('Including:') }}</p>
                        <ul class="cond-features">
                            @foreach($features as $feature)
                                @php
                                    $label = is_array($feature)
                                        ? ($feature['label'] ?? $feature['name'] ?? reset($feature) ?: '')
                                        : (string) $feature;
                                @endphp
                                @if($label !== '')
                                    <li>{{ $label }}</li>
                                @endif
                            @endforeach
                        </ul>
                    @endif
                </article>
            @endforeach
        </div>
    </div>
</section>
