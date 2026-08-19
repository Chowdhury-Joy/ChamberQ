<?php

namespace Tests\Feature;

use App\Filament\TenantAdmin\Pages\Dashboard;
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
 * A doctor's working surface is Consult Screen, not the stats dashboard.
 * Login, the sidebar logo, and a visit to /admin should all open the pad.
 */
class DoctorConsultHomeTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['id' => 'solo', 'plan_tier' => 'solo']);
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    private function makeUser(string $role, string $email): User
    {
        return User::create([
            'name' => ucfirst($role),
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => $role,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    private function onPathPanel(): void
    {
        tenancy()->initialize($this->tenant);
        Filament::setCurrentPanel(Filament::getPanel('tenantAdminPath'));
        URL::defaults(['tenant' => 'solo']);
    }

    public function test_signing_in_as_doctor_lands_on_consult_screen(): void
    {
        $this->onPathPanel();
        $this->makeUser(User::ROLE_DOCTOR, 'doc@solo.com');

        $response = Livewire::test(Login::class)
            ->fillForm([
                'email' => 'doc@solo.com',
                'password' => 'password',
            ])
            ->call('authenticate');

        $response->assertHasNoFormErrors();

        $target = $response->effects['redirect'] ?? null;

        $this->assertNotNull($target, 'Login did not redirect anywhere.');
        $this->assertStringContainsString('/solo/admin/consult-screen', $target);
        $this->assertAuthenticated();
    }

    public function test_signing_in_as_staff_still_lands_on_the_dashboard(): void
    {
        $this->onPathPanel();
        $this->makeUser(User::ROLE_STAFF, 'staff@solo.com');

        $response = Livewire::test(Login::class)
            ->fillForm([
                'email' => 'staff@solo.com',
                'password' => 'password',
            ])
            ->call('authenticate');

        $response->assertHasNoFormErrors();

        $target = $response->effects['redirect'] ?? null;

        $this->assertNotNull($target, 'Login did not redirect anywhere.');
        $this->assertStringContainsString('/solo/admin', $target);
        $this->assertStringNotContainsString('consult-screen', $target);
    }

    public function test_panel_home_for_a_doctor_is_consult_screen(): void
    {
        $this->onPathPanel();
        $doctor = $this->makeUser(User::ROLE_DOCTOR, 'doc-home@solo.com');
        $this->actingAs($doctor);

        $home = FilamentPanelUrl::home(Filament::getPanel('tenantAdminPath'));

        $this->assertStringContainsString('/solo/admin/consult-screen', $home);
    }

    public function test_panel_home_for_staff_stays_the_dashboard(): void
    {
        $this->onPathPanel();
        $staff = $this->makeUser(User::ROLE_STAFF, 'staff-home@solo.com');
        $this->actingAs($staff);

        $home = FilamentPanelUrl::home(Filament::getPanel('tenantAdminPath'));

        $this->assertStringContainsString('/solo/admin', $home);
        $this->assertStringNotContainsString('consult-screen', $home);
        $this->assertStringNotContainsString('live-queue-control', $home);
        $this->assertStringNotContainsString('cashbook', $home);
    }

    public function test_queue_only_staff_land_on_live_queue(): void
    {
        $this->onPathPanel();
        $staff = $this->makeUser(User::ROLE_STAFF, 'queue@solo.com');
        $staff->update(['desk_jobs' => [\App\Support\StaffDeskJobs::JOB_QUEUE]]);
        $this->actingAs($staff->fresh());

        $home = FilamentPanelUrl::home(Filament::getPanel('tenantAdminPath'));

        $this->assertStringContainsString('/solo/admin/live-queue-control', $home);
    }

    public function test_money_only_staff_land_on_cashbook(): void
    {
        $this->onPathPanel();
        $staff = $this->makeUser(User::ROLE_STAFF, 'money@solo.com');
        $staff->update(['desk_jobs' => [\App\Support\StaffDeskJobs::JOB_MONEY]]);
        $this->actingAs($staff->fresh());

        $home = FilamentPanelUrl::home(Filament::getPanel('tenantAdminPath'));

        $this->assertStringContainsString('/solo/admin/cashbook', $home);
    }

    public function test_prep_only_staff_land_on_daily_roster(): void
    {
        $this->onPathPanel();
        $staff = $this->makeUser(User::ROLE_STAFF, 'prep@solo.com');
        $staff->update(['desk_jobs' => [\App\Support\StaffDeskJobs::JOB_PREP]]);
        $this->actingAs($staff->fresh());

        $home = FilamentPanelUrl::home(Filament::getPanel('tenantAdminPath'));

        $this->assertStringContainsString('/solo/admin/daily-roster', $home);
    }

    public function test_signing_in_as_queue_staff_lands_on_live_queue(): void
    {
        $this->onPathPanel();
        $staff = $this->makeUser(User::ROLE_STAFF, 'queue-login@solo.com');
        $staff->update(['desk_jobs' => [\App\Support\StaffDeskJobs::JOB_QUEUE]]);

        $response = Livewire::test(Login::class)
            ->fillForm([
                'email' => 'queue-login@solo.com',
                'password' => 'password',
            ])
            ->call('authenticate');

        $response->assertHasNoFormErrors();

        $target = $response->effects['redirect'] ?? null;

        $this->assertNotNull($target, 'Login did not redirect anywhere.');
        $this->assertStringContainsString('/solo/admin/live-queue-control', $target);
    }

    public function test_doctor_without_prescription_module_stays_on_the_dashboard(): void
    {
        $this->tenant->update([
            'feature_flags' => Tenant::featureFlagsWithModules([], [
                Tenant::MODULE_FRONT_DOOR,
            ]),
        ]);
        $this->tenant->refresh();

        $this->onPathPanel();
        $doctor = $this->makeUser(User::ROLE_DOCTOR, 'front-door-only@solo.com');
        $this->actingAs($doctor);

        $this->assertFalse($doctor->canViewConsultScreen());

        $home = FilamentPanelUrl::home(Filament::getPanel('tenantAdminPath'));

        $this->assertStringNotContainsString('consult-screen', $home);
        $this->assertStringContainsString('/solo/admin', $home);
    }

    public function test_dashboard_is_hidden_from_the_doctor_sidebar(): void
    {
        $this->onPathPanel();
        $doctor = $this->makeUser(User::ROLE_DOCTOR, 'doc-nav@solo.com');
        $this->actingAs($doctor);

        $this->assertFalse(Dashboard::shouldRegisterNavigation());
    }

    public function test_opening_the_dashboard_sends_a_doctor_to_consult_screen(): void
    {
        $this->onPathPanel();
        $doctor = $this->makeUser(User::ROLE_DOCTOR, 'doc-dash@solo.com');
        $this->actingAs($doctor);

        Livewire::test(Dashboard::class)
            ->assertRedirect(FilamentPanelUrl::home());
    }

    public function test_staff_can_still_open_the_dashboard(): void
    {
        $this->onPathPanel();
        $staff = $this->makeUser(User::ROLE_STAFF, 'staff-dash@solo.com');
        $this->actingAs($staff);

        $this->assertTrue(Dashboard::shouldRegisterNavigation());

        Livewire::test(Dashboard::class)
            ->assertSuccessful();
    }

    public function test_admin_keeps_the_dashboard_and_has_no_consult_screen(): void
    {
        $this->onPathPanel();
        $admin = $this->makeUser(User::ROLE_ADMIN, 'admin-dash@solo.com');
        $this->actingAs($admin);

        $this->assertFalse($admin->canViewConsultScreen());
        $this->assertTrue(Dashboard::shouldRegisterNavigation());

        $home = FilamentPanelUrl::home(Filament::getPanel('tenantAdminPath'));
        $this->assertStringContainsString('/solo/admin', $home);
        $this->assertStringNotContainsString('consult-screen', $home);

        Livewire::test(Dashboard::class)
            ->assertSuccessful();
    }
}
