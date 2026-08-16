<?php

namespace Tests\Feature;

use App\Filament\TenantAdmin\Pages\DailyRoster;
use App\Filament\TenantAdmin\Pages\FollowUpReminders;
use App\Filament\TenantAdmin\Pages\OperationalReports;
use App\Filament\TenantAdmin\Pages\WaitingForEarlierDate;
use App\Filament\TenantAdmin\Resources\Chambers\ChamberResource;
use App\Filament\TenantAdmin\Resources\ScheduleSessions\ScheduleSessionResource;
use App\Filament\TenantAdmin\Resources\SlotBlocks\SlotBlockResource;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Today's list, closed dates, the earlier-date waiting list, follow-up
 * WhatsApp, chambers, and sitting hours are front-desk work. They must not
 * sit on the doctor's consult menu when a staff login exists. Reports stay
 * with the account owner and the doctor — not staff.
 */
class DeskToolsBelongToStaffTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['id' => 'desk-tools', 'plan_tier' => 'solo']);
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    private function makeUser(string $role, string $email): User
    {
        return User::create([
            'name' => ucfirst($role),
            'email' => $email,
            'password' => Hash::make('secret'),
            'role' => $role,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_staff_own_the_four_desk_lists(): void
    {
        tenancy()->initialize($this->tenant);
        $staff = $this->makeUser(User::ROLE_STAFF, 'staff@desk.test');
        $this->actingAs($staff);

        $this->assertTrue(DailyRoster::canAccess());
        $this->assertTrue(SlotBlockResource::canViewAny());
        $this->assertTrue(WaitingForEarlierDate::canAccess());
        $this->assertTrue(FollowUpReminders::canAccess());
    }

    public function test_account_owner_can_open_the_day_list_not_follow_up_or_earlier_date(): void
    {
        tenancy()->initialize($this->tenant);
        $this->makeUser(User::ROLE_STAFF, 'staff@desk.test');
        $admin = $this->makeUser(User::ROLE_ADMIN, 'admin@desk.test');
        $this->actingAs($admin);

        $this->assertTrue(DailyRoster::canAccess());
        $this->assertTrue(SlotBlockResource::canViewAny());
        $this->assertFalse(WaitingForEarlierDate::canAccess());
        $this->assertFalse(FollowUpReminders::canAccess());
    }

    public function test_doctor_does_not_see_desk_lists_when_staff_exist(): void
    {
        tenancy()->initialize($this->tenant);
        $this->makeUser(User::ROLE_STAFF, 'staff@desk.test');
        $doctor = $this->makeUser(User::ROLE_DOCTOR, 'doc@desk.test');
        $this->actingAs($doctor);

        $this->assertFalse(DailyRoster::canAccess());
        $this->assertFalse(SlotBlockResource::canViewAny());
        $this->assertFalse(WaitingForEarlierDate::canAccess());
        $this->assertFalse(FollowUpReminders::canAccess());
    }

    public function test_solo_doctor_without_staff_still_reaches_the_desk_lists(): void
    {
        tenancy()->initialize($this->tenant);
        $doctor = $this->makeUser(User::ROLE_DOCTOR, 'solo-doc@desk.test');
        $this->actingAs($doctor);

        $this->assertTrue(DailyRoster::canAccess());
        $this->assertTrue(SlotBlockResource::canViewAny());
        $this->assertTrue(WaitingForEarlierDate::canAccess());
        $this->assertTrue(FollowUpReminders::canAccess());
    }

    public function test_staff_own_chambers_and_sitting_hours(): void
    {
        tenancy()->initialize($this->tenant);
        $staff = $this->makeUser(User::ROLE_STAFF, 'staff-hours@desk.test');
        $this->actingAs($staff);

        $this->assertTrue(ChamberResource::canViewAny());
        $this->assertTrue(ScheduleSessionResource::canViewAny());
    }

    public function test_doctor_does_not_see_chambers_or_hours_when_staff_exist(): void
    {
        tenancy()->initialize($this->tenant);
        $this->makeUser(User::ROLE_STAFF, 'staff-hours@desk.test');
        $doctor = $this->makeUser(User::ROLE_DOCTOR, 'doc-hours@desk.test');
        $this->actingAs($doctor);

        $this->assertFalse(ChamberResource::canViewAny());
        $this->assertFalse(ScheduleSessionResource::canViewAny());
    }

    public function test_solo_doctor_without_staff_still_reaches_chambers_and_hours(): void
    {
        tenancy()->initialize($this->tenant);
        $doctor = $this->makeUser(User::ROLE_DOCTOR, 'solo-hours@desk.test');
        $this->actingAs($doctor);

        $this->assertTrue(ChamberResource::canViewAny());
        $this->assertTrue(ScheduleSessionResource::canViewAny());
    }

    public function test_reports_are_open_to_admin_and_doctor_not_staff(): void
    {
        tenancy()->initialize($this->tenant);

        $this->actingAs($this->makeUser(User::ROLE_ADMIN, 'admin-reports@desk.test'));
        $this->assertTrue(OperationalReports::canAccess());

        $this->actingAs($this->makeUser(User::ROLE_DOCTOR, 'doc-reports@desk.test'));
        $this->assertTrue(OperationalReports::canAccess());

        $this->actingAs($this->makeUser(User::ROLE_STAFF, 'staff-reports@desk.test'));
        $this->assertFalse(OperationalReports::canAccess());
    }
}
