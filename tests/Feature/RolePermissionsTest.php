<?php

namespace Tests\Feature;

use App\Filament\TenantAdmin\Pages\BrandingSettings;
use App\Filament\TenantAdmin\Pages\ConsultScreen;
use App\Filament\TenantAdmin\Pages\DailyRoster;
use App\Filament\TenantAdmin\Pages\LiveQueueControl;
use App\Filament\TenantAdmin\Pages\OperationalReports;
use App\Filament\TenantAdmin\Resources\Chambers\ChamberResource;
use App\Filament\TenantAdmin\Resources\Doctors\DoctorResource;
use App\Filament\TenantAdmin\Resources\ScheduleSessions\ScheduleSessionResource;
use App\Filament\TenantAdmin\Resources\SlotBlocks\SlotBlockResource;
use App\Filament\TenantAdmin\Resources\Users\UserResource;
use App\Filament\TenantAdmin\Resources\WebPages\WebPageResource;
use App\Models\Domain;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WebPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RolePermissionsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['id' => 'role-test-tenant']);
        Domain::create(['domain' => 'role.localhost', 'tenant_id' => 'role-test-tenant']);
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
            'password' => Hash::make('secret'),
            'role' => $role,
            'tenant_id' => 'role-test-tenant',
        ]);
    }

    public function test_admin_doctor_and_staff_can_access_tenant_panel(): void
    {
        tenancy()->initialize($this->tenant);
        $panel = filament()->getPanel('tenantAdmin');

        $admin = $this->makeUser(User::ROLE_ADMIN, 'admin@test.loc');
        $doctor = $this->makeUser(User::ROLE_DOCTOR, 'doctor@test.loc');
        $staff = $this->makeUser(User::ROLE_STAFF, 'staff@test.loc');

        $this->assertTrue($admin->canAccessPanel($panel));
        $this->assertTrue($doctor->canAccessPanel($panel));
        $this->assertTrue($staff->canAccessPanel($panel));

        $this->assertTrue($admin->isAdmin());
        $this->assertTrue($admin->canManageOps());
        $this->assertTrue($admin->canManageContent());
        $this->assertTrue($admin->canManagePageStructure());
        $this->assertTrue($admin->canManageUsers());
        $this->assertTrue($admin->canManageBranding());
        $this->assertFalse($admin->canManageQueue());

        $this->assertTrue($doctor->isDoctor());
        $this->assertTrue($doctor->canManageOps());
        $this->assertTrue($doctor->canManageQueue());
        $this->assertFalse($doctor->canManageContent());
        $this->assertFalse($doctor->canManagePageStructure());
        $this->assertFalse($doctor->canManageUsers());
        $this->assertFalse($doctor->canManageBranding());

        $this->assertTrue($staff->isStaff());
        $this->assertTrue($staff->canManageContent());
        $this->assertTrue($staff->canManageQueue());
        $this->assertFalse($staff->canManageOps());
        $this->assertFalse($staff->canManagePageStructure());
        $this->assertFalse($staff->canManageUsers());
        $this->assertFalse($staff->canManageBranding());
    }

    public function test_staff_can_edit_content_and_queue_but_not_ops_or_structure(): void
    {
        tenancy()->initialize($this->tenant);

        $staff = $this->makeUser(User::ROLE_STAFF, 'staff2@test.loc');
        $page = WebPage::create([
            'title' => 'Test Page',
            'slug' => 'test-page',
            'is_published' => true,
            'content' => [],
        ]);

        $this->actingAs($staff);

        $this->assertTrue(WebPageResource::canViewAny());
        $this->assertFalse(WebPageResource::canCreate());
        $this->assertFalse(WebPageResource::canDelete($page));
        $this->assertTrue(DailyRoster::canAccess());
        $this->assertTrue(LiveQueueControl::canAccess());
        $this->assertFalse(ConsultScreen::canAccess());
        $this->assertFalse(OperationalReports::canAccess());
        $this->assertFalse(UserResource::canViewAny());
        $this->assertFalse(BrandingSettings::canAccess());
        $this->assertFalse(DoctorResource::canViewAny());
        $this->assertFalse(ChamberResource::canViewAny());
        $this->assertFalse(ScheduleSessionResource::canViewAny());
        $this->assertFalse(SlotBlockResource::canViewAny());
    }

    public function test_doctor_can_manage_ops_and_queue_but_not_website(): void
    {
        tenancy()->initialize($this->tenant);

        $doctor = $this->makeUser(User::ROLE_DOCTOR, 'doctor2@test.loc');
        $page = WebPage::create([
            'title' => 'Test Page 2',
            'slug' => 'test-page-2',
            'is_published' => true,
            'content' => [],
        ]);

        $this->actingAs($doctor);

        $this->assertTrue(DoctorResource::canViewAny());
        $this->assertTrue(ChamberResource::canViewAny());
        $this->assertTrue(ScheduleSessionResource::canViewAny());
        $this->assertTrue(SlotBlockResource::canViewAny());
        $this->assertTrue(DailyRoster::canAccess());
        $this->assertFalse(LiveQueueControl::canAccess());
        $this->assertTrue(ConsultScreen::canAccess());
        $this->assertTrue(OperationalReports::canAccess());
        $this->assertFalse(WebPageResource::canViewAny());
        $this->assertFalse(WebPageResource::canCreate());
        $this->assertFalse(WebPageResource::canDelete($page));
        $this->assertFalse(UserResource::canViewAny());
        $this->assertFalse(BrandingSettings::canAccess());
    }

    public function test_admin_has_full_access(): void
    {
        tenancy()->initialize($this->tenant);

        $admin = $this->makeUser(User::ROLE_ADMIN, 'admin2@test.loc');
        $page = WebPage::create([
            'title' => 'Admin Page',
            'slug' => 'admin-page',
            'is_published' => true,
            'content' => [],
        ]);

        $this->actingAs($admin);

        $this->assertTrue(WebPageResource::canCreate());
        $this->assertTrue(WebPageResource::canDelete($page));
        $this->assertTrue(UserResource::canViewAny());
        $this->assertTrue(BrandingSettings::canAccess());
        $this->assertTrue(DoctorResource::canViewAny());
        $this->assertFalse(DailyRoster::canAccess());
        $this->assertFalse(LiveQueueControl::canAccess());
        $this->assertFalse(ConsultScreen::canAccess());
        $this->assertTrue(OperationalReports::canAccess());
    }
}
