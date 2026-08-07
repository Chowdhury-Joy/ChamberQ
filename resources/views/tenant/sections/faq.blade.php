@php
    /*
     * 1:1 from public/previews/clireo-homepage.html .faq.
     */
    $heading = $data['heading'] ?? 'Frequently Questions';
    $faqs = $data['faqs'] ?? [];
    $promoImage = $data['promo_image_url'] ?? 'https://images.pexels.com/photos/8460127/pexels-photo-8460127.jpeg?auto=compress&cs=tinysrgb&w=900&h=1200&fit=crop';
    $promoHeading = $data['promo_heading'] ?? 'Need physiotherapy? Book an appointment';
    $promoCtaText = $data['promo_cta_text'] ?? 'Get in touch';
    $promoCtaLink = tenant_safe_href($data['promo_cta_link'] ?? '/book', '/book');
@endphp

<section class="space-section faq" data-reveal-section>
    <div class="layout-container">
        <div class="stack-header">
            <div class="eyebrow" data-reveal-block data-reveal-kind="fade">{{ __('FAQs') }}</div>
            <h2 class="fx-heading" data-fx-words data-reveal-block data-reveal-kind="heading">{{ $heading }}</h2>
        </div>

        <div class="faq-layout">
            <aside class="faq-promo" data-reveal-block data-reveal-kind="fade">
                <img src="{{ $promoImage }}" alt="">
                <div class="overlay">
                    <h3>{{ $promoHeading }}</h3>
                    <a class="btn-pink sm" href="{{ $promoCtaLink }}">
                        <span>{{ $promoCtaText }}</span>
                    </a>
                </div>
            </aside>

            <div class="faq-list stack-list" data-reveal-block data-reveal-kind="fade">
                @foreach($faqs as $index => $faq)
                    <details class="faq-item" @if($index === 0) open @endif>
                        <summary>{{ $faq['question'] ?? '' }}</summary>
                        <div class="ans">{{ $faq['answer'] ?? '' }}</div>
                    </details>
                @endforeach
            </div>
        </div>
    </div>
</section>
