<?php

namespace App\Filament\TenantAdmin\Resources\Concerns;

trait ClinicWebsiteResource
{
    public static function canViewAny(): bool
    {
        return (auth()->user()?->canManageContent() ?? false)
            && ! tenant()?->isSoloDoctor()
            && (tenant()?->hasFrontDoor() ?? false);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    protected static string|\UnitEnum|null $navigationGroup = 'Website';
}
