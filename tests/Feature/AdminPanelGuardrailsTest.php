<?php

namespace Tests\Feature;

use App\Filament\SuperAdmin\Resources\Marketers\Pages\CreateMarketer;
use App\Filament\SuperAdmin\Resources\Tenants\Pages\CreateTenant;
use App\Filament\SuperAdmin\Resources\Tenants\Pages\EditTenant;
use App\Filament\TenantAdmin\Resources\Chambers\ChamberResource;
use App\Filament\TenantAdmin\Resources\Doctors\DoctorResource;
use App\Filament\TenantAdmin\Resources\Doctors\Pages\CreateDoctor;
use App\Filament\TenantAdmin\Resources\SlotBlocks\Pages\CreateSlotBlock;
use App\Filament\TenantAdmin\Resources\Users\Pages\CreateUser;
use App\Models\Booking;
use App\Models\Chamber;
use App\Models\Commission;
use App\Models\Doctor;
use App\Models\Marketer;
use App\Models\ScheduleSession;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BookingService;
use App\Services\CommissionService;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Guardrails the admin panels are supposed to hold, each one caught by an audit
 * where the panel quietly disagreed with the service or policy behind it.
 */
class AdminPanelGuardrailsTest extends TestCase
{
    use RefreshDatabase;

    private function tenantAdmin(Tenant $tenant, string $email = 'admin@chamber.test'): User
    {
        $user = User::create([
            'name' => 'Admin',
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => User::ROLE_ADMIN,
            'tenant_id' => $tenant->id,
        ]);

        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('tenantAdmin'));

