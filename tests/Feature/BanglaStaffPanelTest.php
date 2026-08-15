<?php

namespace Tests\Feature;

use App\Filament\TenantAdmin\Pages\LiveQueueControl;
use App\Models\Chamber;
use App\Models\Doctor;
use App\Models\Domain;
use App\Models\LiveSession;
use App\Models\ScheduleSession;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class BanglaStaffPanelTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $doctor;

    private ScheduleSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'id' => 'bn-desk',
            'plan_tier' => 'clinic',
            'default_locale' => 'bn',
            'queue_runner' => 'doctor',
        ]);
        Domain::create(['domain' => 'bn-desk.localhost', 'tenant_id' => 'bn-desk']);

        tenancy()->initialize($this->tenant);

        $this->doctor = User::create([
            'name' => 'Dr Bangla',
            'email' => 'doc@bn-desk.test',
            'password' => Hash::make('secret'),
            'role' => User::ROLE_DOCTOR,
            'tenant_id' => $this->tenant->id,
        ]);

        $chamber = Chamber::create(['name' => 'Main']);
        $doctorProfile = Doctor::create([
            'name' => 'Dr Bangla',
            'user_id' => $this->doctor->id,
        ]);
        $this->session = ScheduleSession::create([
            'chamber_id' => $chamber->id,
            'doctor_id' => $doctorProfile->id,
            'day_of_week' => Carbon::today()->dayOfWeek,
            'session_name' => 'Morning',
            'start_time' => '09:00',
            'end_time' => '12:00',
            'slot_cap' => 10,
        ]);

        LiveSession::create([
            'schedule_session_id' => $this->session->id,
            'session_date' => today()->toDateString(),
            'status' => 'active',
            'started_at' => now(),
        ]);

        tenancy()->end();
    }

    public function test_live_queue_renders_bangla_call_next_control(): void
    {
        tenancy()->initialize($this->tenant);
        app()->setLocale('bn');
        Filament::setCurrentPanel('tenantAdmin');

        Livewire::actingAs($this->doctor)
            ->test(LiveQueueControl::class)
            ->assertSee('সেশন শেষ করুন')
            ->assertDontSee('Finish / End Session');

        tenancy()->end();
    }

    public function test_session_locale_override_shows_english_again(): void
    {
        tenancy()->initialize($this->tenant);
        app()->setLocale('en');
        session()->put('locale', 'en');
        Filament::setCurrentPanel('tenantAdmin');

        Livewire::actingAs($this->doctor)
            ->test(LiveQueueControl::class)
            ->assertSee('Finish / End Session')
            ->assertDontSee('সেশন শেষ করুন');

        tenancy()->end();
    }
}
