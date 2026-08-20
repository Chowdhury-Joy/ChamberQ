<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Chamber extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'name',
        'address',
        'map_url',
        'review_url',
        'hours',
        'contact',
    ];

    protected $casts = [
        'hours' => 'array',
    ];

    /**
     * Google Maps link for WhatsApp / ticket handoff (Fatima’s husband needs directions).
     *
     * Staff paste the share link straight from Google Maps; when they have not,
     * we still hand the patient a search on the chamber address.
     */
    public function googleMapsUrl(): ?string
    {
        $url = trim((string) ($this->map_url ?? ''));
        if ($url !== '' && self::isGoogleMapsUrl($url)) {
            return $url;
        }

        $address = trim((string) ($this->address ?? ''));
        if ($address !== '') {
            return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($address);
        }

        return null;
    }

    /**
     * Accept only real Google Maps links, so a pasted link cannot send patients
     * to an arbitrary host from the ticket or the WhatsApp share text.
     */
    public static function isGoogleMapsUrl(?string $url): bool
    {
        $url = trim((string) $url);
        if ($url === '') {
            return false;
        }

        $parts = parse_url($url);
        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return false;
        }

        if (! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return false;
        }

        $host = strtolower($parts['host']);
        $host = str_starts_with($host, 'www.') ? substr($host, 4) : $host;

        // Short share links produced by the Google Maps mobile apps.
        if (in_array($host, ['maps.app.goo.gl', 'goo.gl', 'maps.google.com'], true)) {
            return true;
        }

        // google.com/maps, google.com.bd/maps, google.co.uk/maps, …
        if (preg_match('/^google\.[a-z]{2,3}(\.[a-z]{2,3})?$/', $host) === 1) {
            return str_starts_with(strtolower($parts['path'] ?? ''), '/maps');
        }

        return false;
    }

    /**
     * Google "Ask for reviews" link staff may send after a visit.
     *
     * Chamber paste wins; otherwise the practice Branding link. Empty or a
     * non-Google host is treated as unset so a bad paste cannot leave the Google
     * family.
     */
    public static function reviewUrlForBooking(Booking $booking): ?string
    {
        $booking->loadMissing(['bookable']);

        $chamber = null;
        if ($booking->bookable instanceof ScheduleSession) {
            $booking->bookable->loadMissing(['chamber']);
            $chamber = $booking->bookable->chamber;
        }

        $fromChamber = self::sanitisedReviewUrl($chamber?->review_url ?? null);
        if ($fromChamber !== null) {
            return $fromChamber;
        }

        $tenant = Tenant::find($booking->tenant_id);

        return self::sanitisedReviewUrl($tenant?->review_url ?? null);
    }

    public static function sanitisedReviewUrl(?string $url): ?string
    {
        $url = trim((string) $url);

        return self::isGoogleReviewUrl($url) ? $url : null;
    }

    /**
     * Accept Google review / Maps share hosts only — g.page, writereview, or
     * the same Maps allowlist as directions.
     */
    public static function isGoogleReviewUrl(?string $url): bool
    {
        $url = trim((string) $url);
        if ($url === '') {
            return false;
        }

        if (self::isGoogleMapsUrl($url)) {
            return true;
        }

        $parts = parse_url($url);
        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return false;
        }

        if (! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return false;
        }

        $host = strtolower($parts['host']);
        $host = str_starts_with($host, 'www.') ? substr($host, 4) : $host;

        if ($host === 'g.page') {
            return true;
        }

        if ($host === 'search.google.com') {
            return str_starts_with(strtolower($parts['path'] ?? ''), '/local/writereview');
        }

        return false;
    }
}
