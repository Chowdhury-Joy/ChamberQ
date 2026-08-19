<?php

namespace App\Support;

use App\Models\BlogPost;
use App\Models\Chamber;
use App\Models\Doctor;
use App\Models\Tenant;
use App\Models\WebPage;
use Illuminate\Support\Str;

/**
 * Search snippets for public pages. Built from copy the chamber already has
 * (name, tagline, hero, blog excerpt) — not extra CMS SEO fields.
 */
final class PublicSeo
{
    public const MARKETING_DESCRIPTION = 'ChamberQ for solo doctors: clearer serials, a live queue you control, and fewer interruptions in the consult. We set it up with you.';

    /**
     * @param  list<array<string, mixed>>  $jsonLd
     * @return array{
     *     title: string,
     *     description: string,
     *     canonical: string,
     *     robots: string,
     *     image: ?string,
     *     siteName: string,
     *     locale: string,
     *     ogType: string,
     *     jsonLd: list<array<string, mixed>>
     * }
     */
    public static function tags(
        string $title,
        ?string $description = null,
        bool $index = true,
        ?string $image = null,
        string $ogType = 'website',
        array $jsonLd = [],
        ?string $canonical = null,
        ?string $siteName = null,
    ): array {
        $description = self::plain((string) $description);
        if ($description === '') {
            $description = self::plain($title);
        }

        return [
            'title' => $title,
            'description' => Str::limit($description, 155, ''),
            'canonical' => $canonical ?? request()->url(),
            'robots' => $index ? 'index, follow' : 'noindex, nofollow',
            'image' => self::absoluteImage($image),
            'siteName' => $siteName ?? (string) config('marketing.product_name', 'ChamberQ'),
            'locale' => str_replace('_', '-', app()->getLocale()),
            'ogType' => $ogType,
            'jsonLd' => $jsonLd,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function marketingHome(string $product): array
    {
        $url = url('/');

        return self::tags(
            title: $product.' — For your chamber practice',
            description: self::MARKETING_DESCRIPTION,
            image: '/icons/chamberq-logo.png',
            jsonLd: [[
                '@context' => 'https://schema.org',
                '@type' => 'Organization',
                'name' => $product,
                'url' => $url,
                'description' => self::MARKETING_DESCRIPTION,
                'logo' => self::absoluteImage('/icons/chamberq-logo.png'),
            ]],
            canonical: $url,
            siteName: $product,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function findPage(): array
    {
        $product = (string) config('marketing.product_name', 'ChamberQ');

        return self::tags(
            title: __('Find a doctor').' — '.$product,
            description: __('Find a ChamberQ doctor and book a serial online. No login needed.'),
            jsonLd: [[
                '@context' => 'https://schema.org',
                '@type' => 'WebSite',
                'name' => $product,
                'url' => url('/'),
            ]],
            siteName: $product,
        );
    }

    /**
     * @param  list<array<string, mixed>>  $jsonLd
     * @return array<string, mixed>
     */
    public static function tenantPage(
        Tenant $tenant,
        string $title,
        ?string $description = null,
        bool $index = true,
        ?string $image = null,
        string $ogType = 'website',
        array $jsonLd = [],
    ): array {
        $desc = $description
            ?: (filled($tenant->tagline) ? (string) $tenant->tagline : null)
            ?: __('Book a serial online with :name.', ['name' => $tenant->displayName()]);

        return self::tags(
            title: $title,
            description: $desc,
            index: $index,
            image: $image ?: ($tenant->logo_url ?: $tenant->faviconHref()),
            ogType: $ogType,
            jsonLd: $jsonLd,
            siteName: $tenant->displayName(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function tenantHome(Tenant $tenant, WebPage $page): array
    {
        return self::tenantPage(
            $tenant,
            $page->title.' | '.$tenant->displayName(),
            self::pageDescription($page, $tenant),
            true,
            self::pageImage($page, $tenant),
            'website',
            [self::practiceGraph($tenant)],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function practiceGraph(Tenant $tenant): array
    {
        $graph = [
            '@context' => 'https://schema.org',
            '@type' => $tenant->isSoloDoctor() ? 'Physician' : 'MedicalClinic',
            'name' => $tenant->displayName(),
            'url' => request()->url(),
        ];

        if (filled($tenant->tagline)) {
            $graph['description'] = self::plain((string) $tenant->tagline);
        }

        if (filled($tenant->contact_phone)) {
            $graph['telephone'] = (string) $tenant->contact_phone;
        }

        $logo = self::absoluteImage($tenant->logo_url ?: $tenant->faviconHref());
        if ($logo) {
            $graph['image'] = $logo;
        }

        $chamber = Chamber::query()->orderBy('id')->first();
        if ($chamber && filled($chamber->address)) {
            $graph['address'] = [
                '@type' => 'PostalAddress',
                'streetAddress' => (string) $chamber->address,
                'addressCountry' => 'BD',
            ];
        }

        return $graph;
    }

    /**
     * @return array<string, mixed>
     */
    public static function articleGraph(BlogPost $post, Tenant $tenant): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BlogPosting',
            'headline' => $post->title,
            'description' => self::plain((string) ($post->excerpt ?: $post->title)),
            'datePublished' => optional($post->published_at ?? $post->created_at)?->toAtomString(),
            'dateModified' => optional($post->updated_at)?->toAtomString(),
            'author' => [
                '@type' => 'Organization',
                'name' => $tenant->displayName(),
            ],
            'mainEntityOfPage' => request()->url(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function physicianGraph(Doctor $doctor, Tenant $tenant): array
    {
        $graph = [
            '@context' => 'https://schema.org',
            '@type' => 'Physician',
            'name' => $doctor->name,
            'url' => request()->url(),
            'worksFor' => [
                '@type' => 'MedicalClinic',
                'name' => $tenant->displayName(),
            ],
        ];

        $specialty = $doctor->websiteSpecialtyLabel();
        if ($specialty !== '') {
            $graph['medicalSpecialty'] = $specialty;
        }

        if (filled($doctor->bio)) {
            $graph['description'] = Str::limit(self::plain((string) $doctor->bio), 155, '');
        }

        $photo = self::absoluteImage($doctor->photo_url);
        if ($photo) {
            $graph['image'] = $photo;
        }

        return $graph;
    }

    public static function pageDescription(WebPage $page, Tenant $tenant): string
    {
        if (filled($tenant->tagline)) {
            return (string) $tenant->tagline;
        }

        foreach ((array) $page->content as $block) {
            if (! is_array($block) || ! empty($block['data']['is_hidden'])) {
                continue;
            }

            $data = is_array($block['data'] ?? null) ? $block['data'] : [];
            foreach (['subheadline', 'headline', 'content', 'body'] as $key) {
                $text = self::plain((string) ($data[$key] ?? ''));
                if ($text !== '') {
                    return $text;
                }
            }
        }

        return __('Book a serial online with :name.', ['name' => $tenant->displayName()]);
    }

    public static function pageImage(WebPage $page, Tenant $tenant): ?string
    {
        foreach ((array) $page->content as $block) {
            if (! is_array($block)) {
                continue;
            }

            $url = $block['data']['image_url'] ?? null;
            if (is_string($url) && $url !== '') {
                return $url;
            }
        }

        return $tenant->logo_url ?: $tenant->faviconHref();
    }

    public static function plain(?string $value): string
    {
        $value = html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    }

    public static function absoluteImage(?string $href): ?string
    {
        $href = SafeUrl::href($href, '');
        if ($href === '' || $href === '#') {
            return null;
        }

        if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://')) {
            return $href;
        }

        return rtrim(request()->getSchemeAndHttpHost(), '/').'/'.ltrim($href, '/');
    }
}
