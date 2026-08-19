<?php

namespace Tests\Feature;

use App\Filament\TenantAdmin\Pages\Cashbook;
use App\Filament\TenantAdmin\Pages\LiveQueueControl;
use App\Filament\TenantAdmin\Resources\Users\UserResource;
use App\Models\Booking;
use App\Models\Chamber;
use App\Models\Doctor;
use App\Models\Domain;
use App\Models\LiveSession;
use App\Models\Patient;
use App\Models\ScheduleSession;
use App\Models\Tenant;
use App\Models\User;
use App\Services\VisitRecordService;
use App\Support\ChamberQHelperAccess;
use App\Support\StaffDeskJobs;
use App\Support\StaffDeskScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StaffDeskJobsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private ScheduleSession $session;

    private string $host;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'id' => 'desk-jobs-clinic',
            'plan_tier' => 'clinic',
            'queue_runner' => Tenant::QUEUE_RUNNER_STAFF,
            'feature_flags' => Tenant::mergeStationsFlag(
                Tenant::featureFlagsWithModules([], [
                    Tenant::MODULE_FRONT_DOOR,
                    Tenant::MODULE_LIVE_QUEUE,
                ]),
                true,
            ),
        ]);
        Domain::create(['domain' => 'desk-jobs.localhost', 'tenant_id' => 'desk-jobs-clinic']);
        $this->host = 'http://desk-jobs.localhost';

        tenancy()->initialize($this->tenant);

        $chamber = Chamber::create(['name' => 'Main']);
        $doctor = Doctor::create(['name' => 'Dr Jobs']);
        $this->session = ScheduleSession::create([
            'chamber_id' => $chamber->id,
            'doctor_id' => $doctor->id,
            'day_of_week' => now()->dayOfWeek,
            'session_name' => 'Morning',
            'start_time' => '09:00',
            'end_time' => '12:00',
            'slot_cap' => 20,
        ]);

        LiveSession::create([
            'schedule_session_id' => $this->session->id,
            'session_date' => today()->toDateString(),
            'status' => 'active',
            'started_at' => now(),
        ]);

        tenancy()->end();
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    private function makeStaff(string $email, ?array $deskJobs = null, bool $lead = false): User
    {
        return User::create([
            'name' => $email,
            'email' => $email,
            'password' => Hash::make('secret'),
            'role' => User::ROLE_STAFF,
            'tenant_id' => 'desk-jobs-clinic',
            'desk_jobs' => $deskJobs,
            'desk_is_lead' => $lead,
        ]);
    }

    public function test_empty_desk_jobs_keeps_all_three_jobs(): void
    {
        tenancy()->initialize($this->tenant);

        $staff = $this->makeStaff('all-jobs@desk.test');

        $this->assertSame(StaffDeskJobs::ALL_JOBS, StaffDeskJobs::jobsFor($staff));
        $this->assertTrue(StaffDeskJobs::canCollectFee($staff));
        $this->assertTrue(StaffDeskJobs::canRunQueue($staff));
        $this->assertTrue(StaffDeskJobs::canRecordPrep($staff));

        tenancy()->end();
    }

    public function test_money_only_staff_cannot_run_queue_or_offline_snapshot(): void
    {
        tenancy()->initialize($this->tenant);

        $money = $this->makeStaff('money@desk.test', [StaffDeskJobs::JOB_MONEY]);

        $this->assertTrue(StaffDeskJobs::canCollectFee($money));
        $this->assertFalse(StaffDeskJobs::canRunQueue($money));
        $this->assertFalse(StaffDeskJobs::canRecordPrep($money));
        $this->assertFalse($money->canAccessLiveQueueControl());

        $this->actingAs($money);
        $this->assertFalse(LiveQueueControl::canAccess());

        $this->actingAs($money)
            ->getJson($this->host.'/api/offline/queue/'.$this->session->id)
            ->assertForbidden();

        tenancy()->end();
    }

    public function test_queue_only_staff_cannot_use_cashbook(): void
    {
        tenancy()->initialize($this->tenant);

        $queue = $this->makeStaff('queue@desk.test', [StaffDeskJobs::JOB_QUEUE]);

        $this->assertFalse(StaffDeskJobs::canCollectFee($queue));
        $this->assertTrue(StaffDeskJobs::canRunQueue($queue));

        $this->actingAs($queue);
        $this->assertFalse(Cashbook::canAccess());
        $this->assertTrue(LiveQueueControl::canAccess());

        tenancy()->end();
    }

    public function test_prep_only_staff_can_save_outdoor_vitals_but_money_only_cannot(): void
    {
        tenancy()->initialize($this->tenant);

        $prep = $this->makeStaff('prep@desk.test', [StaffDeskJobs::JOB_PREP]);
        $money = $this->makeStaff('money2@desk.test', [StaffDeskJobs::JOB_MONEY]);

        $patient = Patient::create(['name' => 'Rahim', 'phone' => '01710000099']);
        $booking = Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $this->session->id,
            'booking_date' => today()->toDateString(),
            'patient_id' => $patient->id,
            'patient_name' => $patient->name,
            'patient_phone' => $patient->phone,
            'serial_number' => 1,
            'status' => 'waiting',
        ]);

        $service = app(VisitRecordService::class);

        $record = $service->saveStaffVitals($booking, $prep, [
            'weight_kg' => 70,
            'bp_systolic' => 120,
            'bp_diastolic' => 80,
        ]);
        $this->assertNotNull($record);
        $this->assertSame(70.0, (float) $record->weight_kg);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $service->saveStaffVitals($booking, $money, ['weight_kg' => 71]);

        tenancy()->end();
    }

    public function test_lead_desk_may_manage_staff_but_not_owners_or_helpers(): void
    {
        tenancy()->initialize($this->tenant);

        $lead = $this->makeStaff('lead@desk.test', null, true);
        $desk = $this->makeStaff('desk@desk.test');
        $owner = User::create([
            'name' => 'Owner',
            'email' => 'owner@desk.test',
            'password' => Hash::make('secret'),
            'role' => User::ROLE_OWNER,
            'tenant_id' => 'desk-jobs-clinic',
        ]);
        $helper = User::create([
            'name' => 'Helper',
            'email' => 'helper@desk.test',
            'password' => Hash::make('secret'),
            'role' => User::ROLE_HELPER,
            'tenant_id' => 'desk-jobs-clinic',
        ]);

        $this->assertTrue($lead->canManageDeskStaff());
        $this->assertTrue(StaffDeskScope::leadMayManageStaff($lead, $desk));
        $this->assertFalse(StaffDeskScope::leadMayManageStaff($lead, $owner));
        $this->assertFalse(StaffDeskScope::leadMayManageStaff($lead, $helper));

        $this->actingAs($lead);
        $this->assertTrue(UserResource::canCreate());

        tenancy()->end();
    }

    public function test_owner_staff_list_hides_helpers(): void
    {
        tenancy()->initialize($this->tenant);

        $owner = User::create([
            'name' => 'Owner',
            'email' => 'owner2@desk.test',
            'password' => Hash::make('secret'),
            'role' => User::ROLE_OWNER,
            'tenant_id' => 'desk-jobs-clinic',
        ]);
        User::create([
            'name' => 'Helper',
            'email' => 'helper2@desk.test',
            'password' => Hash::make('secret'),
            'role' => User::ROLE_HELPER,
            'tenant_id' => 'desk-jobs-clinic',
        ]);
        $this->makeStaff('visible@desk.test');

        $this->actingAs($owner);
        $this->assertFalse(ChamberQHelperAccess::actorSeesHelpersOnStaffList());

        $roles = UserResource::getEloquentQuery()->pluck('role')->all();
        $this->assertNotContains(User::ROLE_HELPER, $roles);
        $this->assertContains(User::ROLE_STAFF, $roles);

        tenancy()->end();
    }

    public function test_branch_locked_lead_cannot_hire_for_other_branch(): void
    {
        tenancy()->initialize($this->tenant);

        $panchlaish = Chamber::create(['name' => 'Panchlaish']);
        $uttara = Chamber::create(['name' => 'Uttara']);

        $lead = $this->makeStaff('branch-lead@desk.test', null, true);
        StaffDeskScope::syncChambers($lead, [$panchlaish->id]);

        $constrained = StaffDeskScope::constrainChamberIdsForLeadHire($lead, [$panchlaish->id, $uttara->id]);
        $this->assertSame([$panchlaish->id], $constrained);

        try {
            StaffDeskScope::constrainChamberIdsForLeadHire($lead, [$uttara->id]);
            $this->fail('Expected validation when lead picks an out-of-scope branch.');
        } catch (\Illuminate\Validation\ValidationException) {
            // expected
        }

        $deskAtUttara = $this->makeStaff('uttara-desk@desk.test');
        StaffDeskScope::syncChambers($deskAtUttara, [$uttara->id]);
        $this->assertFalse(StaffDeskScope::leadMayManageStaff($lead, $deskAtUttara));

        $deskHospitalWide = $this->makeStaff('hospital-wide@desk.test');
        $this->assertFalse(StaffDeskScope::leadMayManageStaff($lead, $deskHospitalWide));

        $deskAtPanch = $this->makeStaff('panch-desk@desk.test');
        StaffDeskScope::syncChambers($deskAtPanch, [$panchlaish->id]);
        $this->assertTrue(StaffDeskScope::leadMayManageStaff($lead, $deskAtPanch));

        tenancy()->end();
    }

    public function test_a_single_desk_job_picks_that_page_as_login_home(): void
    {
        tenancy()->initialize($this->tenant);

        $this->assertSame(
            'pages.live-queue-control',
            StaffDeskJobs::loginPageRelativeName($this->makeStaff('q@desk.test', [StaffDeskJobs::JOB_QUEUE])),
        );
        $this->assertSame(
            'pages.cashbook',
            StaffDeskJobs::loginPageRelativeName($this->makeStaff('m@desk.test', [StaffDeskJobs::JOB_MONEY])),
        );
        $this->assertSame(
            'pages.daily-roster',
            StaffDeskJobs::loginPageRelativeName($this->makeStaff('p@desk.test', [StaffDeskJobs::JOB_PREP])),
        );
        $this->assertNull(StaffDeskJobs::loginPageRelativeName($this->makeStaff('all@desk.test')));
        $this->assertNull(StaffDeskJobs::loginPageRelativeName($this->makeStaff('lead@desk.test', lead: true)));
        $this->assertNull(StaffDeskJobs::loginPageRelativeName(
            $this->makeStaff('two@desk.test', [StaffDeskJobs::JOB_MONEY, StaffDeskJobs::JOB_QUEUE]),
        ));

        tenancy()->end();
    }
}
