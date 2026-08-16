<?php

namespace Tests\Feature;

use App\Filament\TenantAdmin\Pages\ConsultScreen;
use App\Filament\TenantAdmin\Pages\DailyRoster;
use App\Filament\TenantAdmin\Pages\LiveQueueControl;
use App\Models\Booking;
use App\Models\Chamber;
use App\Models\Doctor;
use App\Models\LiveSession;
use App\Models\Patient;
use App\Models\ScheduleSession;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use App\Services\TenantUserBootstrapService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Filament\Facades\Filament;
use Tests\TestCase;

class PatientRecordsStage2Test extends TestCase
{
    use RefreshDatabase;

    private Tenant $soloTenant;

    private Tenant $clinicTenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->soloTenant = Tenant::create(['id' => 'stage2-solo', 'plan_tier' => 'solo']);
        $this->clinicTenant = Tenant::create(['id' => 'stage2-clinic', 'plan_tier' => 'clinic']);
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    private function makeUser(Tenant $tenant, string $role, string $email): User
    {
        return User::create([
            'name' => ucfirst($role),
            'email' => $email,
            'password' => Hash::make('secret'),
            'role' => $role,
            'tenant_id' => $tenant->id,
        ]);
    }

  private function seedSession(Tenant $tenant): array
    {
        tenancy()->initialize($tenant);

        $chamber = Chamber::create(['name' => 'Main']);
        $doctor = Doctor::create(['name' => 'Dr Test']);
        $session = ScheduleSession::create([
            'chamber_id' => $chamber->id,
            'doctor_id' => $doctor->id,
            'day_of_week' => Carbon::today()->dayOfWeek,
            'session_name' => 'Morning',
            'start_time' => '09:00',
            'end_time' => '12:00',
            'slot_cap' => 10,
        ]);

        return compact('chamber', 'doctor', 'session');
    }

    public function test_admin_cannot_manage_queue_or_open_clinical_screens(): void
    {
        tenancy()->initialize($this->soloTenant);
        $admin = $this->makeUser($this->soloTenant, User::ROLE_ADMIN, 'owner@stage2.test');

        $this->assertFalse($admin->canManageQueue());
        $this->assertFalse($admin->canOperateQueueControls());
        $this->assertFalse($admin->canAccessLiveQueueControl());
        $this->assertFalse($admin->canViewConsultScreen());
        $this->assertFalse($admin->canRecordVisitNotes());

        $this->actingAs($admin);
        $this->assertFalse(LiveQueueControl::canAccess());
        $this->assertTrue(DailyRoster::canAccess());
        $this->assertFalse(ConsultScreen::canAccess());
    }

    public function test_staff_run_queue_gives_staff_controls_not_doctor(): void
    {
        tenancy()->initialize($this->soloTenant);
        tenant()->update(['queue_runner' => Tenant::QUEUE_RUNNER_STAFF]);

        $doctor = $this->makeUser($this->soloTenant, User::ROLE_DOCTOR, 'doc@stage2.test');
        $staff = $this->makeUser($this->soloTenant, User::ROLE_STAFF, 'staff@stage2.test');

        $this->assertTrue($staff->canOperateQueueControls());
        $this->assertTrue($staff->canAccessLiveQueueControl());
        $this->assertFalse($doctor->canOperateQueueControls());
        $this->assertFalse($doctor->canAccessLiveQueueControl());
        $this->assertTrue($doctor->canViewConsultScreen());
    }

    public function test_doctor_run_queue_gives_doctor_controls_not_staff(): void
    {
        tenancy()->initialize($this->soloTenant);
        tenant()->update(['queue_runner' => Tenant::QUEUE_RUNNER_DOCTOR]);

        $doctor = $this->makeUser($this->soloTenant, User::ROLE_DOCTOR, 'doc2@stage2.test');
        $staff = $this->makeUser($this->soloTenant, User::ROLE_STAFF, 'staff2@stage2.test');

        $this->assertTrue($doctor->canOperateQueueControls());
        $this->assertTrue($doctor->canAccessLiveQueueControl());
        $this->assertFalse($staff->canOperateQueueControls());
        $this->assertFalse($staff->canAccessLiveQueueControl());

        $this->actingAs($staff);
        $this->assertTrue(DailyRoster::canAccess());
    }

