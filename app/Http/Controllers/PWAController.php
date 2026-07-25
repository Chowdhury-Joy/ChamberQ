<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PWAController extends Controller
{
    public function manifest()
    {
        $tenantId = tenant('id') ?? 'Doctor Gemini';
        
        $manifest = [
            'name' => "{$tenantId} Clinic",
            'short_name' => "{$tenantId}",
            'start_url' => '/',
            'display' => 'standalone',
            'background_color' => '#ffffff',
            'theme_color' => '#0ea5e9',
            'icons' => [
                [
                    'src' => '/icon-192.png',
                    'sizes' => '192x192',
                    'type' => 'image/png',
                ],
                [
                    'src' => '/icon-512.png',
                    'sizes' => '512x512',
                    'type' => 'image/png',
                ]
            ]
        ];

        return response()->json($manifest)->header('Content-Type', 'application/manifest+json');
    }

    public function serviceWorker()
    {
        $sw = <<<JS
const CACHE_NAME = 'tenant-queue-v1';
const URLS_TO_CACHE = [
    '/css/theme.css',
    '/book',
    '/'
];

self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => cache.addAll(URLS_TO_CACHE))
    );
});

self.addEventListener('fetch', event => {
    // For API requests (queue status), try network first, then cache
    if (event.request.url.includes('/api/')) {
        event.respondWith(
            fetch(event.request).catch(() => caches.match(event.request))
        );
        return;
    }
    
    // For static pages, cache first, then network
    event.respondWith(
        caches.match(event.request)
            .then(response => {
                if (response) return response;
                return fetch(event.request);
            })
    );
});
JS;

        return response($sw)->header('Content-Type', 'application/javascript');
    }
}
