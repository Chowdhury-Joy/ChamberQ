<?php

namespace App\Http\Responses;

use App\Support\FilamentPanelUrl;
use Filament\Auth\Http\Responses\Contracts\LoginResponse as LoginResponseContract;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

/**
 * Filament's own LoginResponse redirects to `Filament::getUrl()`, which for the
 * path tenant panel resolves to the raw path pattern `{tenant}/admin` — so a
 * successful login landed on `/%7Btenant%7D/admin` and 404'd.
 */
class FilamentLoginResponse implements LoginResponseContract
{
    public function toResponse($request): RedirectResponse | Redirector
    {
        return redirect()->intended(FilamentPanelUrl::home());
    }
}
