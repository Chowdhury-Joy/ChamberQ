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

    public function test_live_queue_renders_bangla_stats_and_english_buttons(): void
    {
        tenancy()->initialize($this->tenant);
        app()->setLocale('bn');
        Filament::setCurrentPanel('tenantAdmin');

        Livewire::actingAs($this->doctor)
            ->test(LiveQueueControl::class)
            ->assertSee('অপেক্ষমাণ')
            ->assertDontSee('Waiting')
            ->assertSee('Finish / End Session')
            ->assertDontSee('সেশন শেষ করুন');

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
            ->assertSee('Waiting')
            ->assertDontSee('অপেক্ষমাণ')
            ->assertSee('Finish / End Session')
            ->assertDontSee('সেশন শেষ করুন');

        tenancy()->end();
    }

    public function test_chamber_language_bangla_renders_the_desk_in_bangla_over_http(): void
    {
        $desk = 'http://bn-desk.localhost/admin/live-queue-control';

        $this->actingAs($this->doctor)
            ->get($desk)
            ->assertOk()
            ->assertSee('অপেক্ষমাণ')
            ->assertSee('Finish / End Session')
            ->assertDontSee('সেশন শেষ করুন')
            ->assertSee('Live Queue Control')
            ->assertDontSee('লাইভ কিউ নিয়ন্ত্রণ')
            ->assertSee('/lang/en', escape: false);
    }

    public function test_switch_to_bangla_from_the_desk_stays_there_and_shows_bangla(): void
    {
        $this->tenant->update(['default_locale' => 'en']);

        $desk = 'http://bn-desk.localhost/admin/live-queue-control';

        $this->actingAs($this->doctor)
            ->get($desk)
            ->assertOk()
            ->assertSee('Finish / End Session')
            ->assertDontSee('সেশন শেষ করুন')
            ->assertSee('/lang/bn', escape: false);

        $this->get('http://bn-desk.localhost/lang/bn', [
            'Referer' => $desk,
        ])->assertRedirect($desk);

        $this->assertSame('bn', session('locale'));

        $this->get($desk)
            ->assertOk()
            ->assertSee('অপেক্ষমাণ')
            ->assertSee('Finish / End Session')
            ->assertDontSee('সেশন শেষ করুন');
    }

    public function test_staff_language_switch_without_referer_returns_to_the_desk(): void
    {
        $this->actingAs($this->doctor)
            ->get('http://bn-desk.localhost/lang/bn')
            ->assertRedirect('http://bn-desk.localhost/admin');

        $this->assertSame('bn', session('locale'));
    }

    public function test_guest_language_switch_without_referer_stays_on_the_public_site(): void
    {
        $this->get('http://bn-desk.localhost/lang/bn')
            ->assertRedirect('http://bn-desk.localhost');
    }

    public function test_path_staff_language_switch_without_referer_returns_to_the_desk(): void
    {
        $this->actingAs($this->doctor)
            ->get('http://localhost/bn-desk/lang/bn')
            ->assertRedirect('http://localhost/bn-desk/admin');
    }

    public function test_path_admin_follows_chamber_language_bangla(): void
    {
        $desk = 'http://localhost/bn-desk/admin/live-queue-control';

        $this->actingAs($this->doctor)
            ->get($desk)
            ->assertOk()
            ->assertSee('অপেক্ষমাণ')
            ->assertSee('Finish / End Session')
            ->assertDontSee('সেশন শেষ করুন');
    }

    public function test_dashboard_widgets_render_in_bangla(): void
    {
        tenancy()->initialize($this->tenant);
        app()->setLocale('bn');
        Filament::setCurrentPanel('tenantAdmin');

        Livewire::actingAs($this->doctor)
            ->test(\App\Filament\TenantAdmin\Widgets\TenantStatsOverview::class)
            ->assertSee('আজকের অ্যাপয়েন্টমেন্ট')
            ->assertDontSee("Today's Appointments");

        Livewire::actingAs($this->doctor)
            ->test(\App\Filament\TenantAdmin\Widgets\TodayAppointmentsWidget::class)
            ->assertSee('আজকের নির্ধারিত অ্যাপয়েন্টমেন্ট')
            ->assertDontSee("Today's Scheduled Appointments");

        tenancy()->end();
    }
}
