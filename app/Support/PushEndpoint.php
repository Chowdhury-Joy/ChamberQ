<?php

namespace App\Support;

/**
 * Whether a Web Push subscription endpoint is safe for this server to POST to.
 *
 * The endpoint is written by whoever calls the subscribe route, and booking a
 * serial is public — so anyone can obtain a booking id, register an endpoint,
 * and have the queue make the server issue an HTTP request to a URL of their
 * choosing the next time the line moves. `url` validation alone accepted
 * `http://169.254.169.254/…` and `http://127.0.0.1:6379/…`: a blind SSRF into
 * the cloud metadata service and every service bound to loopback.
 *
 * Real push endpoints are always https on a public hostname (FCM, Mozilla,
 * Apple, WNS), so the rules below cost nothing legitimate.
 *
 * Residual risk, accepted deliberately: a public hostname whose DNS record
 * points at a private address still passes. Closing that needs resolution at
 * connect time, not at validation time — a check here would only be a race, and
 * would make subscribing depend on DNS being up.
 */
final class PushEndpoint
{
    /**
     * Hostname suffixes that never belong to a public push service.
     *
     * @var list<string>
     */
    private const PRIVATE_HOST_SUFFIXES = [
        '.localhost',
        '.local',
        '.internal',
        '.intranet',
        '.home.arpa',
    ];

    /**
     * Validation rule for a subscribe request body.
     */
    public static function rule(): \Closure
    {
        return static function (string $attribute, mixed $value, \Closure $fail): void {
            if (! self::isAllowed(is_string($value) ? $value : null)) {
                $fail(__('That notification endpoint is not accepted.'));
            }
        };
    }

    public static function isAllowed(?string $endpoint): bool
    {
        if (! is_string($endpoint) || $endpoint === '') {
            return false;
        }

        $parts = parse_url($endpoint);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return false;
        }

        // Push delivery is https everywhere. Anything else is either a
        // downgrade or a probe at a non-HTTP service.
        if (strtolower($parts['scheme']) !== 'https') {
            return false;
        }

        // `https://user:pass@host/` — credentials in an endpoint are never a
        // push service, and they smuggle a different host past naive parsers.
        if (isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        if (isset($parts['port']) && (int) $parts['port'] !== 443) {
            return false;
        }

        return self::hostIsPublic($parts['host']);
    }

    private static function hostIsPublic(string $host): bool
    {
        $host = strtolower(trim($host, '[]'));

        if ($host === '' || $host === 'localhost') {
            return false;
        }

        foreach (self::PRIVATE_HOST_SUFFIXES as $suffix) {
            if (str_ends_with($host, $suffix)) {
                return false;
            }
        }

        // A literal IP is never how a browser hands back a push endpoint, and
        // it is exactly how an internal target is reached. Reject the private
        // and reserved ranges outright; a public literal still has no
        // legitimate use here, but the filter below is the part that matters.
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return filter_var(
                $host,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            ) !== false;
        }

        // Must look like a real DNS name: at least one dot, no single-label
        // hosts (which resolve through the server's own search domain).
        return str_contains($host, '.');
    }
}
