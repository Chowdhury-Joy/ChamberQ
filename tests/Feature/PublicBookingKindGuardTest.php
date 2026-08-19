<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Chamber;
use App\Models\Doctor;
use App\Models\Domain;
use App\Models\ScheduleSession;
use App\Models\Tenant;
use App\Services\StationsHandoffService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicBookingKindGuardTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private string $host;

    private string $monday;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'id' => 'kind-guard',
            'plan_tier' => 'clinic',
            'feature_flags' => Tenant::mergeStationsFlag([], true),
        ]);
        Domain::create(['domain' => 'kind-guard.localhost', 'tenant_id' => 'kind-guard']);
        $this->host = 'http://kind-guard.localhost';
        $this->monday = Carbon::now()->next(Carbon::MONDAY)->format('Y-m-d');
    }

    public function test_posting_an_intervention_sitting_to_the_public_wizard_is_rejected(): void
    {
        $intervention = $this->sitting(ScheduleSession::KIND_INTERVENTION, 'OT-ONLY-SECRET');

        $this->postJson($this->host.'/api/bookings', [
            'bookable_type' => 'session',
            'bookable_id' => $intervention->id,
            'booking_date' => $this->monday,
            'patient_name' => 'Fatima',
            'patient_phone' => '01712345678',
        ])->assertUnprocessable();

        $this->assertSame(0, Booking::query()->count());
    }

    public function test_posting_a_counseling_sitting_to_the_public_wizard_is_rejected(): void
    {
        $counseling = $this->sitting(ScheduleSession::KIND_COUNSELING, 'COUNSEL-ONLY');

        $this->postJson($this->host.'/api/bookings', [
            'bookable_type' => 'session',
            'bookable_id' => $counseling->id,
            'booking_date' => $this->monday,
            'patient_name' => 'Fatima',
            'patient_phone' => '01712345678',
        ])->assertUnprocessable();

        $this->assertSame(0, Booking::query()->count());
    }

    public function test_a_visit_sitting_still_books_online(): void
    {
        $visit = $this->sitting(ScheduleSession::KIND_VISIT, 'Visit');

        $this->postJson($this->host.'/api/bookings', [
            'bookable_type' => 'session',
            'bookable_id' => $visit->id,
            'booking_date' => $this->monday,
            'patient_name' => 'Fatima',
            'patient_phone' => '01712345678',
        ])->assertOk()->assertJsonPath('success', true);
    }

    public function test_the_wizard_does_not_list_intervention_or_counseling_sittings(): void
    {
        $this->sitting(ScheduleSession::KIND_VISIT, 'Visit desk');
        $this->sitting(ScheduleSession::KIND_INTERVENTION, 'OT-ONLY-SECRET');
        $this->sitting(ScheduleSession::KIND_COUNSELING, 'COUNSEL-ONLY');
        $this->sitting(ScheduleSession::KIND_MSK, 'MSK-ONLY');
        $this->sitting(ScheduleSession::KIND_REPORT, 'REPORT-ONLY');

        $this->get($this->host.'/book')
            ->assertOk()
            ->assertSee('Visit desk', false)
            ->assertDontSee('OT-ONLY-SECRET', false)
            ->assertDontSee('COUNSEL-ONLY', false)
            ->assertDontSee('MSK-ONLY', false)
            ->assertDontSee('REPORT-ONLY', false);
    }

    private function sitting(string $kind, string $name): ScheduleSession
    {
        tenancy()->initialize($this->tenant);

        $chamber = Chamber::query()->first() ?? Chamber::create(['name' => 'Main']);
        $doctor = Doctor::query()->first() ?? Doctor::create(['name' => 'Dr Guard']);

        $session = ScheduleSession::create([
            'chamber_id' => $chamber->id,
            'doctor_id' => $doctor->id,
            'day_of_week' => 1,
            'session_name' => $name,
            'kind' => $kind,
            'start_time' => '10:00',
            'end_time' => '12:00',
            'slot_cap' => 10,
        ]);

        tenancy()->end();

        return $session;
    }
}
