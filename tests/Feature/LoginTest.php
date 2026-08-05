<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $soloTenant;
    private Tenant $clinicTenant;
    private User $superAdmin;
    private User $soloAdmin;
    private User $clinicAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'super@demo.com',
            'password' => Hash::make('password'),
            'role' => 'super_admin',
            'tenant_id' => null,
        ]);

        $this->soloTenant = Tenant::create(['id' => 'solo', 'plan_tier' => 'solo']);
        Domain::create(['domain' => 'solo.localhost', 'tenant_id' => 'solo']);
        $this->soloAdmin = User::create([
            'name' => 'Solo Admin',
            'email' => 'admin@solo.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'tenant_id' => 'solo',
        ]);

        $this->clinicTenant = Tenant::create(['id' => 'demo', 'plan_tier' => 'clinic']);
        Domain::create(['domain' => 'demo.localhost', 'tenant_id' => 'demo']);
        $this->clinicAdmin = User::create([
            'name' => 'Demo Admin',
            'email' => 'admin@demo.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'tenant_id' => 'demo',
        ]);
    }

    public function test_super_admin_can_access_central_admin_login(): void
    {
        $response = $this->get('http://localhost/admin/login');
        $response->assertStatus(200);
    }

    public function test_solo_admin_can_access_tenant_admin_login(): void
    {
        $response = $this->get('http://solo.localhost/admin/login');
        $response->assertStatus(200);
    }

    public function test_clinic_admin_can_access_tenant_admin_login(): void
    {
        $response = $this->get('http://demo.localhost/admin/login');
        $response->assertStatus(200);
    }

    public function test_solo_admin_user_resolves_under_solo_subdomain_request(): void
    {
        $this->get('http://solo.localhost/admin/login');

        $user = User::where('email', 'admin@solo.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals('solo', $user->tenant_id);
    }

    public function test_clinic_admin_user_resolves_under_clinic_subdomain_request(): void
    {
        $this->get('http://demo.localhost/admin/login');

        $user = User::where('email', 'admin@demo.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals('demo', $user->tenant_id);
    }

    public function test_solo_admin_can_authenticate_on_solo_subdomain(): void
    {
        $this->get('http://solo.localhost/admin/login');

        \Livewire\Livewire::test(\Filament\Auth\Pages\Login::class)
            ->fillForm([
                'email' => 'admin@solo.com',
                'password' => 'password',
            ])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticatedAs($this->soloAdmin);
    }

    public function test_clinic_admin_can_authenticate_on_clinic_subdomain(): void
    {
        $this->get('http://demo.localhost/admin/login');

        \Livewire\Livewire::test(\Filament\Auth\Pages\Login::class)
            ->fillForm([
                'email' => 'admin@demo.com',
                'password' => 'password',
            ])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticatedAs($this->clinicAdmin);
    }

    public function test_super_admin_can_authenticate_on_central_domain(): void
    {
        $this->get('http://localhost/admin/login');

        \Livewire\Livewire::test(\Filament\Auth\Pages\Login::class)
            ->fillForm([
                'email' => 'super@demo.com',
                'password' => 'password',
            ])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticatedAs($this->superAdmin);
    }

    public function test_super_admin_dashboard_loads_without_tenant_scope_error(): void
    {
        $this->actingAs($this->superAdmin);

        $this->get('http://localhost/admin')->assertOk();

        \Livewire\Livewire::test(\App\Filament\SuperAdmin\Widgets\SuperAdminStatsOverview::class)
            ->assertOk()
            ->assertSee('Total Platform Bookings');
    }

    public function test_password_reset_request_pages_are_available(): void
    {
        $this->get('http://localhost/admin/password-reset/request')->assertOk();
        $this->get('http://solo.localhost/admin/password-reset/request')->assertOk();
    }

    public function test_solo_admin_can_access_central_platform_path_login(): void
    {
        $this->get('http://localhost/solo/admin/login')->assertOk();
    }

    public function test_unauthenticated_solo_admin_redirects_to_path_login(): void
    {
        $response = $this->get('http://localhost/solo/admin');

        $response->assertRedirect();
        $this->assertStringContainsString('/solo/admin/login', (string) $response->headers->get('Location'));
    }

    public function test_solo_live_queue_livewire_refresh_does_not_419_on_tenant_domain(): void
    {
        $this->get('http://solo.localhost/admin/login');

        \Livewire\Livewire::test(\Filament\Auth\Pages\Login::class)
            ->fillForm([
                'email' => 'admin@solo.com',
                'password' => 'password',
            ])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        \Livewire\Livewire::test(\App\Filament\TenantAdmin\Pages\LiveQueueControl::class)
            ->call('$refresh')
            ->assertOk();
    }

    public function test_solo_schedule_sessions_livewire_refresh_does_not_419_on_tenant_domain(): void
    {
        $this->get('http://solo.localhost/admin/login');

        \Livewire\Livewire::test(\Filament\Auth\Pages\Login::class)
            ->fillForm([
                'email' => 'admin@solo.com',
                'password' => 'password',
            ])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        \Livewire\Livewire::test(\App\Filament\TenantAdmin\Resources\ScheduleSessions\Pages\ListScheduleSessions::class)
            ->call('$refresh')
            ->assertOk();
    }

    public function test_solo_schedule_sessions_livewire_refresh_does_not_419_on_path_admin(): void
    {
        $this->get('http://localhost/solo/admin/login');

        \Livewire\Livewire::test(\Filament\Auth\Pages\Login::class)
            ->fillForm([
                'email' => 'admin@solo.com',
                'password' => 'password',
            ])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        \Livewire\Livewire::test(\App\Filament\TenantAdmin\Resources\ScheduleSessions\Pages\ListScheduleSessions::class)
            ->call('$refresh')
            ->assertOk();
    }
}
