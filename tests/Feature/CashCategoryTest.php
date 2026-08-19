<?php

namespace Tests\Feature;

use App\Filament\TenantAdmin\Pages\Cashbook;
use App\Filament\TenantAdmin\Resources\CashCategories\CashCategoryResource;
use App\Filament\TenantAdmin\Resources\CashCategories\Pages\ListCashCategories;
use App\Models\CashCategory;
use App\Models\Chamber;
use App\Models\ChamberCashEntry;
use App\Models\Domain;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CashCategoryService;
use App\Services\ChamberCashService;
use App\Services\OperationalReportService;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

class CashCategoryTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['id' => 'cash-cat-clinic', 'plan_tier' => 'solo']);
        Domain::create(['domain' => 'cash-cat-clinic.localhost', 'tenant_id' => 'cash-cat-clinic']);
        tenancy()->initialize($this->tenant);

        Chamber::create(['name' => 'Main']);
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    private function makeUser(string $role): User
    {
        return User::create([
            'name' => ucfirst($role),
            'email' => $role.'@cash-cat-clinic.loc',
            'password' => Hash::make('secret'),
            'role' => $role,
            'tenant_id' => 'cash-cat-clinic',
        ]);
    }

    public function test_ensure_defaults_seeds_built_in_categories(): void
    {
        app(CashCategoryService::class)->ensureDefaults();

        $this->assertSame(11, CashCategory::query()->count());
        $this->assertDatabaseHas('cash_categories', [
            'code' => ChamberCashEntry::CATEGORY_SALARY,
            'is_locked' => true,
        ]);
    }

    public function test_custom_expense_appears_in_picker(): void
    {
        $service = app(CashCategoryService::class);
        $service->ensureDefaults();
        $service->createCustom('Cleaning', CashCategory::TYPE_EXPENSE);

        $options = $service->pickerOptions(CashCategory::TYPE_EXPENSE);

        $this->assertArrayHasKey('cleaning', $options);
        $this->assertSame('Cleaning', $options['cleaning']);
    }

    public function test_hiding_transport_removes_it_from_expense_picker(): void
    {
        $service = app(CashCategoryService::class);
        $service->ensureDefaults();

        CashCategory::query()
            ->where('code', ChamberCashEntry::CATEGORY_TRANSPORT)
            ->update(['is_active' => false]);

        $options = $service->pickerOptions(CashCategory::TYPE_EXPENSE);

        $this->assertArrayNotHasKey(ChamberCashEntry::CATEGORY_TRANSPORT, $options);
    }

    public function test_record_other_income_stores_chosen_category(): void
    {
        $service = app(CashCategoryService::class);
        $service->ensureDefaults();
        $service->createCustom('Room rent', CashCategory::TYPE_INCOME);

        $admin = $this->makeUser(User::ROLE_ADMIN);
        $day = Carbon::parse('2026-08-13', OperationalReportService::TIMEZONE);

        $entry = app(ChamberCashService::class)->recordOtherIncome(
            $admin,
            1200,
            'room_rent',
            ChamberCashEntry::METHOD_CASH,
            $day,
        );

        $this->assertSame('room_rent', $entry->category);
        $this->assertSame('Room rent', $entry->cashbookSubjectLabel());
    }

    public function test_cannot_pick_patient_fee_for_manual_income(): void
    {
        app(CashCategoryService::class)->ensureDefaults();
        $admin = $this->makeUser(User::ROLE_ADMIN);
        $day = Carbon::parse('2026-08-13', OperationalReportService::TIMEZONE);

        $this->expectException(InvalidArgumentException::class);

        app(ChamberCashService::class)->recordOtherIncome(
            $admin,
            500,
            ChamberCashEntry::CATEGORY_PATIENT,
            ChamberCashEntry::METHOD_CASH,
            $day,
        );
    }

    public function test_cannot_delete_locked_salary_category(): void
    {
        app(CashCategoryService::class)->ensureDefaults();

        $salary = CashCategory::query()->where('code', ChamberCashEntry::CATEGORY_SALARY)->firstOrFail();

        $this->assertFalse(app(CashCategoryService::class)->canDelete($salary));
    }

    public function test_cannot_delete_custom_category_in_use(): void
    {
        $service = app(CashCategoryService::class);
        $service->ensureDefaults();
        $category = $service->createCustom('Cleaning', CashCategory::TYPE_EXPENSE);

        $admin = $this->makeUser(User::ROLE_ADMIN);
        $day = Carbon::parse('2026-08-13', OperationalReportService::TIMEZONE);

        app(ChamberCashService::class)->recordExpense(
            $admin,
            200,
            $category->code,
            ChamberCashEntry::METHOD_CASH,
            $day,
        );

        $this->assertFalse($service->canDelete($category));
    }

    public function test_only_admin_can_open_cash_categories_page(): void
    {
        app(CashCategoryService::class)->ensureDefaults();

        $this->actingAs($this->makeUser(User::ROLE_ADMIN));
        $this->assertTrue(CashCategoryResource::canViewAny());

        $this->actingAs($this->makeUser(User::ROLE_STAFF));
        $this->assertFalse(CashCategoryResource::canViewAny());
    }

    public function test_admin_can_add_category_from_list_page(): void
    {
        app(CashCategoryService::class)->ensureDefaults();

        $admin = $this->makeUser(User::ROLE_ADMIN);
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('tenantAdmin'));

        Livewire::test(ListCashCategories::class)
            ->set('categoryType', CashCategory::TYPE_EXPENSE)
            ->callAction('create', data: ['name' => 'Facebook ads'])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('cash_categories', [
            'name' => 'Facebook ads',
            'type' => CashCategory::TYPE_EXPENSE,
            'code' => 'facebook_ads',
        ]);
    }

    public function test_cashbook_add_income_form_includes_income_categories(): void
    {
        $service = app(CashCategoryService::class);
        $service->ensureDefaults();
        $service->createCustom('Training', CashCategory::TYPE_INCOME);

        $admin = $this->makeUser(User::ROLE_ADMIN);
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('tenantAdmin'));

        Livewire::test(Cashbook::class)
            ->callAction('addIncome', data: [
                'amount' => 500,
                'category' => 'training',
                'method' => ChamberCashEntry::METHOD_CASH,
                'occurred_on' => '2026-08-13',
                'chamber_id' => null,
                'note' => 'Workshop fee',
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('chamber_cash_entries', [
            'direction' => ChamberCashEntry::DIRECTION_INCOME,
            'category' => 'training',
            'amount' => 500,
        ]);
    }
}
