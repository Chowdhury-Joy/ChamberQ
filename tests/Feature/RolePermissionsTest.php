<?php

namespace Tests\Feature;

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

    public function test_all_three_roles_can_access_tenant_admin_panel(): void
    {
        tenancy()->initialize($this->tenant);
        $panel = filament()->getPanel('tenantAdmin');

        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.loc',
            'password' => Hash::make('secret'),
            'role' => 'tenant_admin',
            'tenant_id' => 'role-test-tenant',
        ]);

        $developer = User::create([
            'name' => 'Dev',
            'email' => 'dev@test.loc',
            'password' => Hash::make('secret'),
            'role' => 'web_developer',
            'tenant_id' => 'role-test-tenant',
        ]);

        $editor = User::create([
            'name' => 'Editor',
            'email' => 'editor@test.loc',
            'password' => Hash::make('secret'),
            'role' => 'content_editor',
            'tenant_id' => 'role-test-tenant',
        ]);

        $this->assertTrue($admin->canAccessPanel($panel));
        $this->assertTrue($developer->canAccessPanel($panel));
        $this->assertTrue($editor->canAccessPanel($panel));

        $this->assertTrue($admin->isTenantAdmin());
        $this->assertTrue($admin->isWebDeveloper());
        $this->assertFalse($admin->isContentEditor());

        $this->assertFalse($developer->isTenantAdmin());
        $this->assertTrue($developer->isWebDeveloper());
        $this->assertFalse($developer->isContentEditor());

        $this->assertFalse($editor->isTenantAdmin());
        $this->assertFalse($editor->isWebDeveloper());
        $this->assertTrue($editor->isContentEditor());
    }

    public function test_content_editor_cannot_create_or_delete_web_pages(): void
    {
        tenancy()->initialize($this->tenant);

        $editor = User::create([
            'name' => 'Editor',
            'email' => 'editor2@test.loc',
            'password' => Hash::make('secret'),
            'role' => 'content_editor',
            'tenant_id' => 'role-test-tenant',
        ]);

        $page = WebPage::create([
            'title' => 'Test Page',
            'slug' => 'test-page',
            'is_published' => true,
            'content' => [],
        ]);

        $this->actingAs($editor);

        $this->assertFalse(\App\Filament\TenantAdmin\Resources\WebPages\WebPageResource::canCreate());
        $this->assertFalse(\App\Filament\TenantAdmin\Resources\WebPages\WebPageResource::canDelete($page));
        $this->assertFalse(\App\Filament\TenantAdmin\Resources\Users\UserResource::canViewAny());
        $this->assertFalse(\App\Filament\TenantAdmin\Pages\BrandingSettings::canAccess());
    }

    public function test_web_developer_can_create_and_delete_pages_and_access_branding(): void
    {
        tenancy()->initialize($this->tenant);

        $dev = User::create([
            'name' => 'Dev',
            'email' => 'dev2@test.loc',
            'password' => Hash::make('secret'),
            'role' => 'web_developer',
            'tenant_id' => 'role-test-tenant',
        ]);

        $page = WebPage::create([
            'title' => 'Test Page 2',
            'slug' => 'test-page-2',
            'is_published' => true,
            'content' => [],
        ]);

        $this->actingAs($dev);

        $this->assertTrue(\App\Filament\TenantAdmin\Resources\WebPages\WebPageResource::canCreate());
        $this->assertTrue(\App\Filament\TenantAdmin\Resources\WebPages\WebPageResource::canDelete($page));
        $this->assertTrue(\App\Filament\TenantAdmin\Resources\Users\UserResource::canViewAny());
        $this->assertTrue(\App\Filament\TenantAdmin\Pages\BrandingSettings::canAccess());
    }
}
