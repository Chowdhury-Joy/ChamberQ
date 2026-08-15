<?php

namespace App\Filament\TenantAdmin\Pages;

use App\Support\FilamentPanelUrl;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static bool $isDiscovered = false;

    public static function shouldRegisterNavigation(): bool
    {
        return ! (auth()->user()?->canViewConsultScreen() ?? false);
    }

    public function mount(): void
    {
        $consult = FilamentPanelUrl::consultScreen();

        if ($consult === null) {
            return;
        }

        $this->redirect($consult);
    }
}
