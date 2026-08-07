@php
    /*
     * 1:1 from public/previews/clireo-homepage.html #doctors — including the
     * stats band that sits inside this section in the HTML (not a separate block).
     *
     * Prefer $data['cards'] (name, specialty, image_url) when the page builder
     * supplies them so the preview photography can ship. Otherwise fall back to
     * live Doctor rows, with optional photo_url overrides keyed by doctor_id.
     */
    $eyebrow = $data['eyebrow'] ?? __('Our doctors');
    $heading = $data['heading'] ?? __('Meet The Experts Behind Your Recovery');
    $bookHref = tenant_safe_href(null, '/book');
    $statsHeading = $data['stats_heading'] ?? null;
    $stats = $data['stats'] ?? [];
    $photoByDoctor = collect($data['photos'] ?? [])->keyBy(fn ($row) => (string) ($row['doctor_id'] ?? ''));

    $cards = collect($data['cards'] ?? []);
    if ($cards->isEmpty()) {
        $cards = collect($doctors ?? [])->map(function ($doctor) use ($photoByDoctor) {
            $photo = $photoByDoctor->get((string) $doctor->id);

            return [
                'name' => $doctor->name,
                'specialty' => $doctor->qualifications ?: ($doctor->practiceTypeLabel() ?: __('Specialist Physician')),
                'image_url' => $photo['image_url'] ?? null,
                'book_url' => tenant_web_url('/book?doctor='.$doctor->id),
            ];
        });
    }
@endphp

<section class="space-section" id="doctors" data-reveal-section>
    <div class="layout-container">
        <div class="treat-head">
            <div class="stack-header">
                <div class="eyebrow" data-reveal-block data-reveal-kind="fade">{{ $eyebrow }}</div>
                <h2 class="fx-heading" data-fx-words data-reveal-block data-reveal-kind="heading">{{ $heading }}</h2>
            </div>
            <a class="btn-pink sm" href="{{ $bookHref }}" data-reveal-block data-reveal-kind="fade">
                <span>{{ __('Book appointment') }}</span>
            </a>
        </div>

        <div class="doc-grid grid-cards" data-card-count="{{ $cards->count() }}" data-reveal-block data-reveal-kind="fade">
            @foreach($cards as $card)
                <article class="doc-card @if(empty($card['image_url'])) doc-card--initial @endif">
                    @if(!empty($card['image_url']))
                        <img src="{{ $card['image_url'] }}" alt="{{ $card['name'] ?? '' }}">
                    @else
                        <div class="doc-initial" aria-hidden="true">{{ mb_strtoupper(mb_substr($card['name'] ?? '?', 0, 1)) }}</div>
                    @endif
                    <div class="meta">
                        <h3>{{ $card['name'] ?? '' }}</h3>
                        <span>{{ $card['specialty'] ?? '' }}</span>
                    </div>
                </article>
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
