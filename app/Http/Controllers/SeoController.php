<?php

namespace App\Http\Controllers;

use App\Support\PublicSitemap;
use App\Support\TenancyUrl;
use Symfony\Component\HttpFoundation\Response;

class SeoController extends Controller
{
    public function robots(): Response
    {
        $origin = request()->getSchemeAndHttpHost();
        $prefix = TenancyUrl::pathPrefix();

        $lines = tenant()
            ? $this->tenantRobots($origin, $prefix)
            : $this->centralRobots($origin);

        return response(implode("\n", $lines)."\n", 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    public function sitemap(): Response
    {
        $xml = tenant() ? PublicSitemap::tenantXml() : PublicSitemap::centralXml();

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }

    /**
     * @return list<string>
     */
    private function centralRobots(string $origin): array
    {
        return [
            'User-agent: *',
            'Allow: /',
            'Disallow: /admin',
            'Disallow: /partner',
            'Disallow: /me',
            'Disallow: /livewire',
            'Disallow: /filament',
            'Disallow: /*/admin',
            'Disallow: /*/portal',
            'Disallow: /*/bookings',
            'Disallow: /*/screen',
            'Disallow: /*/p/',
            'Disallow: /*/api',
            'Disallow: /*/livewire',
            'Sitemap: '.$origin.'/sitemap.xml',
        ];
    }

    /**
     * @return list<string>
     */
    private function tenantRobots(string $origin, string $prefix): array
    {
        return [
            'User-agent: *',
            'Allow: /',
            'Disallow: '.$prefix.'/admin',
            'Disallow: '.$prefix.'/portal',
            'Disallow: '.$prefix.'/bookings',
            'Disallow: '.$prefix.'/screen',
            'Disallow: '.$prefix.'/p/',
            'Disallow: '.$prefix.'/api',
            'Disallow: '.$prefix.'/livewire',
            'Sitemap: '.$origin.$prefix.'/sitemap.xml',
        ];
    }
}
