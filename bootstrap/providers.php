<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\Filament\SuperAdminPanelProvider::class,
    App\Providers\Filament\MarketerPanelProvider::class,
    App\Providers\Filament\TenantAdminPanelProvider::class,
    App\Providers\Filament\TenantAdminPathPanelProvider::class,
    App\Providers\TenancyServiceProvider::class,
    // Sign-out diagnostics, off unless AUTH_DEBUG=true (config/diagnostics.php).
    App\Providers\AuthDebugProvider::class,
];
