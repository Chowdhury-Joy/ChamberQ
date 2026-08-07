@php
    /*
     * 1:1 from public/previews/clireo-homepage.html .final-cta.
     */
    $headline = $data['headline'] ?? 'Prioritize Your Recovery Today';
    $subheadline = $data['subheadline'] ?? 'Take the first step toward better mobility with expert care tailored to your needs.';
    $ctaText = $data['cta_text'] ?? 'Book an appointment';
    $ctaLink = tenant_safe_href($data['cta_link'] ?? '/book', '/book');
    $trustPhone = $data['trust_phone'] ?? ($tenant?->contact_phone);
    $trustAddress = $data['trust_address'] ?? null;
@endphp

<section class="final-cta space-section" data-reveal-section>
    <div class="final-cta-bg" aria-hidden="true"></div>
    <div class="layout-container">
        <h2 class="fx-heading" data-fx-words data-reveal-block data-reveal-kind="heading">{{ $headline }}</h2>
        @if(filled($subheadline))
            <p data-reveal-block data-reveal-kind="fade">{{ $subheadline }}</p>
        @endif
        <a class="btn-pink sm" href="{{ $ctaLink }}" data-reveal-block data-reveal-kind="fade">
            <span>{{ $ctaText }}</span>
        </a>
        @if(filled($trustPhone) || filled($trustAddress))
            <div class="trust-mini" data-reveal-block data-reveal-kind="fade">
                @if(filled($trustPhone))<strong>{{ $trustPhone }}</strong>@endif
                @if(filled($trustPhone) && filled($trustAddress)) · @endif
                @if(filled($trustAddress)){{ $trustAddress }}@endif
            </div>
        @endif
    </div>
</section>
