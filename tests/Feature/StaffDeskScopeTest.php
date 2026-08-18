<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Chamber;
use App\Models\Doctor;
use App\Models\Domain;
use App\Models\ScheduleSession;
use App\Models\Tenant;
use App\Models\User;
use App\Support\StaffDeskScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StaffDeskScopeTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['id' => 'scope-clinic', 'plan_tier' => 'clinic']);
        Domain::create(['domain' => 'scope.localhost', 'tenant_id' => 'scope-clinic']);
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    private function makeUser(string $role, string $email): User
    {
        return User::create([
            'name' => $email,
            'email' => $email,
            'password' => Hash::make('secret'),
            'role' => $role,
            'tenant_id' => 'scope-clinic',
        ]);
    }

    public function test_owner_and_helper_see_all_chambers(): void
    {
        tenancy()->initialize($this->tenant);

        $owner = $this->makeUser(User::ROLE_OWNER, 'owner@scope.test');
        $helper = $this->makeUser(User::ROLE_HELPER, 'helper@scope.test');

        $this->assertTrue(StaffDeskScope::seesAllChambers($owner));
        $this->assertTrue(StaffDeskScope::seesAllChambers($helper));
        $this->assertNull(StaffDeskScope::chamberIdsFor($owner));
    }

    public function test_branch_locked_staff_only_sees_their_chamber_sessions(): void
    {
        tenancy()->initialize($this->tenant);

        $panchlaish = Chamber::create(['name' => 'Panchlaish']);
        $uttara = Chamber::create(['name' => 'Uttara']);
        $doctor = Doctor::create(['name' => 'Dr. Scope']);

        ScheduleSession::create([
            'chamber_id' => $panchlaish->id,
            'doctor_id' => $doctor->id,
            'day_of_week' => now()->dayOfWeek,
            'session_name' => 'Morning',
            'start_time' => '09:00',
            'end_time' => '12:00',
            'slot_cap' => 20,
        ]);

        ScheduleSession::create([
            'chamber_id' => $uttara->id,
            'doctor_id' => $doctor->id,
            'day_of_week' => now()->dayOfWeek,
            'session_name' => 'Evening',
            'start_time' => '17:00',
            'end_time' => '20:00',
            'slot_cap' => 20,
        ]);

        $staff = $this->makeUser(User::ROLE_STAFF, 'staff@scope.test');
        StaffDeskScope::syncChambers($staff, [$panchlaish->id]);

        $visible = ScheduleSession::query()->tap(
            fn ($q) => StaffDeskScope::constrainScheduleSessions($q, $staff)
        )->pluck('chamber_id')->all();

        $this->assertSame([$panchlaish->id], $visible);
    }

    public function test_doctor_assistant_staff_only_sees_that_doctors_bookings(): void
    {
        tenancy()->initialize($this->tenant);

        $chamber = Chamber::create(['name' => 'Main']);
        $drA = Doctor::create(['name' => 'Dr. A']);
        $drB = Doctor::create(['name' => 'Dr. B']);

        $sessionA = ScheduleSession::create([
            'chamber_id' => $chamber->id,
            'doctor_id' => $drA->id,
            'day_of_week' => now()->dayOfWeek,
            'session_name' => 'A',
            'start_time' => '09:00',
            'end_time' => '12:00',
            'slot_cap' => 10,
        ]);

        $sessionB = ScheduleSession::create([
            'chamber_id' => $chamber->id,
            'doctor_id' => $drB->id,
            'day_of_week' => now()->dayOfWeek,
            'session_name' => 'B',
            'start_time' => '14:00',
            'end_time' => '17:00',
            'slot_cap' => 10,
        ]);

        $bookingA = Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $sessionA->id,
            'booking_date' => today()->toDateString(),
            'serial_number' => 1,
            'patient_name' => 'Patient A',
            'patient_phone' => '01700000001',
            'status' => 'waiting',
        ]);

        Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $sessionB->id,
            'booking_date' => today()->toDateString(),
            'serial_number' => 1,
            'patient_name' => 'Patient B',
            'patient_phone' => '01700000002',
            'status' => 'waiting',
        ]);

        $assistant = $this->makeUser(User::ROLE_STAFF, 'assistant@scope.test');
        $assistant->update(['assigned_doctor_id' => $drA->id]);

        $ids = Booking::query()
            ->where('booking_date', today()->toDateString())
            ->tap(fn ($q) => StaffDeskScope::constrainBookings($q, $assistant))
            ->pluck('id')
            ->all();

        $this->assertSame([$bookingA->id], $ids);
    }

    public function test_offline_queue_rejects_out_of_scope_session(): void
    {
        tenancy()->initialize($this->tenant);

        $panchlaish = Chamber::create(['name' => 'Panchlaish']);
        $uttara = Chamber::create(['name' => 'Uttara']);
        $doctor = Doctor::create(['name' => 'Dr. Scope', 'user_id' => null]);

        $session = ScheduleSession::create([
            'chamber_id' => $uttara->id,
            'doctor_id' => $doctor->id,
            'day_of_week' => now()->dayOfWeek,
            'session_name' => 'Evening',
            'start_time' => '17:00',
            'end_time' => '20:00',
            'slot_cap' => 20,
        ]);

        $staff = $this->makeUser(User::ROLE_STAFF, 'desk@scope.test');
        StaffDeskScope::syncChambers($staff, [$panchlaish->id]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException::class);
        StaffDeskScope::assertCanAccessSession($staff, $session);
    }
}
