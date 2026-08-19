<?php

namespace Tests\Feature;

use App\Filament\TenantAdmin\Pages\PharmacyCounter;
use App\Models\Booking;
use App\Models\Chamber;
use App\Models\ChamberCashEntry;
use App\Models\Doctor;
use App\Models\Domain;
use App\Models\Patient;
use App\Models\PharmacyCount;
use App\Models\PharmacyDoctorCommission;
use App\Models\PharmacyItem;
use App\Models\PharmacySale;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\ScheduleSession;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VisitRecord;
use App\Services\PharmacySaleService;
use App\Services\PharmacyStockService;
use App\Services\PharmacySupplierService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use Tests\TestCase;

class PharmacyModuleTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $staff;

    private User $doctorUser;

    private Doctor $doctor;

    private Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-08-20 11:00', 'Asia/Dhaka'));

        $this->tenant = Tenant::create([
            'id' => 'pharmacy-shop',
            'plan_tier' => 'clinic',
            'feature_flags' => Tenant::mergeOptInModuleFlag([], Tenant::MODULE_PHARMACY, true),
            'pharmacy_doctor_percent' => 10,
        ]);
        Domain::create(['domain' => 'pharmacy-shop.localhost', 'tenant_id' => 'pharmacy-shop']);
        tenancy()->initialize($this->tenant);

        $this->staff = User::create([
            'name' => 'Desk',
            'email' => 'desk@pharmacy-shop.test',
            'password' => Hash::make('secret'),
            'role' => User::ROLE_STAFF,
            'tenant_id' => 'pharmacy-shop',
        ]);
        $this->doctorUser = User::create([
            'name' => 'Dr Pain',
            'email' => 'doc@pharmacy-shop.test',
            'password' => Hash::make('secret'),
            'role' => User::ROLE_DOCTOR,
            'tenant_id' => 'pharmacy-shop',
        ]);
        $this->doctor = Doctor::create([
            'name' => 'Dr Pain',
            'user_id' => $this->doctorUser->id,
            'default_fee_taka' => 1000,
        ]);
        Chamber::create(['name' => 'Main']);
        $this->patient = Patient::create([
            'name' => 'Amina',
            'phone' => '01710000001',
            'age' => 40,
            'age_recorded_at' => today(),
            'sex' => 'female',
        ]);
    }

    protected function tearDown(): void
    {
        tenancy()->end();
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_opt_in_pharmacy_defaults_off(): void
    {
        $plain = Tenant::create(['id' => 'no-pharmacy', 'plan_tier' => 'solo']);

        $this->assertFalse($plain->hasPharmacy());
        $this->assertTrue($this->tenant->hasPharmacy());
    }

    public function test_counter_is_hidden_when_module_is_off(): void
    {
        $this->tenant->feature_flags = Tenant::mergeOptInModuleFlag(
            $this->tenant->feature_flags ?? [],
            Tenant::MODULE_PHARMACY,
            false,
        );
        $this->tenant->save();
        tenancy()->end();
        tenancy()->initialize($this->tenant->fresh());

        $this->actingAs($this->staff);
        $this->assertFalse(PharmacyCounter::canAccess());
    }

    public function test_sell_from_rx_posts_full_price_and_drops_stock(): void
    {
        $item = $this->napaOnShelf(100, paidNow: 0);
        $rx = $this->todaysPrescription();

        $sale = app(PharmacySaleService::class)->sell(
            $this->staff,
            [['pharmacy_item_id' => $item->id, 'qty' => 1, 'prescription_item_id' => $rx->items->first()->id]],
            ChamberCashEntry::METHOD_CASH,
            false,
            $rx,
        );

        $this->assertSame(650, $sale->amount);
        $this->assertSame(99, $item->fresh()->qty_on_hand);
        $this->assertDatabaseHas('chamber_cash_entries', [
            'category' => ChamberCashEntry::CATEGORY_PHARMACY,
            'amount' => 650,
            'direction' => ChamberCashEntry::DIRECTION_INCOME,
        ]);
        $this->assertSame(300, app(PharmacySupplierService::class)->shopBalance()['owed']);
        $commission = PharmacyDoctorCommission::query()->first();
        $this->assertNotNull($commission);
        $this->assertSame(35, $commission->amount_taka);
        $this->assertSame(PharmacyDoctorCommission::STATUS_PENDING, $commission->status);

        app(\App\Services\PharmacyDoctorCommissionService::class)->markPaid(
            PharmacyDoctorCommission::query()->get(),
            $this->staff,
            ChamberCashEntry::METHOD_CASH,
        );
        $this->assertSame(PharmacyDoctorCommission::STATUS_PAID, $commission->fresh()->status);
        $this->assertDatabaseHas('chamber_cash_entries', [
            'category' => ChamberCashEntry::CATEGORY_PHARMACY_DOCTOR_PAYOUT,
            'amount' => 35,
        ]);
    }

    public function test_walk_in_does_not_owe_the_prescribing_doctor(): void
    {
        $item = $this->napaOnShelf(10, paidNow: 0);

        app(PharmacySaleService::class)->sell(
            $this->staff,
            [['pharmacy_item_id' => $item->id, 'qty' => 1]],
            ChamberCashEntry::METHOD_CASH,
            false,
            null,
            'Walk-in',
        );

        $this->assertSame(0, PharmacyDoctorCommission::query()->count());
        $this->assertSame(300, app(PharmacySupplierService::class)->shopBalance()['owed']);
    }

    public function test_hybrid_deposit_then_sales_then_return_sets_owed(): void
    {
        $item = $this->napaOnShelf(100, paidNow: 10000);

        app(PharmacySaleService::class)->sell(
            $this->staff,
            [['pharmacy_item_id' => $item->id, 'qty' => 60]],
            ChamberCashEntry::METHOD_CASH,
        );

        $balance = app(PharmacySupplierService::class)->shopBalance();
        $this->assertSame(8000, $balance['owed']);
        $this->assertSame(0, $balance['refund_due']);

        app(PharmacyStockService::class)->returnUnsold($item, $this->staff, 40);
        $afterReturn = app(PharmacySupplierService::class)->shopBalance();
        $this->assertSame(8000, $afterReturn['owed']);
        $this->assertSame(0, $afterReturn['refund_due']);
        $this->assertSame(0, $item->fresh()->qty_on_hand);
    }

    public function test_overpaid_returnable_box_refunds_unsold(): void
    {
        $item = $this->napaOnShelf(100, paidNow: 20000);

        app(PharmacySaleService::class)->sell(
            $this->staff,
            [['pharmacy_item_id' => $item->id, 'qty' => 40]],
            ChamberCashEntry::METHOD_CASH,
        );
        app(PharmacyStockService::class)->returnUnsold($item, $this->staff, 60);

        $balance = app(PharmacySupplierService::class)->shopBalance();
        $this->assertSame(0, $balance['owed']);
        $this->assertSame(8000, $balance['refund_due']);
    }

    public function test_bought_outright_unsold_still_counts_toward_company(): void
    {
        $item = $this->napaOnShelf(10, paidNow: 0, returnable: false);

        app(PharmacySaleService::class)->sell(
            $this->staff,
            [['pharmacy_item_id' => $item->id, 'qty' => 1]],
            ChamberCashEntry::METHOD_CASH,
        );

        $this->assertSame(10 * 300, app(PharmacySupplierService::class)->shopBalance()['owed']);
    }

    public function test_cannot_sell_more_than_on_the_shelf(): void
    {
        $item = $this->napaOnShelf(1, paidNow: 0);

        $this->expectException(InvalidArgumentException::class);
        app(PharmacySaleService::class)->sell(
            $this->staff,
            [['pharmacy_item_id' => $item->id, 'qty' => 2]],
            ChamberCashEntry::METHOD_CASH,
        );
    }

    public function test_same_day_void_restores_stock_and_cancels_doctor_cut(): void
    {
        $item = $this->napaOnShelf(5, paidNow: 0);
        $rx = $this->todaysPrescription();
        $sale = app(PharmacySaleService::class)->sell(
            $this->staff,
            [['pharmacy_item_id' => $item->id, 'qty' => 1]],
            ChamberCashEntry::METHOD_CASH,
            false,
            $rx,
        );

        app(PharmacySaleService::class)->void($sale, $this->staff);

        $this->assertSame(5, $item->fresh()->qty_on_hand);
        $this->assertNotNull($sale->fresh()->voided_at);
        $this->assertSame(
            PharmacyDoctorCommission::STATUS_VOID,
            PharmacyDoctorCommission::query()->first()?->status,
        );
        $this->assertDatabaseHas('chamber_cash_entries', [
            'category' => ChamberCashEntry::CATEGORY_PHARMACY_REFUND,
            'amount' => 650,
        ]);
        $this->assertSame(0, app(PharmacySupplierService::class)->shopBalance()['owed']);
    }

    public function test_pay_supplier_posts_purchase_expense(): void
    {
        $this->napaOnShelf(10, paidNow: 0);
        app(PharmacySaleService::class)->sell(
            $this->staff,
            [['pharmacy_item_id' => PharmacyItem::query()->first()->id, 'qty' => 2]],
            ChamberCashEntry::METHOD_CASH,
        );

        app(PharmacySupplierService::class)->pay($this->staff, 600, ChamberCashEntry::METHOD_BKASH);

        $this->assertSame(0, app(PharmacySupplierService::class)->shopBalance()['owed']);
        $this->assertDatabaseHas('chamber_cash_entries', [
            'category' => ChamberCashEntry::CATEGORY_PHARMACY_PURCHASE,
            'amount' => 600,
        ]);
    }

    public function test_physical_count_records_difference(): void
    {
        $item = $this->napaOnShelf(49, paidNow: 0);
        $count = app(PharmacyStockService::class)->startCount($this->staff);
        app(PharmacyStockService::class)->saveCount($count, $this->staff, [$item->id => 47]);

        $this->assertSame(47, $item->fresh()->qty_on_hand);
        $this->assertSame(PharmacyCount::STATUS_SAVED, $count->fresh()->status);
        $this->assertSame(-2, $count->items()->first()?->difference);
    }

    public function test_a_second_in_progress_count_is_blocked(): void
    {
        $this->napaOnShelf(5, paidNow: 0);
        app(PharmacyStockService::class)->startCount($this->staff);

        $this->expectException(InvalidArgumentException::class);
        app(PharmacyStockService::class)->startCount($this->staff);
    }

    public function test_module_off_refuses_a_sale(): void
    {
        $item = $this->napaOnShelf(5, paidNow: 0);
        $this->tenant->feature_flags = Tenant::mergeOptInModuleFlag(
            $this->tenant->feature_flags ?? [],
            Tenant::MODULE_PHARMACY,
            false,
        );
        $this->tenant->save();
        tenancy()->end();
        tenancy()->initialize($this->tenant->fresh());

        $this->expectException(InvalidArgumentException::class);
        app(PharmacySaleService::class)->sell(
            $this->staff,
            [['pharmacy_item_id' => $item->id, 'qty' => 1]],
            ChamberCashEntry::METHOD_CASH,
        );
    }

    public function test_mixed_cash_and_online_must_add_up(): void
    {
        $item = $this->napaOnShelf(5, paidNow: 0);

        $this->expectException(InvalidArgumentException::class);
        app(PharmacySaleService::class)->sell(
            $this->staff,
            [['pharmacy_item_id' => $item->id, 'qty' => 1]],
            ChamberCashEntry::METHOD_MIXED,
            false,
            null,
            null,
            null,
            null,
            100,
            100,
            ChamberCashEntry::METHOD_BKASH,
        );
    }

    private function napaOnShelf(int $qty, int $paidNow, bool $returnable = true): PharmacyItem
    {
        $item = PharmacyItem::create([
            'name' => 'NAPA 500',
            'sell_price_taka' => 650,
            'company_share_taka' => 300,
            'unit_label' => PharmacyItem::UNIT_STRIP,
            'qty_on_hand' => 0,
            'is_active' => true,
        ]);
        app(PharmacyStockService::class)->receive(
            $item,
            $this->staff,
            $qty,
            $paidNow,
            $returnable,
        );

        return $item->fresh();
    }

    private function todaysPrescription(): Prescription
    {
        $chamber = Chamber::query()->first();
        $session = ScheduleSession::create([
            'chamber_id' => $chamber->id,
            'doctor_id' => $this->doctor->id,
            'day_of_week' => Carbon::today()->dayOfWeek,
            'session_name' => 'Morning',
            'start_time' => '09:00',
            'end_time' => '12:00',
            'slot_cap' => 20,
        ]);
        $booking = Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $session->id,
            'booking_date' => today(),
            'patient_id' => $this->patient->id,
            'patient_name' => $this->patient->name,
            'patient_phone' => $this->patient->phone,
            'serial_number' => 1,
            'status' => 'completed',
        ]);
        $visit = VisitRecord::create([
            'booking_id' => $booking->id,
            'patient_id' => $this->patient->id,
            'recorded_by' => $this->doctorUser->id,
            'recorded_at' => now(),
        ]);
        $rx = Prescription::create([
            'visit_record_id' => $visit->id,
            'patient_id' => $this->patient->id,
            'prescribed_by' => $this->doctorUser->id,
        ]);
        PrescriptionItem::create([
            'prescription_id' => $rx->id,
            'medicine_name' => 'NAPA 500',
            'frequency' => '1+0+1',
            'duration' => '5 days',
            'sort_order' => 1,
        ]);

        return $rx->fresh('items');
    }
}
