<?php

namespace Tests\Feature;

use App\Models\Chamber;
use App\Models\Doctor;
use App\Models\LiveSession;
use App\Models\ScheduleSession;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LiveQueueLivewireUriTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $doctor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'id' => 'mups',
            'plan_tier' => 'clinic',
            'queue_runner' => 'doctor',
        ]);

        tenancy()->initialize($this->tenant);

        $this->doctor = User::create([
            'name' => 'Dr Queue',
            'email' => 'doctor@mups.local',
            'password' => Hash::make('pass'),
            'role' => User::ROLE_DOCTOR,
            'tenant_id' => $this->tenant->id,
        ]);

        $chamber = Chamber::create(['name' => 'Main']);
        $profile = Doctor::create([
            'name' => 'Dr Queue',
            'user_id' => $this->doctor->id,
        ]);
        $session = ScheduleSession::create([
            'chamber_id' => $chamber->id,
            'doctor_id' => $profile->id,
            'day_of_week' => Carbon::today()->dayOfWeek,
            'session_name' => 'Morning',
            'start_time' => '09:00',
            'end_time' => '12:00',
            'slot_cap' => 10,
        ]);
        LiveSession::create([
            'schedule_session_id' => $session->id,
            'session_date' => today()->toDateString(),
            'status' => 'active',
            'started_at' => now(),
        ]);

        tenancy()->end();
    }

    public function test_path_live_queue_page_points_livewire_at_the_update_endpoint(): void
    {
        $html = $this->actingAs($this->doctor)
            ->get('http://127.0.0.1/mups/admin/live-queue-control')
            ->assertOk()
            ->getContent();

        preg_match('/data-update-uri="([^"]*)"/', $html, $uri);
        preg_match('/livewireScriptConfig = ({.*?});/', $html, $config);

        $this->assertNotEmpty($uri[1] ?? null, $html);
        $this->assertNotSame('/', $uri[1]);
        $this->assertStringContainsString('livewire/update', $uri[1]);

        if (isset($config[1])) {
            $decoded = json_decode($config[1], true);
            $this->assertIsArray($decoded);
            $this->assertNotSame('/', $decoded['uri'] ?? null);
            $this->assertStringContainsString('livewire/update', (string) ($decoded['uri'] ?? ''));
        }

        $this->assertDoesNotMatchRegularExpression(
            '/<form[^>]+action="\/"/',
            $html
        );
        $this->assertStringNotContainsString('http://localhost/livewire', $html);
        $this->assertStringContainsString('serviceWorker.getRegistrations', $html);
        $this->assertStringContainsString('.unregister()', $html);

        $this->actingAs($this->doctor)
            ->withHeaders([
                'X-Livewire' => 'true',
                'Referer' => 'http://127.0.0.1/mups/admin/live-queue-control',
            ])
            ->postJson('http://127.0.0.1/livewire/update', ['components' => []])
            ->assertStatus(404);
    }

    public function test_patient_service_worker_does_not_claim_the_staff_desk(): void
    {
        $js = $this->get('http://127.0.0.1/mups/sw.js')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString("url.pathname.includes('/admin')", $js);
        $this->assertStringContainsString("url.pathname.includes('/livewire/')", $js);
        $this->assertStringContainsString('self.registration.unregister()', $js);
        $this->assertStringContainsString('clinic-shell-v10', $js);
    }
}
