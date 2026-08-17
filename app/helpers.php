<?php

use App\Support\TenancyUrl;

if (! function_exists('public_asset')) {
    /**
     * Root-relative URL for a file under public/. Unlike asset(), this does not
     * bake in APP_URL, so clinic CSS/JS still load when `php artisan serve` runs
     * on a non-default port while APP_URL is http://localhost.
     */
    function public_asset(string $path): string
    {
        return '/' . ltrim($path, '/');
    }
}

if (! function_exists('tenant_web_url')) {
    function tenant_web_url(string $path = '/'): string
    {
        return TenancyUrl::url($path);
    }
}

if (! function_exists('tenant_safe_href')) {
    /**
     * Sanitize a tenant-authored href, then prefix same-origin paths for path tenancy
     * (e.g. /book → /solo/book on the central host; unchanged on custom domains).
     */
    function tenant_safe_href(?string $url, string $fallback = '#'): string
    {
        $safe = \App\Support\SafeUrl::href($url, $fallback);

        if ($safe === '' || $safe === '#' || str_starts_with($safe, '#')) {
            return $safe;
        }

        if (str_starts_with($safe, '/') && ! str_starts_with($safe, '//')) {
            return tenant_web_url($safe);
        }

        return $safe;
    }
}

if (! function_exists('tenant_web_route')) {
    /**
     * Tenant-aware named routes (path prefix on central domain, root on custom domains).
     * Not to be confused with stancl/tenancy's tenant_route($domain, $route, ...).
     *
     * @param  array<string, mixed>|object  $parameters
     */
    function tenant_web_route(string $name, mixed $parameters = [], bool $absolute = true): string
    {
        return TenancyUrl::route($name, $parameters, $absolute);
    }
}

if (! function_exists('clinic_nav_items')) {
    /**
     * Clinic public header / footer / drawer: Home, Services, Doctors, extra
     * published WebPages (path-prefixed), then Health tips. Skips slugs that
     * already have a dedicated item so /departments is not listed twice.
     *
     * @return list<array{label: string, href: string}>
     */
    function clinic_nav_items(): array
    {
        $items = [
            ['label' => __('Home'), 'href' => tenant_safe_href('/')],
            ['label' => __('Services'), 'href' => tenant_safe_href('/departments')],
            ['label' => __('Doctors'), 'href' => tenant_safe_href('/doctors')],
        ];

        $reserved = ['departments', 'doctors', 'blog', 'book', 'portal'];

        if (function_exists('tenant') && tenant()) {
            $pages = \App\Models\WebPage::query()
                ->where('is_published', true)
                ->orderBy('title')
                ->get(['title', 'slug']);

            foreach ($pages as $page) {
                $slug = trim((string) $page->slug, '/');
                if ($slug === '' || in_array(strtolower($slug), $reserved, true)) {
                    continue;
                }

                $items[] = [
                    'label' => (string) $page->title,
                    'href' => tenant_safe_href('/'.$slug),
                ];
            }
        }

        $items[] = ['label' => __('Health tips'), 'href' => tenant_safe_href('/blog')];

        return $items;
    }
}

if (! function_exists('bilingual')) {
    /**
     * A fixed label in Bangla (lead) and English (quiet), for prescription
     * paperwork. See \App\Support\Bilingual.
     */
    function bilingual(string $key): \Illuminate\Support\HtmlString
    {
        return \App\Support\Bilingual::html($key);
    }
}
