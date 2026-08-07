@php
    /* Numbered steps as Clireo feature cards. */
    $heading = $data['heading'] ?? null;
    $steps = $data['steps'] ?? [];
@endphp

<section class="space-section" id="journey" data-reveal-section>
    <div class="layout-container">
        @if(filled($heading))
            <div class="stack-header is-center" style="text-align:center">
                <div class="eyebrow" style="justify-content:center" data-reveal-block data-reveal-kind="fade">{{ __('How it works') }}</div>
                <h2 class="fx-heading" data-fx-words data-reveal-block data-reveal-kind="heading">{{ $heading }}</h2>
            </div>
        @endif

        <div class="why-grid grid-cards" data-card-count="{{ count($steps) }}" data-reveal-block data-reveal-kind="stagger">
            @foreach($steps as $step)
                <article class="why-card">
                    <div class="why-step">{{ $step['step_number'] ?? str_pad((string) ($loop->index + 1), 2, '0', STR_PAD_LEFT) }}</div>
                    <h3>{{ $step['title'] ?? '' }}</h3>
                    <p>{{ $step['description'] ?? '' }}</p>
                </article>
            @endforeach
        </div>
    </div>
</section>
