<?php

namespace Tests\Feature;

use App\Filament\TenantAdmin\Pages\BookSerial;
use App\Filament\TenantAdmin\Support\StaffBookingForm;
use App\Jobs\SendBookingConfirmation;
use App\Models\Booking;
use App\Models\Chamber;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\ScheduleSession;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CarePath;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class BookSerialPageTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $staff;

    private ScheduleSession $visit;

    private ScheduleSession $intervention;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-08-19 10:00'));

        $this->tenant = Tenant::create(['id' => 'book-serial', 'plan_tier' => 'clinic']);
        tenancy()->initialize($this->tenant);

        $chamber = Chamber::create(['name' => 'Main']);
        $doctor = Doctor::create(['name' => 'Dr Karim', 'default_fee_taka' => 800]);

        $this->visit = ScheduleSession::create([
            'chamber_id' => $chamber->id,
            'doctor_id' => $doctor->id,
            'day_of_week' => Carbon::parse('2026-08-22')->dayOfWeek,
            'session_name' => 'Morning',
            'kind' => ScheduleSession::KIND_VISIT,
            'start_time' => '09:00',
            'end_time' => '12:00',
            'slot_cap' => 2,
        ]);
        $this->intervention = ScheduleSession::create([
            'chamber_id' => $chamber->id,
            'doctor_id' => $doctor->id,
            'day_of_week' => Carbon::parse('2026-08-22')->dayOfWeek,
            'session_name' => 'OT',
            'kind' => ScheduleSession::KIND_INTERVENTION,
            'start_time' => '08:00',
            'end_time' => '10:00',
            'slot_cap' => 4,
        ]);

        $this->staff = User::create([
            'name' => 'Call centre',
            'email' => 'calls@book-serial.test',
            'password' => Hash::make('secret'),
            'role' => User::ROLE_STAFF,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        tenancy()->end();

        parent::tearDown();
    }

    public function test_staff_can_book_a_future_visit_from_the_admin_page(): void
    {
        Queue::fake();
        Filament::setCurrentPanel('tenantAdmin');
        $this->actingAs($this->staff);

        $date = '2026-08-22';

        Livewire::test(BookSerial::class)
            ->fillForm([
                'booking_date' => $date,
                'bookable' => 'session:'.$this->visit->id,
                'patient_phone' => '01715553001',
                'patient_name' => 'Fatima Rahman',
            ])
            ->call('book')
            ->assertHasNoFormErrors()
            ->assertSet('showBookedSerialModal', true)
            ->assertSet('lastBooked.serial', 1)
            ->assertSee('Fatima Rahman');

        $booking = Booking::query()->first();
        $this->assertNotNull($booking);
        $this->assertSame($date, $booking->booking_date->toDateString());
        $this->assertSame(1, $booking->serial_number);
        $this->assertFalse($booking->is_overflow);
        $this->assertSame($this->visit->id, $booking->bookable_id);

        Queue::assertPushed(SendBookingConfirmation::class);
    }

    public function test_phone_booking_uses_the_published_cap_not_walk_in_stools(): void
    {
        $this->visit->update(['slot_cap' => 1, 'walk_in_overflow_cap' => 5]);

        Queue::fake();
        Filament::setCurrentPanel('tenantAdmin');
        $this->actingAs($this->staff);

        $date = '2026-08-22';

        Livewire::test(BookSerial::class)
            ->fillForm([
                'booking_date' => $date,
                'bookable' => 'session:'.$this->visit->id,
                'patient_phone' => '01715553002',
                'patient_name' => 'First',
            ])
            ->call('book');

        $this->assertSame(1, Booking::query()->count());

        Livewire::test(BookSerial::class)
            ->fillForm([
                'booking_date' => $date,
                'bookable' => 'session:'.$this->visit->id,
                'patient_phone' => '01715553003',
                'patient_name' => 'Second',
            ])
            ->call('book');

        $this->assertSame(1, Booking::query()->count());
    }

    public function test_call_centre_sitting_list_hides_intervention(): void
    {
        $options = StaffBookingForm::bookableOptions('2026-08-22', 'usual');

        $this->assertArrayHasKey('session:'.$this->visit->id, $options);
        $this->assertArrayNotHasKey('session:'.$this->intervention->id, $options);
    }

    public function test_staff_can_store_a_different_whatsapp_number(): void
    {
        Queue::fake();
        Filament::setCurrentPanel('tenantAdmin');
        $this->actingAs($this->staff);

        Livewire::test(BookSerial::class)
            ->fillForm([
                'booking_date' => '2026-08-22',
                'bookable' => 'session:'.$this->visit->id,
                'patient_phone' => '01715553010',
                'patient_name' => 'Call Phone Patient',
                'different_whatsapp' => true,
                'whatsapp_phone' => '01812345678',
            ])
            ->call('book')
            ->assertHasNoFormErrors()
            ->assertSet('showBookedSerialModal', true);

        $booking = Booking::query()->first();
        $this->assertNotNull($booking);
        $this->assertSame('01715553010', $booking->patient_phone);
        $this->assertSame('01812345678', $booking->whatsapp_phone);
        $this->assertStringContainsString('8801812345678', $booking->whatsappLink('hi'));
    }

    public function test_follow_up_visit_type_marks_a_returning_patient(): void
    {
        Queue::fake();
        $this->enableStations();
        Filament::setCurrentPanel('tenantAdmin');
        $this->actingAs($this->staff);

        Livewire::test(BookSerial::class)
            ->fillForm([
                'booking_date' => '2026-08-22',
                'visit_type' => 'followup',
                'bookable' => 'session:'.$this->visit->id,
                'patient_phone' => '01715553020',
                'patient_name' => 'Returnee',
            ])
            ->call('book')
            ->assertHasNoFormErrors();

        $patient = Patient::query()->where('phone', '01715553020')->first();
        $this->assertTrue($patient?->seen_before_software);
        $this->assertSame(CarePath::FOLLOW_UP, Booking::query()->first()?->care_path);
    }

    public function test_intervention_visit_type_books_an_ot_sitting(): void
    {
        Queue::fake();
        $this->enableStations();
        Filament::setCurrentPanel('tenantAdmin');
        $this->actingAs($this->staff);

        $this->assertArrayHasKey(
            'session:'.$this->intervention->id,
            StaffBookingForm::bookableOptions('2026-08-22', 'intervention'),
        );

        Livewire::test(BookSerial::class)
            ->fillForm([
                'booking_date' => '2026-08-22',
                'visit_type' => 'intervention',
                'bookable' => 'session:'.$this->intervention->id,
                'patient_phone' => '01715553021',
                'patient_name' => 'OT Patient',
            ])
            ->call('book')
            ->assertHasNoFormErrors();

        $booking = Booking::query()->first();
        $this->assertSame($this->intervention->id, $booking?->bookable_id);
        $this->assertSame(CarePath::INTERVENTION, $booking?->care_path);
    }

    public function test_intervention_visit_type_requires_a_procedure_from_the_catalogue(): void
    {
        Queue::fake();
        $this->enableStations();
        $prp = \App\Models\FeeCatalogItem::create([
            'label' => 'PRP knee (single)',
            'list_price_taka' => 8000,
            'house_share_taka' => 1000,
            'sitting_kind' => ScheduleSession::KIND_INTERVENTION,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        Filament::setCurrentPanel('tenantAdmin');
        $this->actingAs($this->staff);

        $this->assertArrayHasKey(
            (string) $prp->id,
            StaffBookingForm::interventionTypeOptions(),
        );

        Livewire::test(BookSerial::class)
            ->fillForm([
                'booking_date' => '2026-08-22',
                'visit_type' => 'intervention',
                'bookable' => 'session:'.$this->intervention->id,
                'patient_phone' => '01715553023',
                'patient_name' => 'PRP Patient',
            ])
            ->call('book')
            ->assertHasFormErrors(['intervention_type']);

        Livewire::test(BookSerial::class)
            ->fillForm([
                'booking_date' => '2026-08-22',
                'visit_type' => 'intervention',
                'intervention_type' => (string) $prp->id,
                'bookable' => 'session:'.$this->intervention->id,
                'patient_phone' => '01715553023',
                'patient_name' => 'PRP Patient',
            ])
            ->call('book')
            ->assertHasNoFormErrors();

        $booking = Booking::query()->first();
        $this->assertSame($prp->id, $booking?->fee_catalog_item_id);
        $this->assertSame(
            $prp->id,
            \App\Filament\TenantAdmin\Support\StationsCollectFeeForm::fillFromEntry($booking)['fee_catalog_item_id'],
        );
    }

    public function test_lab_visit_type_books_an_msk_scan(): void
    {
        Queue::fake();
        $this->enableStations();
        Filament::setCurrentPanel('tenantAdmin');
        $this->actingAs($this->staff);

        $msk = ScheduleSession::create([
            'chamber_id' => $this->visit->chamber_id,
            'doctor_id' => $this->visit->doctor_id,
            'day_of_week' => Carbon::parse('2026-08-22')->dayOfWeek,
            'session_name' => 'Scan',
            'kind' => ScheduleSession::KIND_MSK,
            'start_time' => '10:00',
            'end_time' => '12:00',
            'slot_cap' => 4,
        ]);

        Livewire::test(BookSerial::class)
            ->fillForm([
                'booking_date' => '2026-08-22',
                'visit_type' => 'lab',
                'lab_type' => 'msk',
                'bookable' => 'session:'.$msk->id,
                'patient_phone' => '01715553022',
                'patient_name' => 'Scan Patient',
            ])
            ->call('book')
            ->assertHasNoFormErrors();

        $booking = Booking::query()->first();
        $this->assertSame($msk->id, $booking?->bookable_id);
        $this->assertSame(CarePath::MSK, $booking?->care_path);
    }

    private function enableStations(): void
    {
        $this->tenant->update([
            'feature_flags' => array_merge($this->tenant->feature_flags ?? [], [
                Tenant::MODULE_STATIONS => true,
            ]),
        ]);
        tenancy()->initialize($this->tenant->fresh());
    }
}
