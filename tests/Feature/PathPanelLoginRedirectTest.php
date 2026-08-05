<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Support\FilamentPanelUrl;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Signing in on the path panel used to land on `/%7Btenant%7D/admin` — Filament's
 * login redirect targets `Panel::getUrl()`, which resolves a `home` route that
 * Filament never registers and so falls back to the raw path *pattern*
 * `{tenant}/admin`. Harmless for `admin` / `partner`; a 404 for this panel.
 */
class PathPanelLoginRedirectTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['id' => 'solo', 'plan_tier' => 'solo']);

        User::create([
            'name' => 'Solo Admin',
            'email' => 'admin@solo.com',
            'password' => Hash::make('password'),
            'role' => User::ROLE_ADMIN,
            'tenant_id' => 'solo',
        ]);
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    public function test_signing_in_lands_on_the_tenants_dashboard_not_the_route_pattern(): void
    {
        tenancy()->initialize($this->tenant);
        Filament::setCurrentPanel(Filament::getPanel('tenantAdminPath'));

        // What `SetPathTenantUrlDefaults` supplies on a real `/{tenant}/admin`
        // request, so the panel's own links (password reset, etc.) can render.
        URL::defaults(['tenant' => 'solo']);

        $response = Livewire::test(Login::class)
            ->fillForm([
                'email' => 'admin@solo.com',
                'password' => 'password',
            ])
            ->call('authenticate');

        $response->assertHasNoFormErrors();

        $target = $response->effects['redirect'] ?? null;

        $this->assertNotNull($target, 'Login did not redirect anywhere.');
        $this->assertStringNotContainsString('{tenant}', rawurldecode($target));
        $this->assertStringContainsString('/solo/admin', $target);
        $this->assertAuthenticated();
    }

    public function test_panel_home_url_resolves_the_tenant_slug(): void
    {
        tenancy()->initialize($this->tenant);

        $panel = Filament::getPanel('tenantAdminPath');
        Filament::setCurrentPanel($panel);

        $home = FilamentPanelUrl::home($panel);

        $this->assertStringNotContainsString('{tenant}', rawurldecode($home));
        $this->assertStringContainsString('/solo/admin', $home);
    }

    public function test_central_panels_still_resolve_their_own_paths(): void
    {
        $superAdmin = Filament::getPanel('superAdmin');
        Filament::setCurrentPanel($superAdmin);
        $this->assertStringContainsString('/admin', FilamentPanelUrl::home($superAdmin));

        $marketer = Filament::getPanel('marketer');
        Filament::setCurrentPanel($marketer);
        $this->assertStringContainsString('/partner', FilamentPanelUrl::home($marketer));
    }
}
