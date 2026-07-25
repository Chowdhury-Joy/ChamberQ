<?php

namespace App\Support;

use DOMAttr;
use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Allowlist sanitiser for tenant-authored rich text.
 *
 * Tenant admins are only semi-trusted: their content is rendered unescaped on a
 * public page that patients read before handing over a phone number, so a
 * compromised or malicious clinic account must not be able to run script there.
 *
 * The approach is strict-allowlist, not blocklist. Anything not explicitly
 * permitted is unwrapped (children kept, tag discarded) or dropped.
 */
class HtmlSanitizer
{
    /** @var array<string, list<string>> tag => permitted attributes */
    private const ALLOWED = [
        'p' => [],
        'br' => [],
        'strong' => [],
        'b' => [],
        'em' => [],
        'i' => [],
        'u' => [],
        's' => [],
        'ul' => [],
        'ol' => [],
        'li' => [],
        'blockquote' => [],
        'h1' => [], 'h2' => [], 'h3' => [], 'h4' => [], 'h5' => [], 'h6' => [],
        'a' => ['href', 'title'],
        'span' => [],
        'div' => [],
        'hr' => [],
    ];

    /** Tags whose entire subtree is removed rather than unwrapped. */
    private const STRIP_SUBTREE = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'svg', 'math'];

    private const ALLOWED_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    public static function clean(?string $html): string
    {
        if (blank($html)) {
            return '';
        }

        $document = new DOMDocument();

        $previous = libxml_use_internal_errors(true);

        // Wrap so libxml does not inject <html><body>, and force UTF-8.
        $document->loadHTML(
            '<?xml encoding="UTF-8"><div id="__root__">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById('__root__');

        if (! $root) {
            return '';
        }

        self::cleanNode($root);

        $output = '';

        foreach ($root->childNodes as $child) {
            $output .= $document->saveHTML($child);
        }

        return trim($output);
    }

    private static function cleanNode(DOMNode $node): void
    {
        // Iterate over a snapshot: the live NodeList shifts as we remove nodes.
        foreach (iterator_to_array($node->childNodes) as $child) {
            if (! $child instanceof DOMElement) {
                // Text and CDATA are kept; comments are not (they can carry
                // conditional-comment payloads).
                if ($child->nodeType === XML_COMMENT_NODE) {
                    $child->parentNode?->removeChild($child);
                }

                continue;
            }

            $tag = strtolower($child->tagName);

            if (in_array($tag, self::STRIP_SUBTREE, true)) {
                $child->parentNode?->removeChild($child);

                continue;
            }

            // Recurse before deciding, so an unwrapped element's children have
            // already been cleaned.
            self::cleanNode($child);

            if (! array_key_exists($tag, self::ALLOWED)) {
                self::unwrap($child);

                continue;
            }

            self::cleanAttributes($child, self::ALLOWED[$tag]);
        }
    }

    /**
     * @param  list<string>  $allowedAttributes
     */
    private static function cleanAttributes(DOMElement $element, array $allowedAttributes): void
    {
        foreach (iterator_to_array($element->attributes) as $attribute) {
            /** @var DOMAttr $attribute */
            $name = strtolower($attribute->name);

            if (! in_array($name, $allowedAttributes, true)) {
                $element->removeAttribute($attribute->name);

                continue;
            }

            if ($name === 'href' && ! self::isSafeUrl($attribute->value)) {
                $element->removeAttribute($attribute->name);
            }
        }

        // Any surviving link leaves our origin, so harden it.
        if (strtolower($element->tagName) === 'a' && $element->hasAttribute('href')) {
            $element->setAttribute('rel', 'noopener noreferrer nofollow');
        }
    }

    private static function isSafeUrl(string $url): bool
    {
        $url = trim($url);

        // Relative and anchor links are fine.
        if ($url === '' || str_starts_with($url, '/') || str_starts_with($url, '#')) {
            return true;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, self::ALLOWED_SCHEMES, true);
    }

    private static function unwrap(DOMElement $element): void
    {
        $parent = $element->parentNode;

        if (! $parent) {
            return;
        }

        while ($element->firstChild) {
            $parent->insertBefore($element->firstChild, $element);
        }

        $parent->removeChild($element);
    }
}
