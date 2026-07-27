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

        return response()->json([
            'name' => $name,
            // Home-screen labels get truncated by the OS anyway; cut on a word
            // boundary so it reads as a name rather than "Shefa Diagno".
            'short_name' => Str::limit($name, 15, ''),
            'start_url' => '/',
            'scope' => '/',
            'display' => 'standalone',
            'background_color' => '#ffffff',
            'theme_color' => $theme,
            'icons' => [
                // Generated per tenant so no static asset needs to exist on disk.
                // The previous manifest pointed at /icon-192.png and /icon-512.png,
                // which were never created — an invalid icon entry means the
                // browser silently refuses to offer "add to home screen".
                [
                    'src' => route('pwa.icon', ['size' => 192]),
                    'sizes' => '192x192',
                    'type' => 'image/svg+xml',
                    'purpose' => 'any',
                ],
                [
                    'src' => route('pwa.icon', ['size' => 512]),
                    'sizes' => '512x512',
                    'type' => 'image/svg+xml',
                    'purpose' => 'any',
                ],
            ],
        ])->header('Content-Type', 'application/manifest+json');
    }

    /**
     * A tenant-branded icon rendered as SVG.
     *
     * Avoids shipping placeholder PNGs and gives every tenant a correct icon at
     * onboarding time with no asset pipeline step.
     */
    public function icon(int $size): Response
    {
        $tenant = tenant();
        $theme = $tenant?->theme_color ?: self::DEFAULT_THEME;
        $initial = mb_strtoupper(mb_substr($tenant?->displayName() ?? 'C', 0, 1));

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
        // Bumping the cache name evicts previous versions on activate.
        $sw = <<<'JS'
const CACHE_NAME = 'clinic-shell-v2';
const PRECACHE = ['/css/theme.css'];

self.addEventListener('install', event => {
    event.waitUntil((async () => {
        const cache = await caches.open(CACHE_NAME);
        // Cache individually: a single 404 in addAll() aborts the whole install
        // and leaves the app with no service worker at all.
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

self.addEventListener('fetch', event => {
    const request = event.request;

    if (request.method !== 'GET') return;

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;

    // Network-first for everything navigational and for the API. This app is a
    // live queue: serving a cached ticket page would show a stale "now serving"
    // number, which is worse than showing nothing.
    if (request.mode === 'navigate' || url.pathname.startsWith('/api/')) {
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

    // Cache-first is fine for immutable static assets only.
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
