<?php

namespace Tests\Feature;

use App\Filament\TenantAdmin\Support\VisitNotesFormSchema;
use App\Models\Domain;
use App\Models\Tenant;
use App\Models\User;
use App\Services\VisitMediaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VoiceAutofillTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $doctor;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('transcription.driver', 'array');

        $this->tenant = Tenant::create([
            'id' => 'voice-autofill',
            'plan_tier' => 'solo',
            'feature_flags' => ['voice_transcription' => true],
        ]);
        Domain::create(['domain' => 'voice-autofill.localhost', 'tenant_id' => $this->tenant->id]);

        tenancy()->initialize($this->tenant);

        $this->doctor = User::create([
            'name' => 'Dr Voice',
            'email' => 'doc@voice.test',
            'password' => Hash::make('secret'),
            'role' => User::ROLE_DOCTOR,
            'tenant_id' => $this->tenant->id,
        ]);

        tenancy()->end();
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    public function test_transcribe_endpoint_returns_structured_draft_when_enabled(): void
    {
        tenancy()->initialize($this->tenant);

        $path = app(VisitMediaService::class)->voiceDirectory().'/test.webm';
        Storage::disk('local')->put($path, 'audio-bytes');

        $response = $this->actingAs($this->doctor)
            ->postJson('http://voice-autofill.localhost/api/visit-media/transcribe', [
                'path' => $path,
            ]);

        $response->assertOk()
            ->assertJsonPath('diagnosis_free_text', 'Gastritis')
            ->assertJsonPath('prescription_items.0.medicine_name', 'NAPA');

        Storage::disk('local')->delete($path);
    }

    public function test_visit_notes_draft_merges_only_blank_fields(): void
    {
        tenancy()->initialize($this->tenant);

        $current = [
            'advice' => 'Already written',
            'diagnosis' => null,
            'prescription_items' => [],
        ];

        $merged = VisitNotesFormSchema::mergeDraftIntoState($current, [
            'transcript' => 'Patient has gastritis.',
            'diagnosis_free_text' => 'Gastritis',
            'advice' => 'Avoid spicy food',
            'machine_filled' => ['voice_transcript', 'diagnosis_free_text', 'advice', 'prescription_items'],
            'prescription_items' => [
                ['medicine_name' => 'NAPA', 'frequency' => '1+1+1', 'duration' => '5 days'],
            ],
        ]);

        $this->assertSame('Already written', $merged['advice']);
        $this->assertSame(VisitNotesFormSchema::FREE_DIAGNOSIS_PREFIX.'Gastritis', $merged['diagnosis']);
        $this->assertSame('Patient has gastritis.', $merged['voice_transcript']);
        $this->assertSame('NAPA', $merged['prescription_items'][0]['medicine_name']);
    }
}