        return $user;
    }

    private function superAdmin(): User
    {
        $user = User::create([
            'name' => 'Platform',
            'email' => 'super@platform.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_SUPER_ADMIN,
            'tenant_id' => null,
        ]);

        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('superAdmin'));

        return $user;
    }

    // ---------------------------------------------------------------- blocks

    public function test_blocking_a_date_from_the_admin_page_leaves_completed_visits_alone(): void
    {
        $tenant = Tenant::create(['id' => 'clinic', 'plan_tier' => 'clinic']);
        tenancy()->initialize($tenant);

        $chamber = Chamber::create(['name' => 'Main']);
        $doctor = Doctor::create(['name' => 'Dr. A']);
        $session = ScheduleSession::create([
            'chamber_id' => $chamber->id, 'doctor_id' => $doctor->id,
            'day_of_week' => 1, 'session_name' => 'Morning',
            'start_time' => '09:00', 'end_time' => '12:00', 'slot_cap' => 10,
        ]);

        $monday = Carbon::now()->next(1)->format('Y-m-d');
        $bookings = app(BookingService::class);

        $seen = $bookings->createBookingForBookable($session, $monday, 'Already seen', '01712345601');
        $seen->update(['status' => 'completed']);
        $waiting = $bookings->createBookingForBookable($session, $monday, 'Still waiting', '01712345602');

        $this->tenantAdmin($tenant);

        Livewire::test(CreateSlotBlock::class)
            ->fillForm([
                'date' => $monday,
                'chamber_id' => $chamber->id,
                'confirm_cancellation' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame('completed', Booking::find($seen->id)->status);
        $this->assertSame('cancelled', Booking::find($waiting->id)->status);

        tenancy()->end();
    }

    public function test_block_records_which_bookings_it_cancelled_for_notification(): void
    {
        $tenant = Tenant::create(['id' => 'clinic', 'plan_tier' => 'clinic']);
        tenancy()->initialize($tenant);

        $chamber = Chamber::create(['name' => 'Main']);
        $doctor = Doctor::create(['name' => 'Dr. A']);
        $session = ScheduleSession::create([
            'chamber_id' => $chamber->id, 'doctor_id' => $doctor->id,
            'day_of_week' => 1, 'session_name' => 'Morning',
            'start_time' => '09:00', 'end_time' => '12:00', 'slot_cap' => 10,
        ]);

        $monday = Carbon::now()->next(1)->format('Y-m-d');

        // A patient name is untrusted input; it must never be interpolated into
        // admin HTML. Storing one that looks like markup keeps that honest.
        app(BookingService::class)->createBookingForBookable(
            $session, $monday, '<script>alert(1)</script>', '01712345603'
        );

        $this->tenantAdmin($tenant);

        Livewire::test(CreateSlotBlock::class)
            ->fillForm([
                'date' => $monday,
                'chamber_id' => $chamber->id,
                'confirm_cancellation' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $block = \App\Models\SlotBlock::firstOrFail();

        $this->assertSame(1, $block->cancelledBookings()->count());

        tenancy()->end();
    }

    // ----------------------------------------------------------- commissions

    public function test_attaching_a_marketer_while_changing_the_plan_still_creates_the_setup_commission(): void
    {
        $marketerUser = User::create([
            'name' => 'Joy Partner',
            'email' => 'joy@partner.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_MARKETER,
            'tenant_id' => null,
        ]);

        $marketer = Marketer::create([
            'user_id' => $marketerUser->id,
            'code' => 'joy20',
            'display_name' => 'Joy',
            'setup_commission_rate' => 0.20,
            'monthly_commission_rate' => 0.10,
            'is_active' => true,
        ]);

        $tenant = Tenant::create(['id' => 'drkarim', 'plan_tier' => 'solo']);
        app(CommissionService::class)->applyPricingToTenant($tenant, null);
        $tenant->save();

        $this->superAdmin();

        // Both changes in one save — the pricing re-save used to clear
        // `wasChanged('marketer_id')`, so no commission row was ever created.
        Livewire::test(EditTenant::class, ['record' => $tenant->getKey()])
            ->fillForm([
                'plan_tier' => 'clinic',
                'marketer_id' => $marketer->id,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $commission = Commission::where('tenant_id', $tenant->id)
            ->where('type', Commission::TYPE_SETUP)
            ->first();

        $this->assertNotNull($commission, 'Setup commission was not created for the newly attached marketer.');
        $this->assertSame(Commission::STATUS_PENDING, $commission->status);
        $this->assertSame((int) $tenant->fresh()->setup_amount_due, (int) $commission->base_amount);
    }

    // --------------------------------------------------------- bulk deletion

    public function test_bulk_delete_cannot_bypass_the_last_chamber_and_only_doctor_rules(): void
    {
        $tenant = Tenant::create(['id' => 'solo1', 'plan_tier' => 'solo']);
        tenancy()->initialize($tenant);

        Chamber::create(['name' => 'Only chamber']);
        Doctor::create(['name' => 'Only doctor']);

        $this->tenantAdmin($tenant);

        // Filament authorizes bulk deletes with `deleteAny()`, not `delete()`.
        // Without an explicit deny these returned true and wiped the lot.
        $this->assertFalse(ChamberResource::canDeleteAny());
        $this->assertFalse(DoctorResource::canDeleteAny());

        tenancy()->end();
    }

    // ------------------------------------------------------------ uniqueness

    public function test_a_doctor_can_be_paired_with_a_login_account(): void
    {
        $tenant = Tenant::create(['id' => 'clinic', 'plan_tier' => 'clinic']);
        tenancy()->initialize($tenant);

        $login = User::create([
            'name' => 'Dr Dentist',
            'email' => 'dentist@chamber.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_DOCTOR,
            'tenant_id' => $tenant->id,
        ]);

        $this->tenantAdmin($tenant);

        Livewire::test(CreateDoctor::class)
            ->fillForm([
                'name' => 'Dr Dentist',
                'user_id' => $login->id,
                'practice_type' => Doctor::PRACTICE_DENTIST,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame($login->id, Doctor::query()->where('name', 'Dr Dentist')->value('user_id'));

        tenancy()->end();
    }

    public function test_pairing_one_login_with_two_doctors_is_a_validation_error(): void
    {
        $tenant = Tenant::create(['id' => 'clinic', 'plan_tier' => 'clinic']);
        tenancy()->initialize($tenant);

        $login = User::create([
            'name' => 'Dr Taken',
            'email' => 'taken@chamber.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_DOCTOR,
            'tenant_id' => $tenant->id,
        ]);

        Doctor::create(['name' => 'Dr Taken', 'user_id' => $login->id]);

        $this->tenantAdmin($tenant);

        // Without the rule this hits the unique index as a 500, and a mis-paired
        // profile would put the wrong doctor's name on a prescription.
        Livewire::test(CreateDoctor::class)
            ->fillForm([
                'name' => 'Dr Impostor',
                'user_id' => $login->id,
                'practice_type' => Doctor::PRACTICE_GENERAL,
            ])
            ->call('create')
            ->assertHasFormErrors(['user_id']);

        $this->assertSame(1, Doctor::query()->count());

        tenancy()->end();
    }

    public function test_duplicate_staff_email_is_a_validation_error_not_a_database_crash(): void
    {
        $tenant = Tenant::create(['id' => 'clinic', 'plan_tier' => 'clinic']);
        tenancy()->initialize($tenant);

        User::create([
            'name' => 'Front desk',
            'email' => 'desk@chamber.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_STAFF,
            'tenant_id' => $tenant->id,
        ]);

        $this->tenantAdmin($tenant);

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Second desk',
                'email' => 'desk@chamber.test',
                'role' => User::ROLE_STAFF,
                'password' => 'password',
            ])
            ->call('create')
            ->assertHasFormErrors(['email']);

        $this->assertSame(2, User::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());

        tenancy()->end();
    }

    public function test_a_staff_email_used_by_another_tenant_is_still_allowed(): void
    {
        $other = Tenant::create(['id' => 'other', 'plan_tier' => 'solo']);
        User::create([
            'name' => 'Their desk',
            'email' => 'desk@chamber.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_STAFF,
            'tenant_id' => $other->id,
        ]);

        $tenant = Tenant::create(['id' => 'clinic', 'plan_tier' => 'clinic']);
        tenancy()->initialize($tenant);
        $this->tenantAdmin($tenant);

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Our desk',
                'email' => 'desk@chamber.test',
                'role' => User::ROLE_STAFF,
                'password' => 'password',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(
            2,
            User::withoutGlobalScopes()->where('email', 'desk@chamber.test')->count()
        );

        tenancy()->end();
    }

    public function test_two_partner_accounts_cannot_share_a_login_email(): void
    {
        $existing = User::create([
            'name' => 'Joy Partner',
            'email' => 'joy@partner.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_MARKETER,
            'tenant_id' => null,
        ]);

        Marketer::create([
            'user_id' => $existing->id,
            'code' => 'joy20',
            'display_name' => 'Joy',
            'is_active' => true,
        ]);

        $this->superAdmin();

        Livewire::test(CreateMarketer::class)
            ->fillForm([
                'user_name' => 'Impostor',
                'user_email' => 'joy@partner.test',
                'user_password' => 'password',
                'display_name' => 'Impostor',
                'code' => 'imp01',
                'setup_commission_rate' => 0.2,
                'monthly_commission_rate' => 0.1,
            ])
            ->call('create')
            ->assertHasFormErrors(['user_email']);

        $this->assertSame(1, User::withoutGlobalScopes()->where('email', 'joy@partner.test')->count());
    }

    public function test_tenant_slug_must_be_unique_and_not_a_reserved_path(): void
    {
        Tenant::create(['id' => 'drkarim', 'plan_tier' => 'solo']);

        $this->superAdmin();

        Livewire::test(CreateTenant::class)
            ->fillForm([
                'id' => 'drkarim',
                'name' => 'Copycat',
                'plan_tier' => 'solo',
                'billing_status' => 'trial',
                'sms_balance' => 0,
                'initial_doctor_email' => 'doctor@copycat.test',
            ])
            ->call('create')
            ->assertHasFormErrors(['id']);

        Livewire::test(CreateTenant::class)
            ->fillForm([
                'id' => 'admin',
                'name' => 'Unreachable',
                'plan_tier' => 'solo',
                'billing_status' => 'trial',
                'sms_balance' => 0,
                'initial_doctor_email' => 'doctor@unreachable.test',
            ])
            ->call('create')
            ->assertHasFormErrors(['id']);

        $this->assertSame(1, Tenant::count());
    }
}
