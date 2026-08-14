<?php

namespace Tests\Feature;

use App\Filament\SuperAdmin\Pages\DataBackup as SuperAdminDataBackup;
use App\Filament\SuperAdmin\Pages\SellerOverview;
use App\Filament\SuperAdmin\Resources\Tenants\Pages\EditTenant;
use App\Filament\SuperAdmin\Resources\Tenants\Pages\ListTenants;
use App\Filament\SuperAdmin\Resources\Tenants\TenantResource;
use App\Filament\SuperAdmin\Widgets\PlatformFinanceOverview;
use App\Filament\SuperAdmin\Widgets\RecentTenantsWidget;
use App\Filament\SuperAdmin\Widgets\SuperAdminStatsOverview;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Widgets\AccountWidget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class SuperAdminPanelUxTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'super-ux@test.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_SUPER_ADMIN,
            'tenant_id' => null,
        ]);

        $this->actingAs($this->superAdmin);
        Filament::setCurrentPanel(Filament::getPanel('superAdmin'));
    }

    public function test_restore_and_delete_sit_behind_a_dangerous_menu_on_tenant_edit(): void
    {
        $tenant = Tenant::create([
            'id' => 'ux-chamber',
            'name' => 'UX Chamber',
            'plan_tier' => 'solo',
        ]);

        Livewire::test(EditTenant::class, ['record' => $tenant->getKey()])
            ->assertSuccessful()
            ->assertSee('Dangerous')
            ->assertActionExists('restoreTenantBackup')
            ->assertActionExists('delete')
            ->assertActionExists('topUpSms');
    }

    public function test_platform_restore_defaults_to_dry_run(): void
    {
        Livewire::test(SuperAdminDataBackup::class)
            ->assertSuccessful()
            ->assertSet('importData.dry_run', true)
            ->assertSee('Check ZIP without writing');
    }

    public function test_platform_backup_buttons_declare_a_loading_state(): void
    {
        $html = Livewire::test(SuperAdminDataBackup::class)
            ->assertSuccessful()
            ->html();

        $this->assertStringContainsString('wire:target="downloadPlatformBackup"', $html);
        $this->assertStringContainsString('wire:target="restorePlatformBackup"', $html);
        $this->assertStringContainsString('wire:loading.attr="disabled"', $html);
    }

    public function test_dashboard_uses_maestro_not_solo_uppercase_and_placeholder_for_blank_names(): void
    {
        Tenant::create([
            'id' => 'blank-name',
            'name' => null,
            'plan_tier' => 'solo',
        ]);

        Livewire::test(RecentTenantsWidget::class)
            ->assertSuccessful()
            ->assertSee('Maestro')
            ->assertDontSee('SOLO');
    }

    public function test_super_admin_panel_registers_amber_and_sky_colours(): void
    {
        $colors = Filament::getPanel('superAdmin')->getColors();

        $this->assertArrayHasKey('amber', $colors);
        $this->assertArrayHasKey('sky', $colors);
    }

    public function test_dashboard_shows_platform_totals_before_the_tenant_table_and_omits_the_account_widget(): void
    {
        $this->assertSame(-3, AccountWidget::getSort());
        $this->assertSame(1, PlatformFinanceOverview::getSort());
        $this->assertSame(2, SuperAdminStatsOverview::getSort());
        $this->assertSame(3, RecentTenantsWidget::getSort());
        $this->assertNotContains(
            AccountWidget::class,
            Filament::getPanel('superAdmin')->getWidgets(),
        );
    }

    public function test_tenants_is_grouped_under_platform_and_the_list_has_operator_filters(): void
    {
        $this->assertSame('Platform', TenantResource::getNavigationGroup());

        Tenant::create([
            'id' => 'filter-me',
            'name' => 'Filter Me',
            'plan_tier' => 'solo',
            'billing_status' => 'active',
        ]);

        Livewire::test(ListTenants::class)
            ->assertSuccessful()
            ->assertTableFilterExists('plan_tier')
            ->assertTableFilterExists('billing_status')
            ->assertTableFilterExists('marketer_id');
    }

    public function test_client_health_names_link_to_the_tenant_edit_page_and_show_phone(): void
    {
        Tenant::create([
            'id' => 'call-me',
            'name' => 'Call Me Clinic',
            'plan_tier' => 'solo',
            'billing_status' => 'active',
            'sms_balance' => 0,
            'contact_phone' => '01711112222',
        ]);

        $editUrl = TenantResource::getUrl('edit', ['record' => 'call-me']);

        Livewire::test(SellerOverview::class)
            ->assertSuccessful()
            ->assertSee($editUrl, escape: false)
            ->assertSee('Call Me Clinic')
            ->assertSee('01711112222');
    }
}