    public function test_consult_screen_shows_patient_when_current_booking_is_set(): void
    {
        ['session' => $scheduleSession] = $this->seedSession($this->soloTenant);
        $doctor = $this->makeUser($this->soloTenant, User::ROLE_DOCTOR, 'doc3@stage2.test');

        $patient = Patient::create([
            'name' => 'Karim Uddin',
            'phone' => '01711112222',
            'sex' => 'male',
            'age' => 45,
            'age_recorded_at' => now()->subYears(1),
            'allergies' => 'Penicillin',
        ]);

        $booking = Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $scheduleSession->id,
            'booking_date' => Carbon::today(),
            'patient_id' => $patient->id,
            'patient_name' => $patient->name,
            'patient_phone' => $patient->phone,
            'serial_number' => 3,
            'status' => 'in_chamber',
        ]);

        Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $scheduleSession->id,
            'booking_date' => Carbon::today()->subWeek(),
            'patient_id' => $patient->id,
            'patient_name' => $patient->name,
            'patient_phone' => $patient->phone,
            'serial_number' => 1,
            'status' => 'completed',
            'completed_at' => now()->subWeek(),
        ]);

        LiveSession::create([
            'schedule_session_id' => $scheduleSession->id,
            'session_date' => Carbon::today(),
            'status' => 'active',
            'started_at' => now(),
            'current_booking_id' => $booking->id,
        ]);

        $this->actingAs($doctor);
        Filament::setCurrentPanel(Filament::getPanel('tenantAdmin'));

        Livewire::test(ConsultScreen::class)
            ->assertSee('Karim Uddin')
            ->assertSee('Penicillin')
            ->assertSee('1 previous visits · no notes recorded')
            ->assertDontSee('#3')
            ->assertDontSee('serial');
    }

    public function test_solo_consult_screen_hides_doctor_column_and_lab_orders(): void
    {
        ['session' => $scheduleSession] = $this->seedSession($this->soloTenant);
        $doctor = $this->makeUser($this->soloTenant, User::ROLE_DOCTOR, 'doc4@stage2.test');

        $patient = Patient::create(['name' => 'Solo Patient', 'phone' => '01722223333']);
        $booking = Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $scheduleSession->id,
            'booking_date' => Carbon::today(),
            'patient_id' => $patient->id,
            'patient_name' => $patient->name,
            'patient_phone' => $patient->phone,
            'serial_number' => 1,
            'status' => 'called',
        ]);

        LiveSession::create([
            'schedule_session_id' => $scheduleSession->id,
            'session_date' => Carbon::today(),
            'status' => 'active',
            'started_at' => now(),
            'current_booking_id' => $booking->id,
        ]);

        $this->actingAs($doctor);
        Filament::setCurrentPanel(Filament::getPanel('tenantAdmin'));

        Livewire::test(ConsultScreen::class)
            ->assertSee('Solo Patient')
            ->assertDontSee('Lab orders')
            ->assertDontSee('Dr Test');
    }

    public function test_clinic_consult_screen_shows_doctor_and_lab_sections(): void
    {
        ['session' => $scheduleSession, 'doctor' => $clinicDoctor] = $this->seedSession($this->clinicTenant);
        tenancy()->initialize($this->clinicTenant);

        $user = $this->makeUser($this->clinicTenant, User::ROLE_DOCTOR, 'clinicdoc@stage2.test');

        $patient = Patient::create(['name' => 'Clinic Patient', 'phone' => '01733334444']);
        Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $scheduleSession->id,
            'booking_date' => Carbon::today()->subDays(3),
            'patient_id' => $patient->id,
            'patient_name' => $patient->name,
            'patient_phone' => $patient->phone,
            'serial_number' => 2,
            'status' => 'completed',
            'completed_at' => now()->subDays(3),
        ]);

        $booking = Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $scheduleSession->id,
            'booking_date' => Carbon::today(),
            'patient_id' => $patient->id,
            'patient_name' => $patient->name,
            'patient_phone' => $patient->phone,
            'serial_number' => 5,
            'status' => 'in_chamber',
        ]);

        LiveSession::create([
            'schedule_session_id' => $scheduleSession->id,
            'session_date' => Carbon::today(),
            'status' => 'active',
            'started_at' => now(),
            'current_booking_id' => $booking->id,
        ]);

        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('tenantAdmin'));

        Livewire::test(ConsultScreen::class)
            ->assertSee('Clinic Patient')
            ->assertSee('Lab orders')
            ->assertSee($clinicDoctor->name);
    }

    public function test_tenant_bootstrap_ensures_doctor_login(): void
    {
        tenancy()->initialize($this->soloTenant);

        $service = app(TenantUserBootstrapService::class);
        $this->assertFalse($service->hasDoctorLogin($this->soloTenant));

        $doctor = $service->ensureDoctorLogin(
            $this->soloTenant,
            'bootstrap@stage2.test',
            'Bootstrap Doctor',
        );

        $this->assertTrue($service->hasDoctorLogin($this->soloTenant));
        $this->assertSame(User::ROLE_DOCTOR, $doctor->role);
        $this->assertSame('bootstrap@stage2.test', $doctor->email);
    }

    public function test_solo_seeder_path_has_doctor_login(): void
    {
        $this->artisan('db:seed', ['--class' => DatabaseSeeder::class]);

        $solo = Tenant::find('solo');
        $this->assertNotNull($solo);

        $service = app(TenantUserBootstrapService::class);
        $this->assertTrue($service->hasDoctorLogin($solo));

        $doctorUser = User::withoutGlobalScopes()
            ->where('tenant_id', 'solo')
            ->where('role', User::ROLE_DOCTOR)
            ->first();

        $this->assertNotNull($doctorUser);
        $this->assertSame('doctor@solo.com', $doctorUser->email);
    }
}
