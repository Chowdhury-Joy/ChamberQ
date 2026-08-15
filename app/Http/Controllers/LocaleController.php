<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function switch(Request $request, string $locale): RedirectResponse
    {
        if (in_array($locale, ['en', 'bn'], true)) {
            session()->put('locale', $locale);
        }

        return redirect()->to($this->redirectAfterSwitch($request));
    }

    private function redirectAfterSwitch(Request $request): string
    {
        $referer = (string) $request->headers->get('referer');
        $sameHost = $referer !== ''
            && parse_url($referer, PHP_URL_HOST) === $request->getHost();

        if ($sameHost) {
            return $referer;
        }

        // Staff Switch to Bangla is a GET from the Filament user menu. Some
        // browsers omit Referer; dumping them onto the public homepage looks
        // like the switch "did nothing" or kicked them out of the desk.
        if (tenancy()->initialized && $this->signedInChamberStaff($request)) {
            return tenant_web_url('/admin');
        }

        return tenant() ? tenant_web_url('/') : '/find';
    }

    private function signedInChamberStaff(Request $request): bool
    {
        $user = $request->user();

        return $user instanceof User
            && $user->belongsToCurrentTenant()
            && in_array($user->role, User::TENANT_PANEL_ROLES, true);
    }
}
