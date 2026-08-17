@php
    /*
     * 1:1 from public/previews/clireo-homepage.html #reviews.
     */
    $tenant = $tenant ?? tenant();
    $eyebrow = $data['eyebrow'] ?? 'Recovery stories';
    $heading = $data['heading'] ?? 'Real Progress From Rehabilitation Treatment';
    $items = $data['items'] ?? [];
    $promoText = $data['promo_text'] ?? 'Follow us on Facebook →';
    $promoLink = \App\Support\SafeUrl::href($data['promo_link'] ?? '', '');
    $bookHref = tenant_safe_href(null, '/book');
@endphp

<section class="space-section reviews" id="reviews" data-reveal-section>
    <div class="layout-container center">
        <div class="stack-header is-center">
            <div class="eyebrow" style="justify-content:center" data-reveal-block data-reveal-kind="fade">{{ $eyebrow }}</div>
            <h2 class="fx-heading" data-fx-words data-reveal-block data-reveal-kind="heading">{{ $heading }}</h2>
        </div>

        <div class="review-scroller" data-review-scroll data-reveal-block data-reveal-kind="fade">
            @foreach($items as $item)
                <article class="review-card">
                    <p>{{ $item['quote'] ?? '' }}</p>
                    <div class="review-person">
                        @if(!empty($item['photo_url']))
                            <img src="{{ $item['photo_url'] }}" alt="">
                        @endif
                        <div>
                            <strong>{{ $item['name'] ?? '' }}</strong>
                            <span>{{ $item['label'] ?? '' }}</span>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>

        <div class="google-row" data-reveal-block data-reveal-kind="fade">
            <div class="google-score">
                @if($tenant?->logo_url)
                    <img class="logo-img logo-img--sm" src="{{ $tenant->logo_url }}" alt="{{ $tenant->displayName() }}">
                @elseif(file_exists(public_path('images/cbph-logo.png')))
                    <img class="logo-img logo-img--sm" src="{{ public_asset('images/cbph-logo.png') }}" alt="">
                @endif
                @if($promoLink !== '')
                    <a href="{{ $promoLink }}" target="_blank" rel="noopener noreferrer">{{ $promoText }}</a>
                @endif
            </div>
            <a class="btn-pink sm" href="{{ $bookHref }}">
                <span>{{ __('Book appointment') }}</span>
            </a>
        </div>
    </div>
</section>
