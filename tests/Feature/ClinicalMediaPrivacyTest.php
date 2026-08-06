<?php

namespace Tests\Feature;

use App\Services\VisitMediaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Consultation voice notes and prescription photos must never be fetchable
 * without a doctor login. They previously sat on the `public` disk, which is
 * symlinked into the web root and served directly by the web server — the
 * authenticated controller was real but bypassable by anyone holding the URL.
 *
 * These tests assert the property, not the implementation: whatever disk is
 * chosen, the bytes must not be reachable over HTTP.
 */
class ClinicalMediaPrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_clinical_media_is_not_written_inside_the_web_root(): void
    {
        $root = realpath(base_path());
        $webRoot = realpath(public_path());

        $probe = 'visit-audio/probe-tenant/'.uniqid().'.webm';
        Storage::disk('local')->put($probe, 'consultation-audio');

        $absolute = realpath(Storage::disk('local')->path($probe));

        $this->assertNotFalse($absolute);
        $this->assertStringStartsWith($root, $absolute);
        $this->assertStringNotContainsString(
            $webRoot.DIRECTORY_SEPARATOR,
            $absolute,
            'Clinical media was written inside the public web root and is served without auth.'
        );

        Storage::disk('local')->delete($probe);
    }

    public function test_clinical_media_is_not_served_over_http(): void
    {
        $probe = 'visit-photos/probe-tenant/'.uniqid().'.jpg';
        Storage::disk('local')->put($probe, 'prescription-photo');

        // Laravel registers a /storage/{path} route for the local disk; it must
        // refuse private files rather than hand them out.
        $this->get('/storage/'.$probe)->assertForbidden();

        Storage::disk('local')->delete($probe);
    }

    public function test_service_exposes_no_public_url_accessor(): void
    {
        // A URL accessor would recreate the unauthenticated path the private
        // disk exists to remove, so the service must not grow one.
        $methods = get_class_methods(VisitMediaService::class);

        foreach ($methods as $method) {
            $this->assertStringNotContainsStringIgnoringCase(
                'url',
                $method,
                "VisitMediaService::{$method}() looks like a public URL accessor."
            );
        }
    }
}
