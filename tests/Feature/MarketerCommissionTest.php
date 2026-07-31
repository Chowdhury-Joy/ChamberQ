<?php

namespace Tests\Feature;

use App\Models\Commission;
use App\Models\DiscountCode;
use App\Models\Marketer;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CommissionService;
use App\Services\DiscountCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MarketerCommissionTest extends TestCase
{
    use RefreshDatabase;

    private Marketer $marketer;

    private User $marketerUser;

    private User $otherMarketerUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->marketerUser = User::create([
            'name' => 'Joy Partner',
            'email' => 'joy@partner.com',
            'password' => Hash::make('password'),
            'role' => User::ROLE_MARKETER,
            'tenant_id' => null,
        ]);

        $this->marketer = Marketer::create([
            'user_id' => $this->marketerUser->id,
            'code' => 'joy20',
            'display_name' => 'Joy',
            'setup_commission_rate' => 0.20,
            'monthly_commission_rate' => 0.10,
            'is_active' => true,
        ]);

        $otherUser = User::create([
            'name' => 'Other Partner',
            'email' => 'other@partner.com',
            'password' => Hash::make('password'),
            'role' => User::ROLE_MARKETER,
            'tenant_id' => null,
        ]);

        Marketer::create([
            'user_id' => $otherUser->id,
            'code' => 'other99',
            'display_name' => 'Other',
            'is_active' => true,
        ]);

        $this->otherMarketerUser = $otherUser;
    }

    public function test_discount_calculator_applies_setup_percent_only(): void
    {
        $code = DiscountCode::create([
            'code' => 'SETUP20',
            'setup_percent' => 20,
            'is_active' => true,
        ]);

        $amounts = app(DiscountCalculator::class)->calculate(5000, 2000, $code);

        $this->assertSame(5000, $amounts['list_setup']);
        $this->assertSame(4000, $amounts['setup_due']);
        $this->assertSame(1000, $amounts['setup_discount']);
        $this->assertSame(2000, $amounts['monthly_due']);
    }

    public function test_setup_commission_is_twenty_percent_of_discounted_amount(): void
    {
        $code = DiscountCode::create([
            'code' => 'SETUP20',
            'setup_percent' => 20,
            'is_active' => true,
        ]);

        $tenant = Tenant::create([
            'id' => 'drkarim',
            'plan_tier' => 'solo',
            'marketer_id' => $this->marketer->id,
            'discount_code_id' => $code->id,
        ]);

        $service = app(CommissionService::class);
        $service->applyPricingToTenant($tenant, $code);
        $tenant->save();
        $service->createPendingSetupCommission($tenant);

        $commission = Commission::where('tenant_id', $tenant->id)->first();
        $this->assertNotNull($commission);
        $this->assertSame(4000, $commission->base_amount);
        $this->assertSame(800, $commission->commission_amount);
        $this->assertSame(Commission::STATUS_PENDING, $commission->status);
    }

    public function test_confirm_setup_payment_moves_commission_to_owed(): void
    {
        $tenant = $this->createReferredTenant();
        $superAdmin = User::create([
            'name' => 'Super',
            'email' => 'super@test.com',
            'password' => Hash::make('password'),
            'role' => User::ROLE_SUPER_ADMIN,
            'tenant_id' => null,
        ]);

        $service = app(CommissionService::class);
        $service->applyPricingToTenant($tenant);
        $tenant->save();
        $service->createPendingSetupCommission($tenant);

        $service->confirmSetupPayment($tenant, $superAdmin, null, 4000);

        $commission = Commission::where('tenant_id', $tenant->id)->first();
        $this->assertSame(Commission::STATUS_OWED, $commission->status);
        $this->assertSame(800, $commission->commission_amount);
        $this->assertNotNull($tenant->fresh()->setup_paid_at);
    }

    public function test_monthly_commission_flow_pending_to_owed_to_paid(): void
    {
        $tenant = $this->createReferredTenant(withSetupPaid: true);
        $superAdmin = User::create([
            'name' => 'Super',
            'email' => 'super2@test.com',
            'password' => Hash::make('password'),
            'role' => User::ROLE_SUPER_ADMIN,
            'tenant_id' => null,
        ]);

        $service = app(CommissionService::class);
        $service->applyPricingToTenant($tenant);
        $tenant->update(['setup_paid_at' => now(), 'billing_status' => 'active']);
        $tenant->save();

        $period = now()->format('Y-m');
        $service->generateMonthlyPendingCommissions($period);

        $commission = Commission::where('tenant_id', $tenant->id)
            ->where('type', Commission::TYPE_MONTHLY)
            ->first();

        $this->assertSame(Commission::STATUS_PENDING, $commission->status);
        $this->assertSame(200, $commission->commission_amount);

        $service->confirmMonthlyPayment($tenant, $period, $superAdmin);
        $commission->refresh();
        $this->assertSame(Commission::STATUS_OWED, $commission->status);

        $service->markCommissionPaid($commission, 'bkash123');
        $commission->refresh();
        $this->assertSame(Commission::STATUS_PAID, $commission->status);
        $this->assertSame('bkash123', $commission->payout_note);
    }

    public function test_monthly_generation_skips_tenant_without_setup_paid(): void
    {
        $tenant = $this->createReferredTenant();
        $service = app(CommissionService::class);
        $service->applyPricingToTenant($tenant);
        $tenant->update(['billing_status' => 'active']);
        $tenant->save();

        $count = $service->generateMonthlyPendingCommissions(now()->format('Y-m'));

        $this->assertSame(0, $count);
        $this->assertDatabaseMissing('commissions', [
            'tenant_id' => $tenant->id,
            'type' => Commission::TYPE_MONTHLY,
        ]);
    }

    public function test_reconfirm_setup_payment_does_not_unpay_commission(): void
    {
        $tenant = $this->createReferredTenant();
        $superAdmin = User::create([
            'name' => 'Super',
            'email' => 'super-reconfirm@test.com',
            'password' => Hash::make('password'),
            'role' => User::ROLE_SUPER_ADMIN,
            'tenant_id' => null,
        ]);

        $service = app(CommissionService::class);
        $service->applyPricingToTenant($tenant);
        $tenant->save();
        $service->createPendingSetupCommission($tenant);
        $service->confirmSetupPayment($tenant, $superAdmin, null, 4000);

        $commission = Commission::where('tenant_id', $tenant->id)->first();
        $service->markCommissionPaid($commission, 'paid-once');

        $service->confirmSetupPayment($tenant, $superAdmin, 'reconfirm', 4000);

        $commission->refresh();
        $this->assertSame(Commission::STATUS_PAID, $commission->status);
        $this->assertSame('paid-once', $commission->payout_note);
    }

    public function test_uppercase_referral_query_matches_legacy_code(): void
    {
        \Illuminate\Support\Facades\DB::table('marketers')
            ->where('id', $this->marketer->id)
            ->update(['code' => 'JOY20']);

        $this->get('http://localhost/?ref=JOY20')
            ->assertOk();

        $this->assertSame($this->marketer->id, session('referral.marketer_id'));
    }

    public function test_referral_query_param_captures_marketer_in_session(): void
    {
        $this->get('http://localhost/?ref=joy20')
            ->assertOk();

        $this->assertSame($this->marketer->id, session('referral.marketer_id'));
        $this->assertSame('joy20', session('referral.code'));
    }

    public function test_discount_query_param_captures_code_in_session(): void
    {
        DiscountCode::create([
            'code' => 'SAVE20',
            'setup_percent' => 20,
            'is_active' => true,
        ]);

        $this->get('http://localhost/?code=SAVE20')
            ->assertOk();

        $this->assertSame('SAVE20', session('referral.discount_code'));
    }

    public function test_whatsapp_link_includes_referral_context_after_visit(): void
    {
        config(['marketing.whatsapp' => '8801712345678']);

        DiscountCode::create([
            'code' => 'SAVE20',
            'setup_percent' => 20,
            'is_active' => true,
        ]);

        $this->withSession([
            'referral.marketer_id' => $this->marketer->id,
            'referral.code' => 'joy20',
            'referral.discount_code_id' => DiscountCode::where('code', 'SAVE20')->value('id'),
            'referral.discount_code' => 'SAVE20',
        ])
            ->get('http://localhost/')
            ->assertOk()
            ->assertSee('wa.me/8801712345678', escape: false)
            ->assertSee(rawurlencode('Ref: joy20'), escape: false)
            ->assertSee(rawurlencode('Code: SAVE20'), escape: false);
    }

    public function test_marketer_can_access_partner_panel_login(): void
    {
        $this->get('http://localhost/partner/login')
            ->assertOk();
    }

    public function test_marketer_cannot_access_super_admin_panel(): void
    {
        $this->actingAs($this->marketerUser);

        $this->assertFalse($this->marketerUser->canAccessPanel(
            filament()->getPanel('superAdmin')
        ));
    }

    public function test_marketer_commissions_are_scoped_to_own_marketer(): void
    {
        $tenantA = $this->createReferredTenant(id: 'tenanta');
        $tenantB = Tenant::create([
            'id' => 'tenantb',
            'plan_tier' => 'solo',
            'marketer_id' => Marketer::where('code', 'other99')->value('id'),
        ]);

        $service = app(CommissionService::class);
        foreach ([$tenantA, $tenantB] as $tenant) {
            $service->applyPricingToTenant($tenant);
            $tenant->save();
            $service->createPendingSetupCommission($tenant);
        }

        $ownCount = Commission::query()
            ->where('marketer_id', $this->marketer->id)
            ->count();

        $this->assertSame(1, $ownCount);
    }

    public function test_generate_monthly_command_is_idempotent(): void
    {
        $tenant = $this->createReferredTenant(withSetupPaid: true);
        $service = app(CommissionService::class);
        $service->applyPricingToTenant($tenant);
        $tenant->update(['setup_paid_at' => now(), 'billing_status' => 'active']);
        $tenant->save();

        $period = now()->format('Y-m');
        $first = $service->generateMonthlyPendingCommissions($period);
        $second = $service->generateMonthlyPendingCommissions($period);

        $this->assertSame(1, $first);
        $this->assertSame(0, $second);
        $this->assertSame(1, Commission::where('tenant_id', $tenant->id)->where('type', Commission::TYPE_MONTHLY)->count());
    }

    private function createReferredTenant(string $id = 'drkarim', bool $withSetupPaid = false): Tenant
    {
        $code = DiscountCode::create([
            'code' => 'SETUP20',
            'setup_percent' => 20,
            'is_active' => true,
        ]);

        return Tenant::create([
            'id' => $id,
            'plan_tier' => 'solo',
            'marketer_id' => $this->marketer->id,
            'discount_code_id' => $code->id,
            'setup_paid_at' => $withSetupPaid ? now() : null,
        ]);
    }
}
