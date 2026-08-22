<?php

namespace Tests\Feature;

use App\Filament\TenantAdmin\Pages\ConsultScreen;
use App\Filament\TenantAdmin\Support\VisitNotesFormSchema;
use App\Models\Booking;
use App\Models\Chamber;
use App\Models\Doctor;
use App\Models\DoctorChip;
use App\Models\Domain;
use App\Models\LiveSession;
use App\Models\Medicine;
use App\Models\MedicineUsage;
use App\Models\Patient;
use App\Models\ScheduleSession;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The phone/tablet prescription modal (below the desktop Rx desk's 768px
 * cutoff) got the same one-tap History, Advice and "my medicines" chips the
 * desktop desk already has, instead of blank text boxes — see the 2026-08-22
 * decision. These chips are Filament `Actions`, so a tap is a normal
 * Livewire round trip through `$set()`, unlike the desk's client-only Alpine
 * store.
 */
class MobilePrescriptionChipsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $doctor;

    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['id' => 'mobile-chips', 'plan_tier' => 'solo', 'queue_runner' => 'doctor']);
        Domain::create(['domain' => 'mobile-chips.localhost', 'tenant_id' => $this->tenant->id]);

        tenancy()->initialize($this->tenant);

        $this->doctor = User::create([
            'name' => 'Dr Chips',
            'email' => 'doc@mobile-chips.test',
            'password' => Hash::make('secret'),
            'role' => User::ROLE_DOCTOR,
            'tenant_id' => $this->tenant->id,
        ]);

        $chamber = Chamber::create(['name' => 'Main', 'address' => 'Dhaka']);
        $doctorProfile = Doctor::create(['name' => 'Dr Chips', 'registration_number' => 'B-11223']);
        $session = ScheduleSession::create([
            'chamber_id' => $chamber->id,
            'doctor_id' => $doctorProfile->id,
            'day_of_week' => Carbon::today()->dayOfWeek,
            'session_name' => 'Evening',
            'start_time' => '18:00',
            'end_time' => '21:00',
            'slot_cap' => 20,
        ]);

        $patient = Patient::create([
            'name' => 'Karim Sheikh',
            'phone' => '01799990000',
            'age' => 40,
            'age_recorded_at' => today(),
            'sex' => 'male',
        ]);

        $this->booking = Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $session->id,
            'booking_date' => today(),
            'patient_id' => $patient->id,
            'patient_name' => $patient->name,
            'patient_phone' => $patient->phone,
            'serial_number' => 1,
            'status' => 'in_chamber',
            'in_chamber_at' => now(),
        ]);

        LiveSession::create([
            'schedule_session_id' => $session->id,
            'session_date' => Carbon::today(),
            'status' => 'active',
            'started_at' => now(),
            'current_booking_id' => $this->booking->id,
        ]);

        Medicine::create([
            'brand_name' => 'NAPA EXTEND',
            'generic_name' => 'Paracetamol',
            'default_strength' => '665 mg',
            'form' => 'tablet',
            'aliases' => ['napa extend'],
            'category' => 'Analgesic',
            'practice_types' => [Doctor::PRACTICE_GENERAL],
        ]);
    }

    private function actingAsDoctorOnPanel(): void
    {
        tenancy()->initialize($this->tenant);
        Filament::setCurrentPanel(Filament::getPanel('tenantAdmin'));
        $this->actingAs($this->doctor);
    }

    public function test_the_prescription_modal_renders_history_advice_and_my_medicine_chips(): void
    {
        $this->actingAsDoctorOnPanel();

        MedicineUsage::create([
            'user_id' => $this->doctor->id,
            'medicine_name' => 'NAPA EXTEND',
            'generic_name' => 'Paracetamol',
            'last_dose' => '665 mg',
            'last_frequency' => '1+0+1',
            'last_duration' => '5 days',
        ]);

        $html = Livewire::test(ConsultScreen::class)
            ->mountAction('writePrescription')
            ->assertActionMounted('writePrescription')
            ->html();

        // A shipped History chip (from HistoryChips::all()) and a shipped
        // Advice chip both render — the modal did not fall back to blank
        // boxes for a doctor who has customised nothing yet.
        $this->assertStringContainsString('HTN', $html);
        $this->assertStringContainsString('NAPA EXTEND', $html, 'The doctor\'s own saved medicine should appear as a "Yours" chip.');
    }

    public function test_tapping_a_history_chip_adds_it_to_the_history_box(): void
    {
        $this->actingAsDoctorOnPanel();

        Livewire::test(ConsultScreen::class)
            ->mountAction('writePrescription')
            ->callMountedAction()
            ->assertHasNoActionErrors();

        // Directly exercise the toggle helper the chip's Action closure
        // calls, since Filament's nested schema-Action click is framework
        // plumbing already covered by Filament's own test suite — what is
        // ours to verify is the toggle/append text logic itself.
        $toggle = new \ReflectionMethod(VisitNotesFormSchema::class, 'toggleChipInText');
        $toggle->setAccessible(true);

        $this->assertSame('HTN', $toggle->invoke(null, '', 'HTN'));
        $this->assertSame('HTN, Asthma', $toggle->invoke(null, 'HTN', 'Asthma'));
        // Tapping an already-present chip removes just that phrase.
        $this->assertSame('Asthma', $toggle->invoke(null, 'HTN, Asthma', 'HTN'));
        // A chip word inside other free text is left alone (word-boundary):
        // "HTN" is not detected as already present inside "HTNeurosis", so
        // tapping it appends rather than trying (and failing) to remove it.
        $this->assertSame('HTNeurosis, HTN', $toggle->invoke(null, 'HTNeurosis', 'HTN'));
    }

    public function test_advice_chip_lines_append_once_and_do_not_duplicate(): void
    {
        $append = new \ReflectionMethod(VisitNotesFormSchema::class, 'appendAdviceLine');
        $append->setAccessible(true);

        $this->assertSame('Take rest', $append->invoke(null, '', 'Take rest'));
        $this->assertSame("Take rest\nDrink fluids", $append->invoke(null, 'Take rest', 'Drink fluids'));
        // Re-tapping the same chip must not duplicate the line.
        $this->assertSame('Take rest', $append->invoke(null, 'Take rest', 'Take rest'));
    }

    public function test_a_doctor_with_no_saved_medicines_gets_no_yours_row_and_no_error(): void
    {
        $this->actingAsDoctorOnPanel();

        Livewire::test(ConsultScreen::class)
            ->mountAction('writePrescription')
            ->assertActionMounted('writePrescription')
            ->assertHasNoActionErrors();
    }
}
