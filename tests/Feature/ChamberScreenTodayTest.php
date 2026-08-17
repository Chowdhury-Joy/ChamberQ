<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Chamber;
use App\Models\Doctor;
use App\Models\Domain;
use App\Models\LiveSession;
use App\Models\ScheduleSession;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChamberScreenTodayTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Chamber $chamber;

    private Chamber $otherChamber;

    private ScheduleSession $visit;

    private ScheduleSession $intervention;

    private string $today;

    private string $host;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'id' => 'chamber-tv',
            'name' => 'Multi room TV',
        ]);
        Domain::create(['domain' => 'chamber-tv.localhost', 'tenant_id' => 'chamber-tv']);
        $this->host = 'http://chamber-tv.localhost';

        tenancy()->initialize($this->tenant);

        $this->chamber = Chamber::create(['name' => 'Panchlaish']);
        $this->otherChamber = Chamber::create(['name' => 'Halishahar']);
        $doctor = Doctor::create(['name' => 'Dr TV']);
        $day = Carbon::today()->dayOfWeek;

        $this->intervention = ScheduleSession::create([
            'chamber_id' => $this->chamber->id,
            'doctor_id' => $doctor->id,
            'day_of_week' => $day,
            'session_name' => 'Intervention',
            'kind' => ScheduleSession::KIND_INTERVENTION,
            'start_time' => '10:00',
            'end_time' => '12:00',
            'slot_cap' => 10,
        ]);
        $this->visit = ScheduleSession::create([
            'chamber_id' => $this->chamber->id,
            'doctor_id' => $doctor->id,
            'day_of_week' => $day,
            'session_name' => 'Visit',
            'kind' => ScheduleSession::KIND_VISIT,
            'start_time' => '12:00',
            'end_time' => '14:30',
            'slot_cap' => 10,
        ]);
        ScheduleSession::create([
            'chamber_id' => $this->otherChamber->id,
            'doctor_id' => $doctor->id,
            'day_of_week' => $day,
            'session_name' => 'Other visit',
            'kind' => ScheduleSession::KIND_VISIT,
            'start_time' => '12:00',
            'end_time' => '14:30',
            'slot_cap' => 10,
        ]);

        $this->today = Carbon::today()->toDateString();
        tenancy()->end();
    }

    public function test_chamber_screen_lists_one_tile_per_live_sitting_and_omits_the_rest(): void
    {
        tenancy()->initialize($this->tenant);

        $visitLive = LiveSession::create([
            'schedule_session_id' => $this->visit->id,
            'session_date' => $this->today,
            'status' => 'active',
            'started_at' => now(),
        ]);
        $called = Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $this->visit->id,
            'booking_date' => $this->today,
            'patient_name' => 'Called one',
            'patient_phone' => '01718000001',
            'serial_number' => 1,
            'status' => 'called',
            'called_at' => now(),
        ]);
        $visitLive->update(['current_booking_id' => $called->id]);

        Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $this->visit->id,
            'booking_date' => $this->today,
            'patient_name' => 'Wait two',
            'patient_phone' => '01718000002',
            'serial_number' => 2,
            'status' => 'waiting',
        ]);
        Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $this->visit->id,
            'booking_date' => $this->today,
            'patient_name' => 'Wait three',
            'patient_phone' => '01718000003',
            'serial_number' => 3,
            'status' => 'waiting',
        ]);
        Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $this->visit->id,
            'booking_date' => $this->today,
            'patient_name' => 'Wait four',
            'patient_phone' => '01718000004',
            'serial_number' => 4,
            'status' => 'waiting',
        ]);
        Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $this->visit->id,
            'booking_date' => $this->today,
            'patient_name' => 'Wait five',
            'patient_phone' => '01718000005',
            'serial_number' => 5,
            'status' => 'waiting',
        ]);

        LiveSession::create([
            'schedule_session_id' => $this->intervention->id,
            'session_date' => $this->today,
            'status' => 'active',
            'started_at' => now(),
        ]);

        $otherSession = ScheduleSession::query()
            ->where('chamber_id', $this->otherChamber->id)
            ->first();
        LiveSession::create([
            'schedule_session_id' => $otherSession->id,
            'session_date' => $this->today,
            'status' => 'active',
            'started_at' => now(),
        ]);

        tenancy()->end();

        $this->get($this->host.'/screen/chamber/'.$this->chamber->id)
            ->assertOk()
            ->assertSee('Panchlaish', false);

        $payload = $this->getJson($this->host.'/api/screen/chamber/'.$this->chamber->id)
            ->assertOk()
            ->json();

        $this->assertCount(2, $payload['rooms']);
        $labels = collect($payload['rooms'])->pluck('label')->all();
        $this->assertTrue(collect($labels)->contains(fn (string $label) => str_contains($label, 'Visit')));
        $this->assertTrue(collect($labels)->contains(fn (string $label) => str_contains($label, 'Intervention')));
        $this->assertFalse(collect($labels)->contains(fn (string $label) => str_contains($label, 'Other visit')));

        $visitRoom = collect($payload['rooms'])->first(
            fn (array $room) => $room['session_id'] === $this->visit->id
        );
        $this->assertTrue($visitRoom['is_called']);
        $this->assertSame(1, $visitRoom['now_serving']);
        $this->assertSame('Called one', $visitRoom['now_serving_name']);
        $this->assertCount(3, $visitRoom['next']);
        $this->assertSame([2, 3, 4], array_column($visitRoom['next'], 'serial'));
    }

    public function test_chamber_screen_is_live_queue_gated_and_tenant_isolated(): void
    {
        $this->tenant->update([
            'feature_flags' => array_merge($this->tenant->feature_flags ?? [], ['live_queue' => false]),
        ]);

        $this->get($this->host.'/screen/chamber/'.$this->chamber->id)->assertNotFound();

        $other = Tenant::create(['id' => 'chamber-tv-b', 'name' => 'Other']);
        Domain::create(['domain' => 'chamber-tv-b.localhost', 'tenant_id' => 'chamber-tv-b']);

        $this->get('http://chamber-tv-b.localhost/screen/chamber/'.$this->chamber->id)
            ->assertNotFound();
    }
}
