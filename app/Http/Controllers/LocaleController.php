<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function switch(Request $request, string $locale): RedirectResponse
    {
        if (in_array($locale, ['en', 'bn'], true)) {
            session()->put('locale', $locale);
        }

        $referer = (string) $request->headers->get('referer');
        $sameHost = $referer !== ''
            && parse_url($referer, PHP_URL_HOST) === $request->getHost();

        $fallback = tenant() ? tenant_web_url('/') : '/find';

        return redirect()->to($sameHost ? $referer : $fallback);
    }
}
