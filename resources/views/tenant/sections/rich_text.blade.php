{{--
    Policy / long-form copy. Still sanitised through HtmlSanitizer — the block
    accepts raw HTML from an admin, so the restyle must not become a route to
    unescaped markup. Typography now comes from `.rich-text` rather than a wall
    of Tailwind arbitrary-variant classes.
--}}
<section class="space-section" data-reveal-section>
    <div class="layout-container">
        <div class="rich-text" data-reveal-block data-reveal-kind="fade">
            {!! \App\Support\HtmlSanitizer::clean($data['content'] ?? '') !!}
        </div>
    </div>
</section>
