<?php

namespace Tests\Feature;

use App\Filament\TenantAdmin\Pages\PharmacyCounter;
use App\Filament\TenantAdmin\Pages\PharmacyPaySupplier;
use App\Filament\TenantAdmin\Pages\PharmacyPhysicalCount;
use App\Filament\TenantAdmin\Resources\PharmacyItems\Pages\ListPharmacyItems;
use App\Filament\TenantAdmin\Resources\PharmacyItems\PharmacyItemResource;
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
use App\Services\PharmacyDoctorCommissionService;
use App\Services\PharmacySaleService;
use App\Services\PharmacyStockService;
use App\Services\PharmacySupplierService;
use App\Support\PharmacyAccess;
use App\Support\StaffDeskJobs;
use App\Support\StaffDeskScope;
use App\Support\TakaWords;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use Livewire\Livewire;
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

        app(PharmacyDoctorCommissionService::class)->markPaid(
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

    public function test_walk_in_sale_does_not_need_a_phone_number(): void
    {
        $item = $this->napaOnShelf(5, paidNow: 0);

        Filament::setCurrentPanel('tenantAdmin');
        $this->actingAs($this->staff);

        Livewire::test(PharmacyCounter::class)
            ->mountAction('walkIn')
            ->assertFormFieldExists('patient_name')
            ->assertFormFieldDoesNotExist('patient_phone')
            ->fillForm(function (array $state) use ($item): array {
                $lines = $state['lines'] ?? [];
                $uuid = array_key_first($lines);
                if ($uuid === null) {
                    $lines = [['pharmacy_item_id' => $item->id, 'qty' => 1]];
                } else {
                    $lines[$uuid]['pharmacy_item_id'] = $item->id;
                    $lines[$uuid]['qty'] = 1;
                }

                return [
                    'patient_name' => 'Karim',
                    'lines' => $lines,
                    'method' => ChamberCashEntry::METHOD_CASH,
                ];
            })
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $sale = PharmacySale::query()->latest('id')->first();
        $this->assertNotNull($sale);
        $this->assertSame('Karim', $sale->patient_name);
        $this->assertNull($sale->patient_phone);
        $this->assertSame(650, $sale->amount);
    }

    public function test_counter_list_uses_shop_words(): void
    {
        $item = $this->napaOnShelf(5, paidNow: 0);
        $sale = app(PharmacySaleService::class)->sell(
            $this->staff,
            [['pharmacy_item_id' => $item->id, 'qty' => 1]],
            ChamberCashEntry::METHOD_CASH,
            false,
            null,
            'Karim',
        );

        Filament::setCurrentPanel('tenantAdmin');
        $this->actingAs($this->staff);

        Livewire::test(PharmacyCounter::class)
            ->assertSee('Amount')
            ->assertSee('Type of medicine')
            ->assertSee('Returned')
            ->assertSee('NAPA 500')
            ->assertDontSee('Taken')
            ->assertTableActionHasLabel('void', 'Return', $sale);
    }

    public function test_shop_list_uses_cupboard_words(): void
    {
        $this->napaOnShelf(5, paidNow: 0);

        Filament::setCurrentPanel('tenantAdmin');
        $this->actingAs($this->staff);

        Livewire::test(ListPharmacyItems::class)
            ->assertSee('Current stock')
            ->assertSee('Selling price')
            ->assertSee('Buying price')
            ->assertSee('Profit')
            ->assertDontSee('On shelf')
            ->assertDontSee('Shop cut');
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

    public function test_taka_words_match_a_pad_voucher(): void
    {
        $this->assertSame('Taka One Thousand One Hundred Only', TakaWords::english(1100));
        $this->assertSame('Taka Six Hundred Fifty Only', TakaWords::english(650));
        $this->assertSame('Taka Zero Only', TakaWords::english(0));
    }

    public function test_medicine_voucher_looks_like_the_printed_pad(): void
    {
        $chamber = Chamber::query()->first();
        $chamber->update([
            'address' => 'Neurosense, Mehedibag, Chattogram',
            'contact' => '01805414666',
        ]);
        $this->tenant->name = 'MUPS — Dr. Moin Uddin Pain Solution';
        $this->tenant->save();

        $item = $this->namedOnShelf('Joint Pro', 5, $chamber->id);
        $sale = app(PharmacySaleService::class)->sell(
            $this->staff,
            [['pharmacy_item_id' => $item->id, 'qty' => 1]],
            ChamberCashEntry::METHOD_CASH,
            false,
            null,
            'Abdul Rufe',
        );

        $this->assertSame(1, $sale->receipt_number);

        $this->actingAs($this->staff);
        $response = $this->get(tenant_web_route('pharmacy-invoices.show', ['sale' => $sale]));

        $response->assertOk();
        $response->assertSee('Medicine voucher', false);
        $response->assertSee('Customer Name (Mr/Mrs)', false);
        $response->assertSee('Abdul Rufe', false);
        $response->assertSee('Joint Pro', false);
        $response->assertSee('1,100/-', false);
        $response->assertSee('Taka One Thousand One Hundred Only', false);
        $response->assertSee('In Word', false);
        $response->assertSee('Received By', false);
        $response->assertSee('Customer Signature', false);
        $response->assertSee('Thank You.', false);
        $response->assertSee('MUPS', false);
        $response->assertSee('Cash', false);
        $response->assertSee('box is-on', false);
        $response->assertSee('01805-414666', false);
        $response->assertSee('20-08-26', false);
    }

    public function test_voucher_numbers_run_like_a_pad(): void
    {
        $item = $this->napaOnShelf(5, paidNow: 0);
        $first = app(PharmacySaleService::class)->sell(
            $this->staff,
            [['pharmacy_item_id' => $item->id, 'qty' => 1]],
            ChamberCashEntry::METHOD_CASH,
        );
        $second = app(PharmacySaleService::class)->sell(
            $this->staff,
            [['pharmacy_item_id' => $item->id, 'qty' => 1]],
            ChamberCashEntry::METHOD_CASH,
        );

        $this->assertSame(1, $first->receipt_number);
        $this->assertSame(2, $second->receipt_number);
    }

    public function test_printing_assigns_a_number_to_an_old_sale(): void
    {
        $item = $this->napaOnShelf(5, paidNow: 0);
        $sale = app(PharmacySaleService::class)->sell(
            $this->staff,
            [['pharmacy_item_id' => $item->id, 'qty' => 1]],
            ChamberCashEntry::METHOD_CASH,
        );
        $sale->receipt_number = null;
        $sale->save();

        $this->actingAs($this->staff)
            ->get(tenant_web_route('pharmacy-invoices.show', ['sale' => $sale]))
            ->assertOk()
            ->assertSee('>1<', false);

        $this->assertSame(1, $sale->fresh()->receipt_number);
    }

    public function test_guest_cannot_open_a_medicine_voucher(): void
    {
        $item = $this->napaOnShelf(5, paidNow: 0);
        $sale = app(PharmacySaleService::class)->sell(
            $this->staff,
            [['pharmacy_item_id' => $item->id, 'qty' => 1]],
            ChamberCashEntry::METHOD_CASH,
        );

        $this->withHeaders(['Accept' => 'application/json'])
            ->get(tenant_web_route('pharmacy-invoices.show', ['sale' => $sale]))
            ->assertUnauthorized();
    }

    public function test_queue_staff_cannot_open_a_medicine_voucher(): void
    {
        $item = $this->napaOnShelf(5, paidNow: 0);
        $sale = app(PharmacySaleService::class)->sell(
            $this->staff,
            [['pharmacy_item_id' => $item->id, 'qty' => 1]],
            ChamberCashEntry::METHOD_CASH,
        );
        $queue = User::create([
            'name' => 'Queue only',
            'email' => 'queue-voucher@pharmacy-shop.test',
            'password' => Hash::make('secret'),
            'role' => User::ROLE_STAFF,
            'tenant_id' => 'pharmacy-shop',
            'desk_jobs' => [StaffDeskJobs::JOB_QUEUE],
        ]);

        $this->actingAs($queue)
            ->get(tenant_web_route('pharmacy-invoices.show', ['sale' => $sale]))
            ->assertForbidden();
    }

    public function test_branch_staff_cannot_print_another_centre_voucher(): void
    {
        $mehedibag = Chamber::query()->first();
        $uttara = Chamber::create(['name' => 'Uttara']);
        $item = $this->namedOnShelf('Joint Pro', 5, $uttara->id);
        $sale = app(PharmacySaleService::class)->sell(
            $this->staff,
            [['pharmacy_item_id' => $item->id, 'qty' => 1]],
            ChamberCashEntry::METHOD_CASH,
        );

        $mehedibagStaff = User::create([
            'name' => 'Mehedibag voucher',
            'email' => 'mehedibag-voucher@pharmacy-shop.test',
            'password' => Hash::make('secret'),
            'role' => User::ROLE_STAFF,
            'tenant_id' => 'pharmacy-shop',
        ]);
        StaffDeskScope::syncChambers($mehedibagStaff, [$mehedibag->id]);

        $this->actingAs($mehedibagStaff)
            ->get(tenant_web_route('pharmacy-invoices.show', ['sale' => $sale]))
            ->assertForbidden();
    }

    public function test_counter_receipt_opens_the_voucher_page(): void
    {
        $item = $this->napaOnShelf(5, paidNow: 0);
        $sale = app(PharmacySaleService::class)->sell(
            $this->staff,
            [['pharmacy_item_id' => $item->id, 'qty' => 1]],
            ChamberCashEntry::METHOD_CASH,
            false,
            null,
            'Karim',
        );

        Filament::setCurrentPanel('tenantAdmin');
        $this->actingAs($this->staff);

        Livewire::test(PharmacyCounter::class)
            ->assertTableActionHasUrl(
                'receipt',
                tenant_web_route('pharmacy-invoices.show', ['sale' => $sale], absolute: false),
                $sale,
            );
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

    public function test_mixed_cash_and_online_records_split(): void
    {
        $item = $this->napaOnShelf(5, paidNow: 0);

        $sale = app(PharmacySaleService::class)->sell(
            $this->staff,
            [['pharmacy_item_id' => $item->id, 'qty' => 1]],
            ChamberCashEntry::METHOD_MIXED,
            false,
            null,
            null,
            null,
            null,
            400,
            250,
            ChamberCashEntry::METHOD_BKASH,
        );

        $this->assertSame(650, $sale->amount);
        $this->assertSame(400, $sale->cash_taka);
        $this->assertSame(250, $sale->mobile_taka);
        $this->assertSame(ChamberCashEntry::METHOD_BKASH, $sale->mobile_method);
    }

    public function test_partial_fill_and_skip_line(): void
    {
        $item = $this->napaOnShelf(10, paidNow: 0);
        $rx = $this->todaysPrescription();
        $secondLine = PrescriptionItem::create([
            'prescription_id' => $rx->id,
            'medicine_name' => 'ORS',
            'frequency' => '1+0+0',
            'duration' => '3 days',
            'sort_order' => 2,
        ]);

        $sale = app(PharmacySaleService::class)->sell(
            $this->staff,
            [
                ['pharmacy_item_id' => $item->id, 'qty' => 1, 'prescription_item_id' => $rx->items->first()->id],
                ['pharmacy_item_id' => $item->id, 'qty' => 0, 'prescription_item_id' => $secondLine->id],
            ],
            ChamberCashEntry::METHOD_CASH,
            false,
            $rx,
        );

        $this->assertSame(650, $sale->amount);
        $this->assertSame(1, $sale->items()->count());
        $this->assertSame(9, $item->fresh()->qty_on_hand);
    }

    public function test_supplier_refund_posts_income(): void
    {
        $item = $this->napaOnShelf(100, paidNow: 20000);
        app(PharmacySaleService::class)->sell(
            $this->staff,
            [['pharmacy_item_id' => $item->id, 'qty' => 40]],
            ChamberCashEntry::METHOD_CASH,
        );
        app(PharmacyStockService::class)->returnUnsold($item, $this->staff, 60);

        app(PharmacySupplierService::class)->recordRefund($this->staff, 8000, ChamberCashEntry::METHOD_CASH);

        $this->assertSame(0, app(PharmacySupplierService::class)->shopBalance()['refund_due']);
        $this->assertDatabaseHas('chamber_cash_entries', [
            'category' => ChamberCashEntry::CATEGORY_PHARMACY_SUPPLIER_REFUND,
            'amount' => 8000,
            'direction' => ChamberCashEntry::DIRECTION_INCOME,
        ]);
    }

    public function test_concurrent_last_unit_sale_only_one_succeeds(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('lockForUpdate is a no-op on SQLite');
        }

        $item = $this->napaOnShelf(1, paidNow: 0);
        $failures = 0;
        $successes = 0;

        foreach ([1, 2] as $attempt) {
            try {
                app(PharmacySaleService::class)->sell(
                    $this->staff,
                    [['pharmacy_item_id' => $item->id, 'qty' => 1]],
                    ChamberCashEntry::METHOD_CASH,
                );
                $successes++;
            } catch (InvalidArgumentException) {
                $failures++;
            }
        }

        $this->assertSame(1, $successes);
        $this->assertSame(1, $failures);
        $this->assertSame(0, $item->fresh()->qty_on_hand);
        $this->assertSame(1, PharmacySale::query()->count());
    }

    public function test_main_staff_can_update_shop_stock_but_queue_staff_and_doctors_cannot(): void
    {
        $owner = User::create([
            'name' => 'Owner',
            'email' => 'owner@pharmacy-shop.test',
            'password' => Hash::make('secret'),
            'role' => User::ROLE_OWNER,
            'tenant_id' => 'pharmacy-shop',
        ]);
        $queue = User::create([
            'name' => 'Queue',
            'email' => 'queue@pharmacy-shop.test',
            'password' => Hash::make('secret'),
            'role' => User::ROLE_STAFF,
            'tenant_id' => 'pharmacy-shop',
            'desk_jobs' => [StaffDeskJobs::JOB_QUEUE],
        ]);
        $moneyOnly = User::create([
            'name' => 'Money',
            'email' => 'money@pharmacy-shop.test',
            'password' => Hash::make('secret'),
            'role' => User::ROLE_STAFF,
            'tenant_id' => 'pharmacy-shop',
            'desk_jobs' => [StaffDeskJobs::JOB_MONEY],
        ]);

        $this->actingAs($this->staff);
        $this->assertTrue(PharmacyAccess::canManageStock($this->staff));
        $this->assertTrue(PharmacyItemResource::canViewAny());
        $this->assertFalse(PharmacyPhysicalCount::canAccess());
        $this->assertFalse(PharmacyPaySupplier::canAccess());

        $this->actingAs($moneyOnly);
        $this->assertTrue(PharmacyAccess::canManageStock($moneyOnly));

        $this->actingAs($owner);
        $this->assertTrue(PharmacyAccess::canManageStock($owner));

        $this->actingAs($queue);
        $this->assertFalse(PharmacyAccess::canManageStock($queue));
        $this->assertFalse(PharmacyItemResource::canViewAny());
        $this->assertFalse(PharmacyPhysicalCount::canAccess());

        $this->actingAs($this->doctorUser);
        $this->assertFalse(PharmacyAccess::canManageStock($this->doctorUser));
        $this->assertFalse(PharmacyItemResource::canViewAny());
    }

    public function test_physical_count_and_pay_supplier_stay_off_the_menu(): void
    {
        $owner = User::query()->where('email', 'owner@pharmacy-shop.test')->first()
            ?? User::create([
                'name' => 'Owner',
                'email' => 'owner@pharmacy-shop.test',
                'password' => Hash::make('secret'),
                'role' => User::ROLE_OWNER,
                'tenant_id' => 'pharmacy-shop',
            ]);

        foreach ([$this->staff, $owner] as $user) {
            $this->actingAs($user);
            $this->assertFalse(PharmacyPhysicalCount::canAccess());
            $this->assertFalse(PharmacyPaySupplier::canAccess());
        }
    }

    public function test_selling_at_one_centre_does_not_empty_the_other_cupboard(): void
    {
        $mehedibag = Chamber::query()->first();
        $uttara = Chamber::create(['name' => 'Uttara']);

        $mehedibagItem = $this->namedOnShelf('Joint Pro', 10, $mehedibag->id);
        $uttaraItem = $this->namedOnShelf('Joint Pro', 10, $uttara->id);

        $mehedibagStaff = User::create([
            'name' => 'Mehedibag desk',
            'email' => 'mehedibag@pharmacy-shop.test',
            'password' => Hash::make('secret'),
            'role' => User::ROLE_STAFF,
            'tenant_id' => 'pharmacy-shop',
        ]);
        StaffDeskScope::syncChambers($mehedibagStaff, [$mehedibag->id]);

        app(PharmacySaleService::class)->sell(
            $this->staff,
            [['pharmacy_item_id' => $uttaraItem->id, 'qty' => 1]],
            ChamberCashEntry::METHOD_CASH,
        );

        $this->assertSame(10, $mehedibagItem->fresh()->qty_on_hand);
        $this->assertSame(9, $uttaraItem->fresh()->qty_on_hand);

        $visible = PharmacyAccess::scopedItems($mehedibagStaff)->pluck('id');
        $this->assertTrue($visible->contains($mehedibagItem->id));
        $this->assertFalse($visible->contains($uttaraItem->id));

        try {
            app(PharmacySaleService::class)->sell(
                $this->staff,
                [
                    ['pharmacy_item_id' => $mehedibagItem->id, 'qty' => 1],
                    ['pharmacy_item_id' => $uttaraItem->id, 'qty' => 1],
                ],
                ChamberCashEntry::METHOD_CASH,
            );
            $this->fail('A basket must not mix two centres.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('mix two centres', $e->getMessage());
        }

        app(PharmacyStockService::class)->startCount($this->staff, $mehedibag->id);
        app(PharmacyStockService::class)->startCount($this->staff, $uttara->id);
        $this->assertSame(2, PharmacyCount::query()->where('status', PharmacyCount::STATUS_IN_PROGRESS)->count());

        $this->expectException(InvalidArgumentException::class);
        app(PharmacyStockService::class)->startCount($mehedibagStaff, $uttara->id);
    }

    private function namedOnShelf(string $name, int $qty, int $chamberId, int $paidNow = 0): PharmacyItem
    {
        $item = PharmacyItem::create([
            'name' => $name,
            'chamber_id' => $chamberId,
            'sell_price_taka' => 1100,
            'company_share_taka' => 840,
            'unit_label' => PharmacyItem::UNIT_BOTTLE,
            'qty_on_hand' => 0,
            'is_active' => true,
        ]);
        app(PharmacyStockService::class)->receive(
            $item,
            $this->staff,
            $qty,
            $paidNow,
            true,
        );

        return $item->fresh();
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
