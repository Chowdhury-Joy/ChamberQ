<?php

namespace App\Support;

use App\Models\BlogPost;
use App\Models\Department;
use App\Models\Doctor;
use App\Models\Tenant;
use App\Models\WebPage;
use Throwable;

final class PublicSitemap
{
    public static function centralXml(): string
    {
        $origin = request()->getSchemeAndHttpHost();
        $urls = [
            ['loc' => $origin.'/'],
            ['loc' => $origin.'/find'],
        ];

        $tenants = Tenant::query()->orderBy('id')->get();

        foreach ($tenants as $tenant) {
            if (! $tenant->hasFrontDoor()) {
                continue;
            }

            if ($tenant->domains()->exists()) {
                continue;
            }

            $prefix = $origin.'/'.$tenant->id;

            try {
                tenancy()->initialize($tenant);
                $urls = array_merge($urls, self::tenantLocs($prefix));
            } catch (Throwable) {
                $urls[] = ['loc' => $prefix.'/'];
            } finally {
                tenancy()->end();
            }
        }

        return self::urlset($urls);
    }

    public static function tenantXml(): string
    {
        $tenant = tenant();
        abort_unless($tenant instanceof Tenant && $tenant->hasFrontDoor(), 404);

        $origin = request()->getSchemeAndHttpHost();
        $prefix = TenancyUrl::usesPathPrefix()
            ? $origin.'/'.$tenant->id
            : $origin;

        return self::urlset(self::tenantLocs($prefix));
    }

    /**
     * @return list<array{loc: string, lastmod?: string}>
     */
    public static function tenantLocs(string $prefix): array
    {
        $prefix = rtrim($prefix, '/');
        $urls = [
            ['loc' => $prefix.'/'],
            ['loc' => $prefix.'/book'],
        ];

        foreach (WebPage::query()->where('is_published', true)->orderBy('slug')->get() as $page) {
            $slug = trim((string) $page->slug, '/');
            if ($slug === '') {
                continue;
            }

            $urls[] = [
                'loc' => $prefix.'/'.$slug,
                'lastmod' => optional($page->updated_at)?->toAtomString() ?? '',
            ];
        }

        $tenant = tenant();
        if ($tenant instanceof Tenant && ! $tenant->isSoloDoctor()) {
            $urls[] = ['loc' => $prefix.'/departments'];
            $urls[] = ['loc' => $prefix.'/doctors'];
            $urls[] = ['loc' => $prefix.'/blog'];

            foreach (Department::published()->ordered()->get() as $department) {
                $urls[] = [
                    'loc' => $prefix.'/departments/'.$department->slug,
                    'lastmod' => optional($department->updated_at)?->toAtomString() ?? '',
                ];
            }

            foreach (Doctor::publishedOnWebsite()->get() as $doctor) {
                $urls[] = [
                    'loc' => $prefix.'/doctors/'.$doctor->public_slug,
                    'lastmod' => optional($doctor->updated_at)?->toAtomString() ?? '',
                ];
            }

            foreach (BlogPost::published()->ordered()->get() as $post) {
                $urls[] = [
                    'loc' => $prefix.'/blog/'.$post->slug,
                    'lastmod' => optional($post->updated_at ?? $post->published_at)?->toAtomString() ?? '',
                ];
            }
        }

        return $urls;
    }

    /**
     * @param  list<array{loc: string, lastmod?: string}>  $urls
     */
    public static function urlset(array $urls): string
    {
        $body = '';
        $seen = [];

        foreach ($urls as $url) {
            $loc = $url['loc'] ?? '';
            if ($loc === '' || isset($seen[$loc])) {
                continue;
            }
            $seen[$loc] = true;

            $body .= "  <url>\n    <loc>".self::xml($loc)."</loc>\n";
            if (! empty($url['lastmod'])) {
                $body .= '    <lastmod>'.self::xml($url['lastmod'])."</lastmod>\n";
            }
            $body .= "  </url>\n";
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n"
            .$body
            .'</urlset>';
    }

    private static function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
