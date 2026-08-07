@php
    /*
     * Clireo marquee band. The reference marks the whole thing aria-hidden
     * because its words are decorative; these badges are real claims about the
     * practice, so the first group stays readable to screen readers and only
     * the duplicate — which exists to make the scroll loop seamless — is hidden.
     */
    $badges = collect($data['badges'] ?? [])
        ->map(fn ($badge) => trim((string) ($badge['text_badge'] ?? '')))
        ->filter()
        ->values();
@endphp

@if($badges->isNotEmpty())
    <div class="marquee">
        <div class="marquee-track">
            <div class="marquee-group">
                @foreach($badges as $badge)
                    <span>{{ $badge }}</span><i aria-hidden="true"></i>
                @endforeach
            </div>
            <div class="marquee-group" aria-hidden="true">
                @foreach($badges as $badge)
                    <span>{{ $badge }}</span><i></i>
                @endforeach
            </div>
        </div>
    </div>
@endif
