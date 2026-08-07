@if(tenant()?->isClinic())
@php
    /*
     * Clireo stats band with count-up numbers. The counter script overwrites
     * the element's text with a bare integer, which constrains what may be
     * animated:
     *
     *  - "99%" and "15+" animate — digits, with the suffix kept outside the
     *    counted span so it survives.
     *  - "50,000+" does not. The script would land on "50000+", losing the
     *    separator the clinic typed.
     *  - "24/7" does not. It would print "24".
     *
     * The span's own text is the true value, so a stat reads correctly before
     * the animation starts and stays correct if the script never runs — the
     * reference hardcodes a "0" there, which would show a patient "0%".
     */
    $stats = collect($data['stats'] ?? [])->map(function (array $stat): array {
        $value = trim((string) ($stat['value'] ?? ''));
        $countable = null;
        $suffix = '';

        if (preg_match('/^(\d+)(\D*)$/', $value, $m) && (int) $m[1] > 0) {
            $countable = (int) $m[1];
            $suffix = $m[2];
        }

        return [
            'value' => $value,
            'countable' => $countable,
            'suffix' => $suffix,
            'label' => $stat['label'] ?? '',
        ];
    });
@endphp

<section class="space-section" data-reveal-section>
    <div class="layout-container">
        <div class="stats-band space-card" data-reveal-block data-reveal-kind="fade">
            <div class="grid-stats">
                @foreach($stats as $stat)
                    <div class="stat">
                        @if($stat['countable'] !== null)
                            <div class="num"><span data-count="{{ $stat['countable'] }}">{{ $stat['countable'] }}</span>{{ $stat['suffix'] }}</div>
                        @else
                            <div class="num">{{ $stat['value'] }}</div>
                        @endif
                        <div class="lbl">{{ $stat['label'] }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</section>
@endif
