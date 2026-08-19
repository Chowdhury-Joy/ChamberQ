<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\ChamberCashEntry;
use App\Models\LiveSession;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\PrescriptionTemplate;
use App\Models\ScheduleSessionOverride;
use App\Models\SlotBlock;
use App\Models\SmsMessage;
use App\Models\Tenant;
use App\Models\VisitRecord;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SoloDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_solo_demo_seeder_populates_admin_panel_data(): void
    {
        $this->seed(DatabaseSeeder::class);

        tenancy()->initialize(Tenant::find('solo'));

        $this->assertGreaterThanOrEqual(14, Patient::count());
        $this->assertGreaterThanOrEqual(10, Booking::whereDate('booking_date', Carbon::today())->count());
        $this->assertGreaterThanOrEqual(30, Booking::count());

        $liveSession = LiveSession::whereDate('session_date', Carbon::today())->first();
        $this->assertNotNull($liveSession);
        $this->assertSame('active', $liveSession->status);
        $this->assertNotNull($liveSession->current_booking_id);

        $inChamber = Booking::find($liveSession->current_booking_id);
        $this->assertSame('in_chamber', $inChamber->status);

        $this->assertGreaterThanOrEqual(1, Booking::where('status', 'called')->count());
        $this->assertGreaterThanOrEqual(1, Booking::where('status', 'cancelled')->count());
        $this->assertGreaterThanOrEqual(1, Booking::where('wants_earlier_date', true)->count());

        $this->assertGreaterThanOrEqual(3, VisitRecord::count());
        $this->assertGreaterThanOrEqual(3, Prescription::count());
        $this->assertGreaterThanOrEqual(2, VisitRecord::whereNotNull('follow_up_reminder_whatsapp_queued_at')->count());
        $this->assertGreaterThanOrEqual(3, PrescriptionTemplate::count());
        $this->assertGreaterThanOrEqual(2, SlotBlock::count());
        $this->assertGreaterThanOrEqual(1, ScheduleSessionOverride::count());

        $this->assertGreaterThanOrEqual(4, ChamberCashEntry::where('direction', ChamberCashEntry::DIRECTION_INCOME)->count());
        $this->assertGreaterThanOrEqual(4, ChamberCashEntry::where('direction', ChamberCashEntry::DIRECTION_EXPENSE)->count());
        $this->assertGreaterThanOrEqual(1, ChamberCashEntry::where('category', ChamberCashEntry::CATEGORY_WAIVED)->count());

        $this->assertGreaterThanOrEqual(1, SmsMessage::where('purpose', SmsMessage::PURPOSE_BOOKING_CONFIRMATION)->count());
        $this->assertGreaterThanOrEqual(1, SmsMessage::where('purpose', SmsMessage::PURPOSE_CANCELLATION)->count());

        tenancy()->end();
    }
}
