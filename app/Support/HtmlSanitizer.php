<?php

namespace App\Support;

use Mews\Purifier\Facades\Purifier;

/**
 * Sanitiser for tenant-authored rich text.
 *
 * Tenant admins are only semi-trusted: their content is rendered unescaped on a
 * public page that patients read before handing over a phone number, so a
 * compromised clinic account must not be able to run script there.
 *
 * Delegates to HTMLPurifier rather than hand-rolling tag filtering — HTML
 * sanitising has too many edge cases (mutation XSS, malformed markup that
 * parsers disagree about, exotic encodings) to reimplement safely. The allowlist
 * lives in the `tenant_content` profile in config/purifier.php.
 */
class HtmlSanitizer
{
    public static function clean(?string $html): string
    {
        if (blank($html)) {
            return '';
        }

        return trim(Purifier::clean($html, 'tenant_content'));
    }
}
