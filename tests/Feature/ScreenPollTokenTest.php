<?php

namespace Tests\Feature;

use App\Models\Chamber;
use App\Models\Doctor;
use App\Models\Domain;
use App\Models\ScheduleSession;
use App\Models\Tenant;
use App\Support\ScreenPollToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScreenPollTokenTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['id' => 'screen-token', 'plan_tier' => 'solo']);
        Domain::create(['domain' => 'screen-token.localhost', 'tenant_id' => $this->tenant->id]);

        tenancy()->initialize($this->tenant);
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    public function test_screen_json_poll_requires_a_valid_token(): void
    {
        $chamber = Chamber::create(['name' => 'TV Chamber']);
        $doctor = Doctor::create(['name' => 'Dr Screen']);
        $session = ScheduleSession::create([
            'chamber_id' => $chamber->id,
            'doctor_id' => $doctor->id,
            'day_of_week' => now()->dayOfWeek,
            'session_name' => 'Morning',
            'start_time' => '09:00',
            'end_time' => '12:00',
            'slot_cap' => 20,
        ]);

        $this->get('http://screen-token.localhost/api/screen/'.$session->id)
            ->assertNotFound();

        $token = ScreenPollToken::forSession($session->id);

        $this->get('http://screen-token.localhost/api/screen/'.$session->id.'?token='.$token)
            ->assertOk()
            ->assertJsonStructure(['status', 'session_date']);
    }

    public function test_chamber_screen_json_poll_requires_a_valid_token(): void
    {
        $chamber = Chamber::create(['name' => 'TV Chamber']);

        $this->get('http://screen-token.localhost/api/screen/chamber/'.$chamber->id)
            ->assertNotFound();

        $token = ScreenPollToken::forChamber($chamber->id);

        $this->get('http://screen-token.localhost/api/screen/chamber/'.$chamber->id.'?token='.$token)
            ->assertOk()
            ->assertJsonStructure(['session_date', 'rooms']);
    }
}
