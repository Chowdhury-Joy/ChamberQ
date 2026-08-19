<?php

namespace App\Filament\TenantAdmin\Pages;

use App\Support\FilamentPanelUrl;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static bool $isDiscovered = false;

    public static function shouldRegisterNavigation(): bool
    {
        return ! (auth()->user()?->landsOnConsultScreen() ?? false);
    }

    public function mount(): void
    {
        $surface = FilamentPanelUrl::workingSurface();

        if ($surface === null) {
            return;
        }

        $this->redirect($surface);
    }
}
