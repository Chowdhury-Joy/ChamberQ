<?php

namespace Tests\Feature;

use App\Filament\TenantAdmin\Resources\WebPages\Pages\CreateWebPage;
use App\Filament\TenantAdmin\Resources\WebPages\Pages\EditWebPage;
use App\Filament\TenantAdmin\Support\PageBuilderChrome;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WebPage;
use Filament\Facades\Filament;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class WebPageEditorHabitsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['id' => 'page-editor', 'plan_tier' => 'solo']);
        tenancy()->initialize($this->tenant);

        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@page-editor.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_ADMIN,
            'tenant_id' => $this->tenant->id,
        ]);

        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('tenantAdmin'));
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    public function test_closed_lid_label_is_type_plus_headline(): void
    {
        $label = PageBuilderChrome::blockLid('Hero');

        $this->assertSame('Hero', $label(null));
        $this->assertSame('Hero', $label([]));
        $this->assertSame(
            'Hero — Care that respects your time',
            $label(['headline' => "Care that respects\nyour time"]),
        );
    }

    public function test_nested_row_labels(): void
    {
        $this->assertSame(
            '01 — Digital Consultation',
            PageBuilderChrome::numberedName(
                ['step_number' => '01', 'title' => 'Digital Consultation'],
                'step_number',
                'title',
            ),
        );
        $this->assertSame(
            'How do I prepare?',
            PageBuilderChrome::nestedName(['question' => 'How do I prepare?'], 'question'),
        );
    }

    public function test_inner_page_edit_is_narrow_with_save_at_the_end(): void
    {
        $page = WebPage::create([
            'title' => 'About',
            'slug' => 'about',
            'is_published' => true,
            'content' => [[
                'type' => 'hero',
                'data' => ['headline' => 'Care that respects your time'],
            ]],
        ]);

        $component = Livewire::test(EditWebPage::class, ['record' => $page->getKey()])
            ->assertSuccessful();

        $this->assertSame(Width::FiveExtraLarge, $component->instance()->getMaxContentWidth());
        $this->assertSame(Alignment::End, $component->instance()->getFormActionsAlignment());
        $this->assertFalse($component->instance()->areFormActionsSticky());

        $html = $component->html();
        $this->assertStringContainsString('Hero — Care that respects your time', $html);
        $this->assertStringNotContainsString('Block 1', $html);
        $this->assertStringContainsString('Save changes', $html);
        $this->assertStringNotContainsString('Page Content Editor', $html);
        $this->assertStringNotContainsString('sticky bottom-0 z-40', $html);
        $this->assertStringContainsString('setUpUnsavedDataChangesAlert', $html);
        $this->assertStringContainsString('Collapse all', $html);
        $this->assertStringContainsString('Expand all', $html);
    }

    public function test_homepage_edit_uses_full_width_without_collapse_all(): void
    {
        $home = WebPage::create([
            'title' => 'Home',
            'slug' => '/',
            'is_published' => true,
            'content' => [[
                'type' => 'faq',
                'data' => [
                    'heading' => 'Frequently Asked Questions',
                    'faqs' => [
                        ['question' => 'How do I prepare for my first appointment?', 'answer' => 'Bring ID.'],
                    ],
                ],
            ]],
        ]);

        $component = Livewire::test(EditWebPage::class, ['record' => $home->getKey()])
            ->assertSuccessful();

        $this->assertSame(Width::Full, $component->instance()->getMaxContentWidth());
        $this->assertTrue(PageBuilderChrome::isHomepageEditor($component->instance()));

        $html = $component->html();
        $this->assertStringContainsString('FAQ — Frequently Asked Questions', $html);
        $this->assertStringContainsString('How do I prepare for my first appointment?', $html);
        $this->assertStringNotContainsString('Collapse all', $html);
        $this->assertStringNotContainsString('Expand all', $html);
        $this->assertStringContainsString('Save changes', $html);
        $this->assertStringNotContainsString('Homepage Content Editor', $html);
    }

    public function test_create_page_matches_inner_page_save_habits(): void
    {
        $component = Livewire::test(CreateWebPage::class)->assertSuccessful();

        $this->assertSame(Width::FiveExtraLarge, $component->instance()->getMaxContentWidth());
        $this->assertSame(Alignment::End, $component->instance()->getFormActionsAlignment());

        $html = $component->html();
        $this->assertStringContainsString('Save changes', $html);
        $this->assertStringContainsString('Collapse all', $html);
        $this->assertStringNotContainsString('Create & create another', $html);
        $this->assertStringContainsString('setUpUnsavedDataChangesAlert', $html);
    }
}
