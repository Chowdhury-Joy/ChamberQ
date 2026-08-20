<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Models\Chamber;
use App\Models\Doctor;
use App\Models\Domain;
use App\Models\Tenant;
use App\Models\WebPage;
use App\Support\TenancyUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicSeoTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $clinic;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clinic = Tenant::create([
            'id' => 'seo-clinic',
            'plan_tier' => 'clinic',
            'name' => 'Riverside Clinic',
            'tagline' => 'Pain care in Chattogram',
            'contact_phone' => '01711111111',
        ]);
        Domain::create(['domain' => 'seo-clinic.localhost', 'tenant_id' => $this->clinic->id]);
    }

    public function test_reserved_slugs_include_robots_and_sitemap(): void
    {
        $pattern = TenancyUrl::tenantSlugPattern();

        $this->assertDoesNotMatchRegularExpression('/^'.$pattern.'$/', 'robots.txt');
        $this->assertDoesNotMatchRegularExpression('/^'.$pattern.'$/', 'sitemap.xml');
        $this->assertDoesNotMatchRegularExpression('/^'.$pattern.'$/', 'conditions');
    }

    public function test_central_robots_txt_hides_staff_and_private_paths(): void
    {
        $body = $this->get('http://localhost/robots.txt')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->getContent();

        $this->assertStringContainsString('User-agent: *', $body);
        $this->assertStringContainsString('Disallow: /admin', $body);
        $this->assertStringContainsString('Disallow: /partner', $body);
        $this->assertStringContainsString('Disallow: /me', $body);
        $this->assertStringContainsString('Disallow: /*/admin', $body);
        $this->assertStringContainsString('Disallow: /*/portal', $body);
        $this->assertStringContainsString('Disallow: /*/bookings', $body);
        $this->assertStringContainsString('Sitemap: http://localhost/sitemap.xml', $body);
    }

    public function test_central_sitemap_lists_sales_and_find_not_admin(): void
    {
        Tenant::create(['id' => 'seo-path', 'plan_tier' => 'solo', 'name' => 'Path Chamber']);

        $xml = $this->get('http://localhost/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->getContent();

        $this->assertStringContainsString('<loc>http://localhost/</loc>', $xml);
        $this->assertStringContainsString('<loc>http://localhost/find</loc>', $xml);
        $this->assertStringContainsString('<loc>http://localhost/seo-path/</loc>', $xml);
        $this->assertStringNotContainsString('/admin</loc>', $xml);
        $this->assertStringNotContainsString('seo-clinic.localhost', $xml);
    }

    public function test_marketing_home_has_description_open_graph_and_schema(): void
    {
        $html = $this->get('http://localhost/')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('name="description"', $html);
        $this->assertStringContainsString('property="og:title"', $html);
        $this->assertStringContainsString('rel="canonical"', $html);
        $this->assertStringContainsString('application/ld+json', $html);
        $this->assertStringContainsString('"@type":"Organization"', $html);
    }

    public function test_find_page_is_indexable_and_login_is_not(): void
    {
        $this->get('http://localhost/find')
            ->assertOk()
            ->assertSee('name="description"', false)
            ->assertSee('rel="canonical"', false)
            ->assertDontSee('noindex', false);

        $this->get('http://localhost/me/login')
            ->assertOk()
            ->assertSee('noindex', false);
    }

    public function test_clinic_homepage_and_book_page_carry_search_tags(): void
    {
        $this->publishHome();

        $home = $this->get('http://seo-clinic.localhost/')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('name="description"', $home);
        $this->assertStringContainsString('Pain specialist in Chattogram | Riverside Clinic', $home);
        $this->assertStringContainsString('Pain care in Chattogram', $home);
        $this->assertStringContainsString('rel="canonical"', $home);
        $this->assertStringContainsString('property="og:title"', $home);
        $this->assertStringContainsString('application/ld+json', $home);
        $this->assertStringContainsString('MedicalClinic', $home);
        $this->assertStringContainsString('addressLocality', $home);
        $this->assertStringContainsString('index, follow', $home);

        $book = $this->get('http://seo-clinic.localhost/book')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('name="description"', $book);
        $this->assertStringContainsString('rel="canonical"', $book);
        $this->assertStringContainsString('/book', $book);
        $this->assertStringContainsString('Pain specialist in Chattogram', $book);
        $this->assertStringContainsString('Pay at the chamber', $book);
    }

    public function test_clinic_sitemap_lists_public_pages_not_portal(): void
    {
        $this->publishHome();

        tenancy()->initialize($this->clinic);
        BlogPost::create([
            'title' => 'Back pain tips',
            'slug' => 'back-pain-tips',
            'excerpt' => 'Stand up every hour',
            'is_published' => true,
            'published_at' => now(),
        ]);
        tenancy()->end();

        $xml = $this->get('http://seo-clinic.localhost/sitemap.xml')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('<loc>http://seo-clinic.localhost/</loc>', $xml);
        $this->assertStringContainsString('<loc>http://seo-clinic.localhost/book</loc>', $xml);
        $this->assertStringContainsString('<loc>http://seo-clinic.localhost/blog/back-pain-tips</loc>', $xml);
        $this->assertStringContainsString('<loc>http://seo-clinic.localhost/conditions/knee-pain</loc>', $xml);
        $this->assertStringNotContainsString('/portal</loc>', $xml);
        $this->assertStringNotContainsString('/admin</loc>', $xml);
    }

    public function test_clinic_robots_and_portal_are_not_for_google(): void
    {
        $robots = $this->get('http://seo-clinic.localhost/robots.txt')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Disallow: /admin', $robots);
        $this->assertStringContainsString('Disallow: /portal', $robots);
        $this->assertStringContainsString('Disallow: /bookings', $robots);
        $this->assertStringContainsString('Sitemap: http://seo-clinic.localhost/sitemap.xml', $robots);

        $this->get('http://seo-clinic.localhost/portal')
            ->assertOk()
            ->assertSee('noindex', false);
    }

    public function test_blog_article_uses_excerpt_and_article_schema(): void
    {
        tenancy()->initialize($this->clinic);
        BlogPost::create([
            'title' => 'Stand up every hour',
            'slug' => 'stand-up',
            'excerpt' => 'A short walk beats a long sit.',
            'is_published' => true,
            'published_at' => now(),
        ]);
        tenancy()->end();

        $html = $this->get('http://seo-clinic.localhost/blog/stand-up')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('A short walk beats a long sit.', $html);
        $this->assertStringContainsString('BlogPosting', $html);
    }

    public function test_condition_topic_pages_are_indexable_and_empty_library_is_not(): void
    {
        $this->publishHome();

        $html = $this->get('http://seo-clinic.localhost/conditions/knee-pain')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Knee pain', $html);
        $this->assertStringContainsString('ACL tears', $html);
        $this->assertStringContainsString('MedicalCondition', $html);
        $this->assertStringContainsString('index, follow', $html);
        $this->assertStringContainsString('Knee pain | Pain specialist in Chattogram', $html);

        $this->get('http://seo-clinic.localhost/conditions')
            ->assertOk()
            ->assertSee('Knee pain', false);

        $this->get('http://seo-clinic.localhost/conditions/missing')
            ->assertNotFound();

        $solo = Tenant::create([
            'id' => 'seo-solo',
            'plan_tier' => 'solo',
            'name' => 'Dr Empty',
        ]);
        Domain::create(['domain' => 'seo-solo.localhost', 'tenant_id' => $solo->id]);

        $this->get('http://seo-solo.localhost/conditions')
            ->assertNotFound();
    }

    private function publishHome(): void
    {
        tenancy()->initialize($this->clinic);

        Chamber::create([
            'name' => 'Panchlaish',
            'address' => 'Panchlaish, Chattogram',
        ]);

        Doctor::create([
            'name' => 'Dr Hasan',
            'public_title' => 'Pain specialist',
            'practice_type' => Doctor::PRACTICE_GENERAL,
        ]);

        WebPage::create([
            'title' => 'Home',
            'slug' => '/',
            'is_published' => true,
            'content' => [
                [
                    'type' => 'hero',
                    'data' => [
                        'headline' => 'Relief without surgery',
                        'subheadline' => 'Pain care in Chattogram',
                    ],
                ],
                [
                    'type' => 'condition_library',
                    'data' => [
                        'heading' => 'Conditions we treat',
                        'conditions' => [
                            [
                                'name' => 'Knee pain',
                                'description' => 'Sports injuries and worn joints.',
                                'features' => [['label' => 'ACL tears']],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        tenancy()->end();
    }
}
