<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\Tenant;
use App\Models\User;
use App\Support\ProductionReadiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Voice-to-writing is stashed. These assertions fail the build if a later
 * change quietly puts the Mic back, re-registers the dictate route, or
 * loads Groq config — any of which would start sending transcripts out.
 */
class PrescriptionDictationDeferredTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_dictate_pipeline_is_not_on_disk(): void
    {
        $this->assertFileDoesNotExist(app_path('Services/PrescriptionDictationService.php'));
        $this->assertFileDoesNotExist(app_path('Http/Controllers/PrescriptionDictationController.php'));
        $this->assertFileDoesNotExist(config_path('groq.php'));
        $this->assertFileExists(base_path('docs/deferred/prescription-dictation/README.md'));
    }

    public function test_the_pad_has_no_mic_and_does_not_call_groq(): void
    {
        $desk = file_get_contents(resource_path('views/filament/tenant-admin/components/rx-desk.blade.php'));

        $this->assertStringNotContainsString("tenant_web_url('/api/prescriptions/dictate')", $desk);
        $this->assertStringNotContainsString('webkitSpeechRecognition', $desk);
        $this->assertStringNotContainsString('cs-rx-desk__mic', $desk);
        $this->assertStringNotContainsString('api.groq.com', $desk);
        $this->assertStringNotContainsString('toggleDictate', $desk);
    }

    public function test_production_check_does_not_require_groq(): void
    {
        $source = file_get_contents(app_path('Support/ProductionReadiness.php'));

        $this->assertStringNotContainsString('GROQ_', $source);
        $this->assertStringNotContainsString('groq.', $source);

        $this->assertNotContains('GROQ_DRIVER', array_column(ProductionReadiness::problems(), 'key'));
        $this->assertNotContains('GROQ_API_KEY', array_column(ProductionReadiness::problems(), 'key'));
    }

    public function test_posting_a_transcript_does_not_reach_groq(): void
    {
        $tenant = Tenant::create(['id' => 'dictate-off', 'plan_tier' => 'solo']);
        Domain::create(['domain' => 'dictate-off.localhost', 'tenant_id' => $tenant->id]);
        tenancy()->initialize($tenant);
        $doctor = User::create([
            'name' => 'Dr Off',
            'email' => 'off@dictate.test',
            'password' => Hash::make('secret'),
            'role' => User::ROLE_DOCTOR,
            'tenant_id' => $tenant->id,
        ]);
        tenancy()->end();

        Http::fake();

        $response = $this->actingAs($doctor)
            ->postJson('http://dictate-off.localhost/api/prescriptions/dictate', [
                'transcript' => 'Napa 500 1+1+1 five days',
            ]);

        // 404 if nothing matches; 405 if the GET webpage catch-all eats the
        // path. Either way there is no POST handler, so Groq is never called.
        $this->assertContains($response->status(), [404, 405]);
        Http::assertNothingSent();
    }
}
