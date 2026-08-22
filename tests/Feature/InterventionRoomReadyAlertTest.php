<?php

namespace Tests\Feature;

use App\Filament\TenantAdmin\Pages\ConsultScreen;
use App\Models\Booking;
use App\Models\Chamber;
use App\Models\Doctor;
use App\Models\Domain;
use App\Models\FeeCatalogItem;
use App\Models\LiveSession;
use App\Models\Patient;
use App\Models\ScheduleSession;
use App\Models\Tenant;
use App\Models\User;
use App\Services\StationsHandoffService;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * OT marks the room prepped; the doctor, still in the regular visit chamber,
 * gets a four-second card and hears it read out.
 */
class InterventionRoomReadyAlertTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $doctorUser;

    private Doctor $doctor;

    private Chamber $chamber;

    private ScheduleSession $visitSitting;

    private ScheduleSession $otSitting;

    private Booking $visitBooking;

    private Booking $procedure;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'id' => 'ot-ready',
            'plan_tier' => 'clinic',
            'feature_flags' => Tenant::mergeStationsFlag([], true),
        ]);
        Domain::create(['domain' => 'ot-ready.localhost', 'tenant_id' => $this->tenant->id]);

        tenancy()->initialize($this->tenant);

        $this->doctorUser = User::create([
            'name' => 'Dr Pain',
            'email' => 'doc@ot-ready.test',
            'password' => Hash::make('secret'),
            'role' => User::ROLE_DOCTOR,
            'tenant_id' => $this->tenant->id,
        ]);

        $this->chamber = Chamber::create(['name' => 'Main']);
        $this->doctor = Doctor::create(['name' => 'Dr Pain']);

        $this->visitSitting = ScheduleSession::create([
            'chamber_id' => $this->chamber->id,
            'doctor_id' => $this->doctor->id,
            'day_of_week' => Carbon::today()->dayOfWeek,
            'session_name' => 'Morning',
            'kind' => ScheduleSession::KIND_VISIT,
            'start_time' => '09:00',
            'end_time' => '21:00',
            'slot_cap' => 20,
        ]);

        $this->otSitting = ScheduleSession::create([
            'chamber_id' => $this->chamber->id,
            'doctor_id' => $this->doctor->id,
            'day_of_week' => Carbon::today()->dayOfWeek,
            'session_name' => 'Intervention',
            'kind' => ScheduleSession::KIND_INTERVENTION,
            'start_time' => '09:00',
            'end_time' => '21:00',
            'slot_cap' => 20,
        ]);

        $patient = Patient::create(['name' => 'Fatima Rahman', 'phone' => '01711112222']);

        $this->visitBooking = Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $this->visitSitting->id,
            'booking_date' => Carbon::today(),
            'patient_id' => $patient->id,
            'patient_name' => $patient->name,
            'patient_phone' => $patient->phone,
            'serial_number' => 1,
            'status' => 'in_chamber',
        ]);

        $epidural = FeeCatalogItem::create([
            'label' => 'Epidural steroid injection',
            'list_price_taka' => 6000,
            'sitting_kind' => ScheduleSession::KIND_INTERVENTION,
            'is_active' => true,
        ]);

        $this->procedure = Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $this->otSitting->id,
            'booking_date' => Carbon::today(),
            'patient_id' => $patient->id,
            'patient_name' => $patient->name,
            'patient_phone' => $patient->phone,
            'serial_number' => 4,
            'status' => 'waiting',
            'procedure_status' => Booking::PROCEDURE_LOGGED,
            'fee_catalog_item_id' => $epidural->id,
        ]);

        LiveSession::create([
            'schedule_session_id' => $this->visitSitting->id,
            'session_date' => Carbon::today(),
            'status' => 'active',
            'started_at' => now(),
            'current_booking_id' => $this->visitBooking->id,
        ]);
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    private function alerts(): array
    {
        return (new ConsultScreen)->getPreppedInterventionAlertsProperty();
    }

    private function markPrepped(): void
    {
        app(StationsHandoffService::class)->advanceProcedureStatus(
            $this->procedure,
            Booking::PROCEDURE_PREPPED,
        );
    }

    public function test_nothing_is_announced_before_ot_marks_the_room_prepped(): void
    {
        $this->assertSame([], $this->alerts());
    }

    public function test_prepped_room_names_the_serial_and_the_procedure(): void
    {
        $this->markPrepped();

        $alerts = $this->alerts();

        $this->assertCount(1, $alerts);
        $this->assertSame(4, $alerts[0]['number']);
        $this->assertSame('Epidural steroid injection', $alerts[0]['procedure']);
        $this->assertSame('Fatima Rahman', $alerts[0]['patient']);
        $this->assertSame(
            'Intervention room is prepared for patient number 4, for Epidural steroid injection.',
            $alerts[0]['speech'],
        );
    }

    public function test_marking_prepped_stamps_the_time_and_moving_on_clears_it(): void
    {
        $this->markPrepped();
        $this->assertNotNull($this->procedure->fresh()->procedure_prepped_at);

        app(StationsHandoffService::class)->advanceProcedureStatus(
            $this->procedure,
            Booking::PROCEDURE_DOCTOR_CALLED,
        );

        $this->assertNull($this->procedure->fresh()->procedure_prepped_at);
        $this->assertSame([], $this->alerts());
    }

    public function test_a_stale_prep_is_not_announced(): void
    {
        $this->markPrepped();
        $this->procedure->forceFill(['procedure_prepped_at' => now()->subMinutes(30)])->save();

        $this->assertSame([], $this->alerts());
    }

    public function test_a_cancelled_procedure_is_not_announced(): void
    {
        $this->markPrepped();
        $this->procedure->forceFill(['status' => 'cancelled'])->save();

        $this->assertSame([], $this->alerts());
    }

    public function test_another_doctors_ot_does_not_interrupt_this_consult(): void
    {
        $otherDoctor = Doctor::create(['name' => 'Dr Other']);
        $otherOt = ScheduleSession::create([
            'chamber_id' => $this->chamber->id,
            'doctor_id' => $otherDoctor->id,
            'day_of_week' => Carbon::today()->dayOfWeek,
            'session_name' => 'Intervention',
            'kind' => ScheduleSession::KIND_INTERVENTION,
            'start_time' => '09:00',
            'end_time' => '21:00',
            'slot_cap' => 20,
        ]);

        $this->procedure->forceFill(['bookable_id' => $otherOt->id])->save();
        $this->markPrepped();

        $this->assertSame([], $this->alerts());
    }

    public function test_the_doctor_already_in_the_ot_is_not_announced_to(): void
    {
        LiveSession::query()->delete();
        LiveSession::create([
            'schedule_session_id' => $this->otSitting->id,
            'session_date' => Carbon::today(),
            'status' => 'active',
            'started_at' => now(),
        ]);

        $this->markPrepped();

        $this->assertSame([], $this->alerts());
    }

    public function test_the_consult_screen_renders_the_spoken_line(): void
    {
        $this->markPrepped();

        Filament::setCurrentPanel(Filament::getPanel('tenantAdmin'));
        $this->actingAs($this->doctorUser);

        $html = Livewire::test(ConsultScreen::class)->html();

        $this->assertStringContainsString('chamberq-intervention-ready', $html);
        $this->assertStringContainsString('Intervention room is prepared for patient number 4', $html);
    }
}
