<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\Tenant;
use App\Models\WebPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketingSiteUiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'id' => 'ui-solo',
            'name' => 'Dr. UI Chamber',
            'plan_tier' => 'solo',
            'theme_color' => Tenant::DEFAULT_THEME_COLOR,
            'default_locale' => 'en',
            'font_family' => 'Outfit',
        ]);
        Domain::create(['domain' => 'ui-solo.test', 'tenant_id' => 'ui-solo']);

        tenancy()->initialize($this->tenant);
        WebPage::create([
            'title' => 'Home',
            'slug' => '/',
            'is_published' => true,
            'content' => [[
                'type' => 'hero',
                'data' => [
                    'headline' => 'Care that respects your time',
                    'subheadline' => 'Book online.',
                    'cta_text' => 'Book Appointment',
                    'cta_link' => '/book',
                ],
            ], [
                'type' => 'rich_text',
                'data' => [
                    'content' => '<h2>About</h2><p>Test about copy for the chamber homepage.</p>',
                ],
            ]],
        ]);
        tenancy()->end();
    }

    public function test_homepage_puts_book_in_hero_and_portal_in_nav(): void
    {
        $response = $this->get('http://ui-solo.test/');

        $response->assertOk();
        $response->assertSee('Dr. UI Chamber', false);
        $response->assertSee('Care that respects your time', false);
        $response->assertSee('Book Appointment', false);
        $response->assertSee('Patient’s Portal', false);
        $response->assertDontSee('🏥', false);
        // Marketing EN/BN gated off without bangla_homepage
        $html = $response->getContent();
        $this->assertStringNotContainsString('/lang/bn', $html);
    }

    public function test_bangla_homepage_flag_shows_marketing_lang_switch(): void
    {
        $this->tenant->update(['feature_flags' => ['bangla_homepage' => true]]);

        $response = $this->get('http://ui-solo.test/');

        $response->assertOk();
        $response->assertSee('/lang/bn', false);
    }

    public function test_book_page_always_offers_locale_switch(): void
    {
        $response = $this->get('http://ui-solo.test/book');

        $response->assertOk();
        $response->assertSee('/lang/en', false);
        $response->assertSee('/lang/bn', false);
        $response->assertSee('locale-chip', false);
    }
}
