@php
    $eyebrow = $data['eyebrow'] ?? __('Our doctors');
    $heading = $data['heading'] ?? __('Meet The Experts Behind Your Recovery');
    $bookHref = tenant_safe_href(null, '/book');
    $statsHeading = $data['stats_heading'] ?? null;
    $stats = $data['stats'] ?? [];
    $viewAllLink = tenant_safe_href($data['view_all_link'] ?? '/doctors', '/doctors');
    $viewAllText = preg_replace('/\s*[→➜➔]\s*$/u', '', (string) ($data['view_all_text'] ?? 'View all doctors')) ?: 'View all doctors';

    $cards = collect($websiteDoctors ?? [])->map(fn ($doctor) => [
        'name' => $doctor->name,
        'specialty' => $doctor->websiteSpecialtyLabel(),
        'image_url' => $doctor->photo_url,
        'detail_url' => tenant_web_url('/doctors/'.$doctor->public_slug),
        'book_url' => tenant_web_url('/book?doctor='.$doctor->id),
    ]);
@endphp

<section class="space-section" id="doctors" data-reveal-section>
    <div class="layout-container docs-split">
        <div class="treat-head">
            <div class="stack-header">
                <div class="eyebrow" data-reveal-block data-reveal-kind="fade">{{ $eyebrow }}</div>
                <h2 class="fx-heading" data-fx-words data-reveal-block data-reveal-kind="heading">{{ $heading }}</h2>
            </div>
            <a class="btn-pink sm" href="{{ $viewAllLink }}" data-reveal-block data-reveal-kind="fade">
                <span>{{ $viewAllText }}</span>
            </a>
        </div>

        <div class="doc-grid grid-cards" data-card-count="{{ $cards->count() }}" data-reveal-block data-reveal-kind="fade">
            @foreach($cards as $card)
                <a class="doc-card @if(empty($card['image_url'])) doc-card--initial @endif" href="{{ $card['detail_url'] ?? $bookHref }}">
                    @if(!empty($card['image_url']))
                        <img src="{{ $card['image_url'] }}" alt="{{ $card['name'] ?? '' }}">
                    @else
                        <div class="doc-initial" aria-hidden="true">{{ mb_strtoupper(mb_substr($card['name'] ?? '?', 0, 1)) }}</div>
                    @endif
                    <div class="meta">
                        <h3>{{ $card['name'] ?? '' }}</h3>
                        <span>{{ $card['specialty'] ?? '' }}</span>
                    </div>
                </a>
            @endforeach
        </div>

        @if(filled($stats))
            <div class="stats-band space-card" data-reveal-block data-reveal-kind="fade">
                @if(filled($statsHeading))
                    <h3>{{ $statsHeading }}</h3>
                @endif
                <div class="grid-stats">
                    @foreach($stats as $stat)
                        @php
                            $value = trim((string) ($stat['value'] ?? ''));
                            $countable = null;
                            if (preg_match('/^(\d+)/', $value, $m) && (int) $m[1] > 0) {
                                $countable = (int) $m[1];
                            }
                        @endphp
                        <div class="stat">
                            @if($countable !== null)
                                <div class="num" data-count="{{ $countable }}">0</div>
                            @else
                                <div class="num">{{ $value }}</div>
                            @endif
                            <div class="lbl">{{ $stat['label'] ?? '' }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</section>
