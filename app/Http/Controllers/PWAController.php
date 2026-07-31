<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Str;

class PWAController extends Controller
{
    private const DEFAULT_THEME = '#2563eb';

    public function manifest()
    {
        $tenant = tenant();
        $name = $tenant?->displayName() ?? config('app.name');
        $theme = $tenant?->theme_color ?: self::DEFAULT_THEME;
        $scope = tenant_web_url('/');

        return response()->json([
            'name' => $name,
            // Home-screen labels get truncated by the OS anyway; cut on a word
            // boundary so it reads as a name rather than "Shefa Diagno".
            'short_name' => Str::limit($name, 15, ''),
            'start_url' => $scope,
            'scope' => $scope,
            'display' => 'standalone',
            'background_color' => '#ffffff',
            'theme_color' => $theme,
            'icons' => [
                [
                    'src' => tenant_web_route('pwa.icon', ['size' => 192]),
                    'sizes' => '192x192',
                    'type' => 'image/svg+xml',
                    'purpose' => 'any',
                ],
                [
                    'src' => tenant_web_route('pwa.icon', ['size' => 512]),
                    'sizes' => '512x512',
                    'type' => 'image/svg+xml',
                    'purpose' => 'any',
                ],
            ],
        ])->header('Content-Type', 'application/manifest+json');
    }

    /**
     * A tenant-branded icon rendered as SVG.
     */
    public function icon(int $size): Response
    {
        $tenant = tenant();
        $theme = $tenant?->theme_color ?: self::DEFAULT_THEME;
        if (! preg_match('/^#[0-9a-fA-F]{6}$/', $theme)) {
            $theme = self::DEFAULT_THEME;
        }

        $initial = htmlspecialchars(
            mb_strtoupper(mb_substr($tenant?->displayName() ?? 'C', 0, 1)),
            ENT_XML1 | ENT_QUOTES,
            'UTF-8'
        );

        $size = max(16, min(512, $size));

        $svg = <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" width="{$size}" height="{$size}" viewBox="0 0 512 512" role="img">
            <rect width="512" height="512" rx="96" fill="{$theme}"/>
            <text x="50%" y="50%" dy="0.35em" text-anchor="middle"
                  font-family="system-ui, -apple-system, sans-serif"
                  font-size="256" font-weight="700" fill="#ffffff">{$initial}</text>
        </svg>
        SVG;

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    public function serviceWorker(): Response
    {
        $scopePrefix = tenant_web_url('');
        $scopePrefixJs = json_encode(rtrim($scopePrefix, '/') ?: '');

        $sw = <<<JS
const CACHE_NAME = 'clinic-shell-v3';
const SCOPE_PREFIX = {$scopePrefixJs};
const PRECACHE = ['/css/theme.css'];

self.addEventListener('install', event => {
    event.waitUntil((async () => {
        const cache = await caches.open(CACHE_NAME);
        await Promise.all(PRECACHE.map(url => cache.add(url).catch(() => {})));
        await self.skipWaiting();
    })());
});

self.addEventListener('activate', event => {
    event.waitUntil((async () => {
        const names = await caches.keys();
        await Promise.all(names.filter(n => n !== CACHE_NAME).map(n => caches.delete(n)));
        await self.clients.claim();
    })());
});

function scopedApiPath(pathname) {
    if (!SCOPE_PREFIX) {
        return pathname.startsWith('/api/');
    }
    return pathname.startsWith(SCOPE_PREFIX + '/api/');
}

self.addEventListener('fetch', event => {
    const request = event.request;

    if (request.method !== 'GET') return;

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;

    if (request.mode === 'navigate' || scopedApiPath(url.pathname)) {
        event.respondWith(
            fetch(request)
                .then(response => {
                    if (request.mode === 'navigate' && response.ok) {
                        const copy = response.clone();
                        caches.open(CACHE_NAME).then(c => c.put(request, copy));
                    }
                    return response;
                })
                .catch(() => caches.match(request))
        );
        return;
    }

    event.respondWith(
        caches.match(request).then(cached => cached || fetch(request))
    );
});
JS;

        return response($sw, 200, [
            'Content-Type' => 'application/javascript',
            'Cache-Control' => 'no-cache',
        ]);
    }
}
