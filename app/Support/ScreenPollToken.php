<?php

namespace App\Support;

/**
 * Shared secret for outdoor-screen JSON polls so session ids cannot be scraped.
 */
final class ScreenPollToken
{
    public static function forSession(int $scheduleSessionId): string
    {
        return self::digest('session', (string) $scheduleSessionId);
    }

    public static function forChamber(int $chamberId): string
    {
        return self::digest('chamber', (string) $chamberId);
    }

    public static function matchesSession(int $scheduleSessionId, ?string $token): bool
    {
        return self::matches(self::forSession($scheduleSessionId), $token);
    }

    public static function matchesChamber(int $chamberId, ?string $token): bool
    {
        return self::matches(self::forChamber($chamberId), $token);
    }

    private static function digest(string $kind, string $id): string
    {
        return hash_hmac(
            'sha256',
            (string) tenant('id').'|'.$kind.'|'.$id,
            (string) config('app.key'),
        );
    }

    private static function matches(string $expected, ?string $token): bool
    {
        return filled($token) && hash_equals($expected, (string) $token);
    }
}
