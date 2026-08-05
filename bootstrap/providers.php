<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\Filament\SuperAdminPanelProvider::class,
    App\Providers\Filament\MarketerPanelProvider::class,
    App\Providers\Filament\TenantAdminPanelProvider::class,
    App\Providers\Filament\TenantAdminPathPanelProvider::class,
    App\Providers\TenancyServiceProvider::class,
    // TEMPORARY diagnostic, no-ops unless AUTH_DEBUG=true. Remove with the provider.
    App\Providers\AuthDebugProvider::class,
];
