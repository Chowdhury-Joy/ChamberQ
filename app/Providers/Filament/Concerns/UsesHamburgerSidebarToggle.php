<?php

namespace App\Providers\Filament\Concerns;

use Filament\Panel;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsIconAlias;

trait UsesHamburgerSidebarToggle
{
    protected function withHamburgerSidebarToggle(Panel $panel): Panel
    {
        return $panel->icons([
            PanelsIconAlias::SIDEBAR_COLLAPSE_BUTTON => Heroicon::OutlinedBars3,
            PanelsIconAlias::SIDEBAR_COLLAPSE_BUTTON_RTL => Heroicon::OutlinedBars3,
            PanelsIconAlias::SIDEBAR_EXPAND_BUTTON => Heroicon::OutlinedBars3,
            PanelsIconAlias::SIDEBAR_EXPAND_BUTTON_RTL => Heroicon::OutlinedBars3,
        ]);
    }
}
