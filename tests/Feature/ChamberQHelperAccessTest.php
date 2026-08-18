<?php

namespace Tests\Feature;

use App\Filament\SuperAdmin\Resources\Tenants\Pages\CreateTenant;
use App\Filament\TenantAdmin\Pages\ConsultScreen;
use App\Filament\TenantAdmin\Pages\DailyRoster;
use App\Filament\TenantAdmin\Pages\LiveQueueControl;
use App\Filament\TenantAdmin\Resources\Users\Pages\ListUsers;
use App\Filament\TenantAdmin\Resources\Users\UserResource;
use App\Models\Domain;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantUserBootstrapService;
use App\Support\ChamberQHelperAccess;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class ChamberQHelperAccessTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['id' => 'helper-clinic', 'plan_tier' => 'clinic']);
        Domain::create(['domain' => 'helper.localhost', 'tenant_id' => 'helper-clinic']);
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    private function makeUser(string $role, string $email): User
    {
        return User::create([
            'name' => $email,
            'email' => $email,
            'password' => Hash::make('secret'),
            'role' => $role,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    private function makeSuperAdmin(string $email): User
    {
        tenancy()->end();

        return User::create([
            'name' => 'Platform',
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => User::ROLE_SUPER_ADMIN,
            'tenant_id' => null,
        ]);
    }

    public function test_helper_has_setup_access_but_not_queue_or_rx(): void
    {
        tenancy()->initialize($this->tenant);
        $this->makeUser(User::ROLE_STAFF, 'desk@helper.loc');
        $helper = $this->makeUser(User::ROLE_HELPER, 'support@helper.loc');

        $this->assertTrue($helper->isAdmin());
        $this->assertTrue($helper->isHelper());
        $this->assertFalse($helper->isOwner());
        $this->assertTrue($helper->canManageUsers());
        $this->assertTrue($helper->canManageBranding());
        $this->assertTrue($helper->canManageSittingSetup());
        $this->assertFalse($helper->canManageQueue());
        $this->assertFalse($helper->canOperateQueueControls());
        $this->assertFalse($helper->canViewConsultScreen());
        $this->assertFalse($helper->canRecordVisitNotes());

        $this->actingAs($helper);
        Filament::setCurrentPanel(Filament::getPanel('tenantAdmin'));

        $this->assertTrue($helper->canAccessPanel(Filament::getPanel('tenantAdmin')));
        $this->assertTrue(DailyRoster::canAccess());
        $this->assertFalse(LiveQueueControl::canAccess());
        $this->assertFalse(ConsultScreen::canAccess());
    }

    public function test_owner_staff_list_hides_helpers_and_cannot_delete_them(): void
    {
        tenancy()->initialize($this->tenant);
        $owner = $this->makeUser(User::ROLE_OWNER, 'owner@helper.loc');
        $helper = $this->makeUser(User::ROLE_HELPER, 'support@helper.loc');
        $desk = $this->makeUser(User::ROLE_STAFF, 'desk@helper.loc');

        $this->actingAs($owner);
        Filament::setCurrentPanel(Filament::getPanel('tenantAdmin'));

        $this->assertFalse(UserResource::canView($helper));
        $this->assertFalse(UserResource::canEdit($helper));
        $this->assertFalse(UserResource::canDelete($helper));
        $this->assertTrue(UserResource::canView($desk));
        $this->assertFalse(ChamberQHelperAccess::actorSeesHelpersOnStaffList());

        Livewire::test(ListUsers::class)
            ->assertSee('desk@helper.loc')
            ->assertDontSee('support@helper.loc');

        $this->expectException(AuthorizationException::class);
        $helper->delete();
    }

    public function test_owner_cannot_demote_a_helper_or_create_another(): void
    {
        tenancy()->initialize($this->tenant);
        $owner = $this->makeUser(User::ROLE_OWNER, 'owner2@helper.loc');
        $helper = $this->makeUser(User::ROLE_HELPER, 'support2@helper.loc');

        $this->actingAs($owner);

        try {
            $helper->update(['role' => User::ROLE_STAFF]);
            $this->fail('Owner must not demote a helper.');
        } catch (AuthorizationException) {
            $this->assertTrue($helper->fresh()->isHelper());
        }

        $this->expectException(AuthorizationException::class);
        $this->makeUser(User::ROLE_HELPER, 'another-support@helper.loc');
    }

    public function test_helper_sees_other_helpers_but_cannot_edit_or_delete_them(): void
    {
        tenancy()->initialize($this->tenant);
        $this->makeUser(User::ROLE_OWNER, 'owner3@helper.loc');
        $a = $this->makeUser(User::ROLE_HELPER, 'web@helper.loc');
        $b = $this->makeUser(User::ROLE_HELPER, 'it@helper.loc');

        $this->actingAs($a);
        Filament::setCurrentPanel(Filament::getPanel('tenantAdmin'));

        $this->assertTrue(UserResource::canView($b));
        $this->assertFalse(UserResource::canEdit($b));
        $this->assertFalse(UserResource::canDelete($b));
        $this->assertTrue(ChamberQHelperAccess::actorSeesHelpersOnStaffList());

        Livewire::test(ListUsers::class)
            ->assertSee('web@helper.loc')
            ->assertSee('it@helper.loc');
    }

    public function test_last_helper_cannot_be_deleted_even_by_super_admin(): void
    {
        $super = $this->makeSuperAdmin('super@platform.test');
        tenancy()->initialize($this->tenant);
        $helper = $this->makeUser(User::ROLE_HELPER, 'only@helper.loc');
        $this->actingAs($super);

        $this->expectException(AuthorizationException::class);
        $helper->delete();
    }

    public function test_super_admin_can_remove_an_extra_helper(): void
    {
        $super = $this->makeSuperAdmin('super2@platform.test');
        tenancy()->initialize($this->tenant);
        $keep = $this->makeUser(User::ROLE_HELPER, 'keep@helper.loc');
        $extra = $this->makeUser(User::ROLE_HELPER, 'extra@helper.loc');
        $this->actingAs($super);

        $extra->delete();

        $this->assertNotNull($keep->fresh());
        $this->assertNull(User::withoutGlobalScopes()->find($extra->id));
    }

    public function test_create_tenant_bootstraps_owner_helper_and_doctor(): void
    {
        $super = $this->makeSuperAdmin('super3@platform.test');
        $this->actingAs($super);
        Filament::setCurrentPanel(Filament::getPanel('superAdmin'));

        Livewire::test(CreateTenant::class)
            ->fillForm([
                'id' => 'newclinic',
                'name' => 'New Clinic',
                'plan_tier' => 'solo',
                'slot_cap_mode' => 'per_session',
                'billing_status' => 'trial',
                'sms_balance' => 0,
                'default_locale' => 'en',
                'theme_color' => Tenant::DEFAULT_THEME_COLOR,
                'product_modules' => Tenant::productModules(),
                'domains' => [],
                'initial_owner_email' => 'founder@newclinic.test',
                'initial_doctor_email' => 'doc@newclinic.test',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $tenant = Tenant::find('newclinic');
        $this->assertNotNull($tenant);

        $bootstrap = app(TenantUserBootstrapService::class);
        $this->assertTrue($bootstrap->hasOwnerLogin($tenant));
        $this->assertTrue($bootstrap->hasHelperLogin($tenant));
        $this->assertTrue($bootstrap->hasDoctorLogin($tenant));

        $owner = User::withoutGlobalScopes()->where('email', 'founder@newclinic.test')->first();
        $helper = User::withoutGlobalScopes()
            ->where('tenant_id', 'newclinic')
            ->where('role', User::ROLE_HELPER)
            ->first();
        $doctor = User::withoutGlobalScopes()->where('email', 'doc@newclinic.test')->first();

        $this->assertTrue($owner?->isOwner());
        $this->assertTrue($helper?->isHelper());
        $this->assertTrue($doctor?->isDoctor());
        $this->assertSame('support@newclinic.chamberq.internal', $helper->email);
    }
}
