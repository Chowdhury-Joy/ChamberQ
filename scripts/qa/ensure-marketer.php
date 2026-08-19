<?php

/**
 * Idempotent local QA helper: ensure partner@demo.com can sign in at /partner.
 * Refuses production. Password is the same demo password as other seed logins.
 */

use App\Models\Marketer;
use App\Models\User;
use App\Scopes\TenantScope;
use App\Support\SeedAccounts;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

SeedAccounts::refuseProduction();

$user = SeedAccounts::upsert(
    ['email' => 'partner@demo.com'],
    [
        'name' => 'QA Partner',
        'role' => User::ROLE_MARKETER,
        'tenant_id' => null,
    ],
    'pass',
);

if (! Marketer::query()->where('user_id', $user->id)->exists()) {
    Marketer::query()->create([
        'user_id' => $user->id,
        'code' => 'qasweep',
        'display_name' => 'QA Partner',
        'setup_commission_rate' => 0.20,
        'monthly_commission_rate' => 0.10,
        'is_active' => true,
    ]);
}

fwrite(STDOUT, "ok {$user->email}\n");
