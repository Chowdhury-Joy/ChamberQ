<?php

namespace Tests\Feature;

use App\Filament\TenantAdmin\Pages\BrandingSettings;
use App\Filament\TenantAdmin\Resources\BlogPosts\Pages\CreateBlogPost;
use App\Filament\TenantAdmin\Resources\Departments\Pages\CreateDepartment;
use App\Filament\TenantAdmin\Resources\Doctors\Pages\CreateDoctor;
use App\Models\BlogPost;
use App\Models\Department;
use App\Models\Doctor;
use App\Models\Domain;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WebPage;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Every website image is picked from the staff member's own computer. The
 * fields used to be pasted URLs, so each one has to survive the same journey:
 * Livewire temp file → public disk → `/storage/…` in the database → <img src>.
 */
class WebsiteImageUploadTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('livewire-tmp');

        $this->tenant = Tenant::create([
            'id' => 'image-upload',
            'name' => 'Upload Clinic',
            'plan_tier' => 'clinic',
        ]);
        Domain::create(['domain' => 'image-upload.test', 'tenant_id' => 'image-upload']);

        tenancy()->initialize($this->tenant);

        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@image-upload.test',
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

    public function test_blog_featured_image_is_uploaded_not_typed(): void
    {
        Livewire::test(CreateBlogPost::class)
            ->fillForm([
                'title' => 'Managing blood pressure',
                'slug' => 'managing-blood-pressure',
                'image_url' => [UploadedFile::fake()->image('featured.jpg')],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $stored = (string) BlogPost::firstOrFail()->image_url;

        $this->assertStringStartsWith('/storage/blog-images/image-upload/', $stored);
        $this->assertTrue(Storage::disk('public')->exists(substr($stored, strlen('/storage/'))));
    }

    public function test_department_card_image_is_uploaded_not_typed(): void
    {
        Livewire::test(CreateDepartment::class)
            ->fillForm([
                'title' => 'Physiotherapy',
                'slug' => 'physiotherapy',
                'image_url' => [UploadedFile::fake()->image('card.png')],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $stored = (string) Department::firstOrFail()->image_url;

        $this->assertStringStartsWith('/storage/department-images/image-upload/', $stored);
        $this->assertTrue(Storage::disk('public')->exists(substr($stored, strlen('/storage/'))));
    }

    public function test_doctor_photo_is_uploaded_not_typed(): void
    {
        Livewire::test(CreateDoctor::class)
            ->fillForm([
                'name' => 'Dr. Antar Das',
                'practice_type' => Doctor::PRACTICE_GENERAL,
                'show_on_website' => true,
                'photo_url' => [UploadedFile::fake()->image('portrait.jpg')],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $stored = (string) Doctor::firstOrFail()->photo_url;

        $this->assertStringStartsWith('/storage/doctor-photos/image-upload/', $stored);
        $this->assertTrue(Storage::disk('public')->exists(substr($stored, strlen('/storage/'))));
    }

    public function test_branding_logo_and_favicon_are_uploaded_not_typed(): void
    {
        Livewire::test(BrandingSettings::class)
            ->fillForm([
                'name' => 'Upload Clinic',
                'logo_url' => [UploadedFile::fake()->image('logo.png')],
                'favicon_url' => [UploadedFile::fake()->image('icon.png')],
                'theme_color' => '#0f766e',
                'font_family' => 'Outfit',
                'default_locale' => 'en',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->tenant->refresh();

        $this->assertStringStartsWith('/storage/branding-logos/image-upload/', (string) $this->tenant->logo_url);
        $this->assertStringStartsWith('/storage/branding-icons/image-upload/', (string) $this->tenant->favicon_url);
        $this->assertTrue(Storage::disk('public')->exists(
            substr((string) $this->tenant->logo_url, strlen('/storage/'))
        ));
    }

    public function test_page_builder_image_keys_survive_the_url_scrub(): void
    {
        $page = WebPage::create([
            'title' => 'Home',
            'slug' => '/',
            'is_published' => true,
            'content' => [
                [
                    'type' => 'image_carousel',
                    'data' => [
                        'heading' => 'Facility Tour',
                        'items' => [[
                            'image_url' => 'webpage-gallery/image-upload/slide.jpg',
                            'title' => 'Reception',
                        ]],
                    ],
                ],
                [
                    'type' => 'testimonials',
                    'data' => [
                        'heading' => 'What our patients say',
                        'items' => [[
                            'quote' => 'Calm and clear.',
                            'name' => 'Rashida Begum',
                            'photo_url' => 'webpage-testimonials/image-upload/avatar.jpg',
                        ]],
                    ],
                ],
                [
                    'type' => 'faq',
                    'data' => [
                        'heading' => 'Frequently Asked Questions',
                        'promo_image_url' => 'webpage-faq/image-upload/promo.jpg',
                    ],
                ],
                [
                    'type' => 'about_facility',
                    'data' => [
                        'heading' => 'About Our Practice',
                        'gallery' => [[
                            'title' => 'Therapy room',
                            'image_url' => 'webpage-facility/image-upload/room.jpg',
                        ]],
                    ],
                ],
            ],
        ]);

        $content = $page->fresh()->content;

        $this->assertSame('/storage/webpage-gallery/image-upload/slide.jpg', $content[0]['data']['items'][0]['image_url']);
        $this->assertSame('/storage/webpage-testimonials/image-upload/avatar.jpg', $content[1]['data']['items'][0]['photo_url']);
        $this->assertSame('/storage/webpage-faq/image-upload/promo.jpg', $content[2]['data']['promo_image_url']);
        $this->assertSame('/storage/webpage-facility/image-upload/room.jpg', $content[3]['data']['gallery'][0]['image_url']);
    }

    public function test_previously_pasted_links_still_work_after_a_save(): void
    {
        $post = BlogPost::create([
            'title' => 'Older post',
            'slug' => 'older-post',
            'image_url' => 'https://images.example/featured.jpg',
        ]);

        $doctor = Doctor::create([
            'name' => 'Dr. Legacy',
            'show_on_website' => true,
            'photo_url' => 'https://images.example/portrait.jpg',
        ]);

        $this->assertSame('https://images.example/featured.jpg', $post->fresh()->image_url);
        $this->assertSame('https://images.example/portrait.jpg', $doctor->fresh()->photo_url);
    }

    public function test_javascript_urls_are_still_scrubbed_from_image_fields(): void
    {
        $post = BlogPost::create([
            'title' => 'Hostile post',
            'slug' => 'hostile-post',
            'image_url' => 'javascript:alert(1)',
        ]);

        $doctor = Doctor::create([
            'name' => 'Dr. Hostile',
            'show_on_website' => true,
            'photo_url' => 'javascript:alert(2)',
        ]);

        $this->assertSame('', $post->fresh()->image_url);
        $this->assertSame('', $doctor->fresh()->photo_url);
    }
}
