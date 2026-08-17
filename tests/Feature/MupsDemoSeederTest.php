<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\ChamberCashEntry;
use App\Models\LabTest;
use App\Models\LiveSession;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\ScheduleSessionOverride;
use App\Models\SlotBlock;
use App\Models\Tenant;
use App\Models\VisitRecord;
use Carbon\Carbon;
use Database\Seeders\MupsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MupsDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_mups_admin_demo_fills_queue_patients_cashbook_and_labs(): void
    {
        $this->seed(MupsSeeder::class);

        tenancy()->initialize(Tenant::find(MupsSeeder::TENANT_ID));

        $this->assertGreaterThanOrEqual(12, Patient::count());
        $this->assertGreaterThanOrEqual(8, Booking::whereDate('booking_date', Carbon::today())->count());
        $this->assertGreaterThanOrEqual(30, Booking::count());

        $liveSession = LiveSession::whereDate('session_date', Carbon::today())->first();
        $this->assertNotNull($liveSession);
        $this->assertSame('active', $liveSession->status);
        $this->assertNotNull($liveSession->current_booking_id);
        $this->assertSame('in_chamber', Booking::find($liveSession->current_booking_id)?->status);

        $this->assertGreaterThanOrEqual(3, VisitRecord::count());
        $this->assertGreaterThanOrEqual(3, Prescription::count());
        $this->assertGreaterThanOrEqual(1, Booking::where('wants_earlier_date', true)->count());
        $this->assertGreaterThanOrEqual(2, SlotBlock::count());
        $this->assertGreaterThanOrEqual(1, ScheduleSessionOverride::count());
        $this->assertGreaterThanOrEqual(5, LabTest::count());
        $this->assertGreaterThanOrEqual(4, ChamberCashEntry::where('direction', ChamberCashEntry::DIRECTION_INCOME)->count());
        $this->assertGreaterThanOrEqual(4, ChamberCashEntry::where('direction', ChamberCashEntry::DIRECTION_EXPENSE)->count());

        tenancy()->end();
    }
}
