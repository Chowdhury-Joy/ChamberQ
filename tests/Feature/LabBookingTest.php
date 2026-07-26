<?php

namespace Tests\Feature;

use App\Exceptions\BookingUnavailableException;
use App\Models\Chamber;
use App\Models\LabCollectionSlot;
use App\Models\LabTest;
use App\Models\Tenant;
use App\Services\BookingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LabBookingTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $clinic;

    private LabCollectionSlot $slot;

    private BookingService $service;

    private string $mondayDate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clinic = Tenant::create(['id' => 'clinic', 'plan_tier' => 'clinic']);
        tenancy()->initialize($this->clinic);

        $chamber = Chamber::create(['name' => 'Main']);
        $this->slot = LabCollectionSlot::create([
            'chamber_id' => $chamber->id,
            'day_of_week' => 1,
            'start_time' => '08:00',
            'end_time' => '11:00',
            'slot_cap' => 10,
        ]);

        $this->service = app(BookingService::class);
        $this->mondayDate = Carbon::now()->next(1)->format('Y-m-d');
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    private function test(string $name, float $price, ?string $prep = null): LabTest
    {
        return LabTest::create([
            'name' => $name,
            'price' => $price,
            'preparation_instructions' => $prep,
        ]);
    }

    public function test_line_items_snapshot_the_price_at_booking(): void
    {
        $cbc = $this->test('CBC', 500.00);

        $booking = $this->service->createBookingForBookable(
            $this->slot, $this->mondayDate, 'Patient', '01712345678', [$cbc->id]
        );

        // The catalogue price changes after the booking is made.
        $cbc->update(['price' => 900.00]);

        $booking->refresh()->load('labTests');

        $this->assertSame('500.00', $booking->labTests->first()->pivot->price_at_booking);
        $this->assertSame('500.00', $booking->totalPrice(), 'Total must use quoted prices, not current ones.');
    }

    public function test_multiple_tests_consume_one_slot_and_one_serial(): void
    {
        $ids = [
            $this->test('CBC', 500)->id,
            $this->test('Lipid', 1200)->id,
            $this->test('TSH', 900)->id,
        ];

        $booking = $this->service->createBookingForBookable(
            $this->slot, $this->mondayDate, 'Patient', '01712345678', $ids
        );

        $this->assertCount(3, $booking->labTests()->get());
        // One patient arriving for one sample collection: one serial, one slot.
        $this->assertSame(1, $booking->serial_number);
        $this->assertSame('2600.00', $booking->totalPrice());
    }

    public function test_capacity_counts_bookings_not_line_items(): void
    {
        $this->slot->update(['slot_cap' => 2]);
        $ids = [$this->test('A', 100)->id, $this->test('B', 100)->id, $this->test('C', 100)->id];

        // Two bookings of three tests each must consume two of two slots, not six.
        $this->service->createBookingForBookable($this->slot, $this->mondayDate, 'P1', '01712345678', $ids);
        $this->service->createBookingForBookable($this->slot, $this->mondayDate, 'P2', '01712345679', $ids);

        $this->expectException(BookingUnavailableException::class);
        $this->expectExceptionMessage('fully booked');

        $this->service->createBookingForBookable($this->slot, $this->mondayDate, 'P3', '01712345670', $ids);
    }

    public function test_preparation_instructions_are_aggregated(): void
    {
        $ids = [
            $this->test('Fasting Sugar', 300, 'Do not eat for 12 hours.')->id,
            $this->test('CBC', 500, null)->id,
            $this->test('Lipid', 1200, '12 hours fasting required.')->id,
        ];

        $booking = $this->service->createBookingForBookable(
            $this->slot, $this->mondayDate, 'Patient', '01712345678', $ids
        );

        $prep = $booking->load('labTests')->preparationInstructions();

        // Only tests that actually have instructions appear.
        $this->assertCount(2, $prep);
        $this->assertSame('Fasting Sugar', $prep[0]['test']);
    }

    public function test_another_tenants_lab_test_cannot_be_attached(): void
    {
        $other = Tenant::create(['id' => 'other', 'plan_tier' => 'clinic']);

        tenancy()->initialize($other);
        $foreign = LabTest::create(['name' => 'Foreign Test', 'price' => 100]);
        tenancy()->initialize($this->clinic);

        $this->expectException(BookingUnavailableException::class);

        $this->service->createBookingForBookable(
            $this->slot, $this->mondayDate, 'Patient', '01712345678', [$foreign->id]
        );
    }

    public function test_a_solo_tenant_cannot_book_lab_tests(): void
    {
        $solo = Tenant::create(['id' => 'solo', 'plan_tier' => 'solo']);

        tenancy()->initialize($solo);
        $chamber = Chamber::create(['name' => 'Solo']);
        $slot = LabCollectionSlot::create([
            'chamber_id' => $chamber->id, 'day_of_week' => 1,
            'start_time' => '08:00', 'end_time' => '11:00', 'slot_cap' => 5,
        ]);
        $labTest = LabTest::create(['name' => 'CBC', 'price' => 500]);

        $this->expectException(BookingUnavailableException::class);

        $this->service->createBookingForBookable(
            $slot, $this->mondayDate, 'Patient', '01712345678', [$labTest->id]
        );
    }

    public function test_an_inactive_test_cannot_be_booked(): void
    {
        $retired = $this->test('Retired', 100);
        $retired->update(['is_active' => false]);

        $this->expectException(BookingUnavailableException::class);

        $this->service->createBookingForBookable(
            $this->slot, $this->mondayDate, 'Patient', '01712345678', [$retired->id]
        );
    }

    public function test_a_failed_line_item_rolls_back_the_whole_booking(): void
    {
        $valid = $this->test('CBC', 500);

        try {
            $this->service->createBookingForBookable(
                $this->slot, $this->mondayDate, 'Patient', '01712345678', [$valid->id, 999999]
            );
        } catch (BookingUnavailableException) {
            // expected
        }

        // No orphaned booking without its tests.
        $this->assertSame(0, \App\Models\Booking::count());
    }
}
