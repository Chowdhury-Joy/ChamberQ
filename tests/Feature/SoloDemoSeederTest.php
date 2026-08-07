<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\LiveSession;
use App\Models\Patient;
use App\Models\Tenant;
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

        $this->assertGreaterThanOrEqual(10, Patient::count());
        $this->assertGreaterThanOrEqual(8, Booking::whereDate('booking_date', Carbon::today())->count());
        $this->assertGreaterThanOrEqual(30, Booking::count());

        $liveSession = LiveSession::whereDate('session_date', Carbon::today())->first();
        $this->assertNotNull($liveSession);
        $this->assertSame('active', $liveSession->status);
        $this->assertNotNull($liveSession->current_booking_id);

        $inChamber = Booking::find($liveSession->current_booking_id);
        $this->assertSame('in_chamber', $inChamber->status);

        tenancy()->end();
    }
}
