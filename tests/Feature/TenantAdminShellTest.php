<?php

namespace Tests\Feature;

use App\Filament\TenantAdmin\Resources\Patients\Pages\EditPatient;
use App\Filament\TenantAdmin\Resources\Patients\Pages\ListPatients;
use App\Filament\TenantAdmin\Resources\Users\Pages\CreateUser;
use App\Filament\TenantAdmin\Resources\Users\Pages\EditUser;
use App\Models\Patient;
use App\Models\Tenant;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use ReflectionMethod;
use Tests\TestCase;

class TenantAdminShellTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['id' => 'shell-ui', 'plan_tier' => 'solo']);
        tenancy()->initialize($this->tenant);

        $this->admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@shell-ui.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_ADMIN,
            'tenant_id' => $this->tenant->id,
        ]);

        $this->actingAs($this->admin);
        Filament::setCurrentPanel(Filament::getPanel('tenantAdmin'));
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    public function test_list_page_shows_content_header_without_back_link(): void
    {
        $html = Livewire::test(ListPatients::class)
            ->assertSuccessful()
            ->html();

        $this->assertStringContainsString('fi-content-shell-header', $html);
        $this->assertStringContainsString('fi-header-heading', $html);
        $this->assertStringNotContainsString('fi-page-header-back', $html);
    }

    public function test_create_page_shows_back_link_and_create_in_the_header(): void
    {
        $html = Livewire::test(CreateUser::class)
            ->assertSuccessful()
            ->html();

        $this->assertStringContainsString('fi-page-header-back', $html);
        $this->assertMatchesRegularExpression(
            '/<header[^>]*fi-content-shell-header[\s\S]*?Create[\s\S]*?<\/header>/i',
            $html,
        );
    }

    public function test_edit_page_uses_save_as_header_cta_not_delete(): void
    {
        $patient = Patient::create([
            'name' => 'Aminul Islam',
            'phone' => '01712345001',
        ]);

        $html = Livewire::test(EditPatient::class, ['record' => $patient->getKey()])
            ->assertSuccessful()
            ->html();

        $this->assertStringContainsString('fi-page-header-back', $html);
        $this->assertMatchesRegularExpression(
            '/<header[^>]*fi-content-shell-header[\s\S]*?Save changes[\s\S]*?<\/header>/i',
            $html,
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<header[^>]*fi-content-shell-header[\s\S]*?>\s*Delete\s*<[\s\S]*?<\/header>/i',
            $html,
        );
        $this->assertStringContainsString('Delete', $html);
    }

    public function test_edit_user_binds_delete_to_the_form_footer(): void
    {
        $other = User::create([
            'name' => 'Front desk',
            'email' => 'staff@shell-ui.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_STAFF,
            'tenant_id' => $this->tenant->id,
        ]);

        $component = Livewire::test(EditUser::class, ['record' => $other->getKey()])
            ->assertSuccessful();

        $method = new ReflectionMethod($component->instance(), 'getFormActions');
        $delete = null;
        foreach ($method->invoke($component->instance()) as $action) {
            if ($action instanceof DeleteAction) {
                $delete = $action;
            }
        }

        $this->assertNotNull($delete, 'Edit screen should expose a footer Delete action.');
        $this->assertTrue($delete->getRecord()->is($other));
        $this->assertFalse($delete->isHidden());
    }

    public function test_chamber_admin_keeps_open_sidebar_hamburger_and_chamberq_blue(): void
    {
        $provider = file_get_contents(app_path('Providers/Filament/Concerns/ConfiguresTenantAdminPanel.php'));

        $this->assertStringContainsString('->topbar(false)', $provider);
        $this->assertStringContainsString('sidebarCollapsibleOnDesktop()', $provider);
        $this->assertStringContainsString('UsesHamburgerSidebarToggle', $provider);
        $this->assertStringContainsString('OutlinedBars3', file_get_contents(app_path('Providers/Filament/Concerns/UsesHamburgerSidebarToggle.php')));
        $this->assertStringContainsString("localStorage.setItem('isOpenDesktop', JSON.stringify(true));", $provider);
        $this->assertStringContainsString('sidebar.open()', $provider);
        $this->assertStringContainsString('Color::Blue', $provider);
        $this->assertStringNotContainsString('#2173BD', $provider);
    }

    public function test_mobile_sidebar_wraps_long_practice_names_and_scrolls_the_menu(): void
    {
        $css = file_get_contents(resource_path('css/filament/tenantAdmin/theme.css'));

        $this->assertMatchesRegularExpression(
            '/\.fi-sidebar-header-ctn\s*\{[^}]*overflow:\s*visible/',
            $css,
            'Filament clips the header with overflow-x-clip, which also clips wrapped practice names.',
        );
        $this->assertMatchesRegularExpression(
            '/\.fi-sidebar-header\s*\{[^}]*height:\s*auto/',
            $css,
            'The 4rem header cannot hold a two-line practice name.',
        );
        $this->assertMatchesRegularExpression(
            '/\.fi-logo\s*\{[^}]*white-space:\s*normal/',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/\.fi-sidebar-nav\s*\{[^}]*min-height:\s*0/',
            $css,
            'Without min-height:0 a flex nav will not scroll and later menu items sit off-screen.',
        );
        $this->assertStringContainsString('safe-area-inset-top', $css);
        $this->assertStringContainsString('safe-area-inset-bottom', $css);
        $this->assertMatchesRegularExpression(
            '/@media \(max-width:\s*1023px\)\s*\{[\s\S]*\.fi-sidebar-close-overlay\s*\{[^}]*z-index:\s*45/',
            $css,
            'The dim overlay must sit above the sticky page header (z-index 40).',
        );
        $this->assertMatchesRegularExpression(
            '/@media \(max-width:\s*1023px\)\s*\{[\s\S]*\.fi-sidebar\s*\{[^}]*z-index:\s*50/',
            $css,
            'The drawer must sit above Session Actions / New Walk-In. Filament’s sidebar is z-30; our content header is z-40.',
        );
        $this->assertMatchesRegularExpression(
            '/@media \(max-width:\s*1023px\)\s*\{[\s\S]*\.fi-header\.fi-content-shell-header\s*\{[^}]*flex-direction:\s*column/',
            $css,
            'On a phone the header actions must sit on a second row, not beside the title.',
        );

        $provider = file_get_contents(app_path('Providers/Filament/Concerns/ConfiguresTenantAdminPanel.php'));
        $this->assertStringContainsString('viewport-fit=cover', $provider);
    }

    public function test_solid_warning_buttons_use_white_label_text(): void
    {
        $css = file_get_contents(resource_path('css/filament/tenantAdmin/theme.css'));

        $this->assertStringContainsString(
            '.fi-btn.fi-color-warning:not(.fi-outlined):not(label)',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/\.fi-btn\.fi-color-warning:not\(\.fi-outlined\):not\(label\)\s*\{[^}]*--text:\s*#ffffff/',
            $css,
            'Yellow (warning) filled buttons must use white label text, not dark amber.',
        );
    }
}
