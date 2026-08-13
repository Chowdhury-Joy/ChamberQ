<footer class="mk-footer">
    <div class="mk-wrap mk-footer-inner">
        <div>
            <p class="mk-footer-brand">{{ $product }}</p>
            <p class="mk-footer-copy">Online serials, a live queue, and a calmer chamber — set up with you over WhatsApp.</p>
            <p class="mk-footer-copy">&copy; {{ date('Y') }}, {{ $product }}</p>
        </div>
        <div>
            <p><a href="{{ $generalWa }}" target="_blank" rel="noopener noreferrer">WhatsApp</a></p>
            @if(config('marketing.phone'))
                <p class="mk-footer-meta">{{ config('marketing.phone') }}</p>
            @endif
        </div>
    </div>
</footer>
