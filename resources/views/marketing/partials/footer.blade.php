<footer class="mk-footer">
    <div class="mk-wrap mk-footer-inner">
        <div>
            <p class="mk-footer-brand">{{ $product }}</p>
            <p class="mk-footer-copy">Online serials, a live queue you control, and a calmer consult — set up with you over WhatsApp.</p>
            <p class="mk-footer-copy">&copy; {{ date('Y') }}, {{ $product }}</p>
        </div>
        <div>
            <p><a href="{{ $generalWa }}" target="_blank" rel="noopener noreferrer">WhatsApp</a></p>
            @if($phone)
                <p class="mk-footer-meta">{{ $phone }}</p>
            @endif
        </div>
    </div>
</footer>
