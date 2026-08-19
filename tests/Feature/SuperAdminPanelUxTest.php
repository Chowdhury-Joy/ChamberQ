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

    public function test_super_admin_uses_the_same_phone_admin_chrome_as_the_chamber_desk(): void
    {
        $provider = file_get_contents(app_path('Providers/Filament/SuperAdminPanelProvider.php'));

        $this->assertStringContainsString("viteTheme('resources/css/filament/tenantAdmin/theme.css')", $provider);
        $this->assertStringContainsString('->topbar(false)', $provider);
        $this->assertStringContainsString('sidebarCollapsibleOnDesktop()', $provider);
        $this->assertStringContainsString('viewport-fit=cover', $provider);
        $this->assertStringContainsString('UsesHamburgerSidebarToggle', $provider);
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

    public function test_backup_card_body_keeps_its_padding_rule(): void
    {
        // The rule was deleted once while both cards still used the class, so the
        // whole restore form rendered flush against the card border.
        $html = Livewire::test(SuperAdminDataBackup::class)
            ->assertSuccessful()
            ->html();

        $this->assertMatchesRegularExpression(
            '/\.backup-card-body\s*\{[^}]*padding:/',
            $html,
            'The .backup-card-body padding rule is missing while the class is still in use.',
        );
    }

    public function test_turning_dry_run_off_arms_the_destructive_restore_with_a_confirmation(): void
    {
        $dryRun = Livewire::test(SuperAdminDataBackup::class)
            ->assertSuccessful()
            ->html();

        $this->assertStringNotContainsString('wire:confirm', $dryRun);
        $this->assertStringContainsString('restore-submit-dry', $dryRun);

        $live = Livewire::test(SuperAdminDataBackup::class)
            ->set('importData.dry_run', false)
            ->assertSuccessful()
            ->assertSee('Upload and restore platform data')
            ->assertSee('Dry run is off')
            ->html();

        $this->assertStringContainsString('wire:confirm', $live);
        // Keyed per state so Livewire replaces the button instead of morphing it —
        // a morphed button kept its old paint and the danger red never appeared.
        $this->assertStringContainsString('restore-submit-live', $live);
    }

    public function test_tenants_list_keeps_the_row_actions_reachable_without_horizontal_scroll(): void
    {
        Tenant::create([
            'id' => 'wide-row',
            'name' => 'A Very Long Practice Name That Used To Set The Column Width',
            'plan_tier' => 'solo',
            'billing_status' => 'active',
        ]);

        $table = Livewire::test(ListTenants::class)
            ->assertSuccessful()
            ->instance()
            ->getTable();

        $hiddenByDefault = [];
        foreach ($table->getColumns() as $name => $column) {
            if ($column->isToggledHiddenByDefault()) {
                $hiddenByDefault[] = $name;
            }
        }

        // These four are what pushed Edit past the right edge; each is still one
        // toggle away, and all of them are also on tenant edit or Client Health.
        foreach (['modules', 'marketer.display_name', 'setup_amount_due', 'monthly_amount_due'] as $column) {
            $this->assertContains($column, $hiddenByDefault, "{$column} should start hidden on the tenants list.");
        }
    }
}
