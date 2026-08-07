@if(tenant()?->isClinic())
@php
    /*
     * 1:1 from public/previews/clireo-homepage.html #about.
     */
    $eyebrow = $data['heading'] ?? 'About us';
    $mission = $data['mission_statement'] ?? '';
    $ctaText = $data['cta_text'] ?? 'More about us';
    $ctaLink = tenant_safe_href($data['cta_link'] ?? '/book', '/book');
    $trustCopy = $data['trust_copy'] ?? 'Trusted by';
    $trustStrong = $data['trust_strong'] ?? 'patients across the city';
    $gallery = $data['gallery'] ?? [];
    $trustAvatars = [
        $data['trust_avatar_1'] ?? 'https://images.unsplash.com/photo-1612349317150-e413f6a5b16d?auto=format&fit=crop&w=80&h=80&q=80',
        $data['trust_avatar_2'] ?? 'https://images.unsplash.com/photo-1531746020798-e6953c6e8e04?auto=format&fit=crop&w=80&h=80&q=80',
        $data['trust_avatar_3'] ?? 'https://images.unsplash.com/photo-1594824476967-48c8b964273f?auto=format&fit=crop&w=80&h=80&q=80',
    ];
@endphp

<section class="space-section" id="about" data-reveal-section>
    <div class="layout-container">
        <div class="about-head stack-header is-center">
            <div class="eyebrow" style="justify-content:center" data-reveal-block data-reveal-kind="fade">{{ $eyebrow }}</div>
            @if(filled($mission))
                <h2 class="fx-heading" data-fx-words data-reveal-block data-reveal-kind="heading">{{ $mission }}</h2>
            @endif
            <div class="about-cta-row" data-reveal-block data-reveal-kind="fade">
                <a class="btn-about" href="{{ $ctaLink }}" @if(str_starts_with($ctaLink, 'http')) target="_blank" rel="noopener noreferrer" @endif>
                    <span>{{ $ctaText }}</span>
                    <span class="arrow" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17L17 7M9 7h8v8"/></svg>
                    </span>
                </a>
                <div class="about-trust">
                    <div class="about-trust-avs">
                        @foreach($trustAvatars as $avatar)
                            <img src="{{ $avatar }}" alt="">
                        @endforeach
                    </div>
                    <div class="about-trust-copy">{{ $trustCopy }}<strong>{{ $trustStrong }}</strong></div>
                </div>
            </div>
        </div>

        @if(filled($gallery))
            <div class="about-features" data-reveal-block data-reveal-kind="fade">
                @foreach($gallery as $item)
                    <article class="about-feature">
                        @if(!empty($item['image_url']))
                            <div class="icon"><img src="{{ $item['image_url'] }}" alt=""></div>
                        @endif
                        <h3>{{ $item['title'] ?? '' }}</h3>
                        @if(!empty($item['description']))
                            <p>{{ $item['description'] }}</p>
                        @endif
                    </article>
                @endforeach
            </div>
        @endif
    </div>
</section>
@endif
