<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\Tenant;
use App\Models\WebPage;
use App\Support\PublicStoredImage;
use App\Support\SafeUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HeroImageUploadTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'id' => 'hero-upload',
            'name' => 'Dr. Upload Chamber',
            'plan_tier' => 'solo',
        ]);
        Domain::create(['domain' => 'hero-upload.test', 'tenant_id' => 'hero-upload']);
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    public function test_public_disk_path_becomes_a_storage_url(): void
    {
        $this->assertSame(
            '/storage/webpage-hero/hero-upload/photo.jpg',
            PublicStoredImage::toPublicPath('webpage-hero/hero-upload/photo.jpg'),
        );
        $this->assertSame(
            'webpage-hero/hero-upload/photo.jpg',
            PublicStoredImage::toDiskPath('/storage/webpage-hero/hero-upload/photo.jpg'),
        );
        $this->assertSame(
            'https://images.example/doc.jpg',
            PublicStoredImage::toPublicPath('https://images.example/doc.jpg'),
        );
        $this->assertNull(PublicStoredImage::toDiskPath('https://images.example/doc.jpg'));
    }

    public function test_uploaded_hero_path_survives_url_scrub_and_renders(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('webpage-hero/hero-upload/photo.jpg', 'fake-image');

        tenancy()->initialize($this->tenant);

        $page = WebPage::create([
            'title' => 'Home',
            'slug' => '/',
            'is_published' => true,
            'content' => [[
                'type' => 'hero',
                'data' => [
                    'headline' => 'Care that respects your time',
                    'cta_text' => 'Book Appointment',
                    'cta_link' => '/book',
                    'image_url' => '/storage/webpage-hero/hero-upload/photo.jpg',
                ],
            ]],
        ]);

        $this->assertSame(
            '/storage/webpage-hero/hero-upload/photo.jpg',
            $page->fresh()->content[0]['data']['image_url'],
        );

        tenancy()->end();

        $html = $this->get('http://hero-upload.test/')->assertOk()->getContent();
        $this->assertStringContainsString('/storage/webpage-hero/hero-upload/photo.jpg', $html);
    }

    public function test_safe_url_keeps_storage_paths_and_https_images(): void
    {
        $block = SafeUrl::sanitizeBuilderBlock([
            'type' => 'hero',
            'data' => [
                'image_url' => '/storage/webpage-hero/hero-upload/photo.jpg',
                'cta_link' => '/book',
            ],
        ]);

        $this->assertSame('/storage/webpage-hero/hero-upload/photo.jpg', $block['data']['image_url']);
        $this->assertSame('/book', $block['data']['cta_link']);
    }

    public function test_relative_hero_upload_path_is_promoted_to_storage_url(): void
    {
        tenancy()->initialize($this->tenant);

        $page = WebPage::create([
            'title' => 'Home',
            'slug' => '/',
            'is_published' => true,
            'content' => [[
                'type' => 'hero',
                'data' => [
                    'headline' => 'Care',
                    'image_url' => 'webpage-hero/hero-upload/photo.jpg',
                ],
            ]],
        ]);

        $this->assertSame(
            '/storage/webpage-hero/hero-upload/photo.jpg',
            $page->fresh()->content[0]['data']['image_url'],
        );
    }

    public function test_public_disk_stays_web_visible_after_tenancy_starts(): void
    {
        tenancy()->initialize($this->tenant);

        $path = str_replace('\\', '/', Storage::disk('public')->path('webpage-hero/probe.jpg'));

        $this->assertStringContainsString('storage/app/public', $path);
        $this->assertStringNotContainsString('storage/tenant', $path);
    }

    public function test_uploaded_educational_video_is_copied_to_video_url_and_renders(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('webpage-videos/hero-upload/clip.mp4', 'fake-video');
        Storage::disk('public')->put('webpage-video-thumbs/hero-upload/cover.jpg', 'fake-thumb');

        tenancy()->initialize($this->tenant);

        $page = WebPage::create([
            'title' => 'Home',
            'slug' => '/',
            'is_published' => true,
            'content' => [[
                'type' => 'video_gallery',
                'data' => [
                    'heading' => 'Latest Educational Videos',
                    'videos' => [[
                        'title' => 'Diabetes mellitus explained',
                        'type' => 'upload',
                        'uploaded_video' => 'webpage-videos/hero-upload/clip.mp4',
                        'thumbnail_url' => '/storage/webpage-video-thumbs/hero-upload/cover.jpg',
                    ]],
                ],
            ]],
        ]);

        $video = $page->fresh()->content[0]['data']['videos'][0];
        $this->assertSame('/storage/webpage-videos/hero-upload/clip.mp4', $video['video_url']);
        $this->assertSame('/storage/webpage-video-thumbs/hero-upload/cover.jpg', $video['thumbnail_url']);

        tenancy()->end();

        $html = $this->get('http://hero-upload.test/')->assertOk()->getContent();
        $this->assertStringContainsString('Latest Educational Videos', $html);
        $this->assertStringContainsString('/storage/webpage-videos/hero-upload/clip.mp4', $html);
        $this->assertStringContainsString('/storage/webpage-video-thumbs/hero-upload/cover.jpg', $html);
        $this->assertStringContainsString('Diabetes mellitus explained', $html);
    }
}
