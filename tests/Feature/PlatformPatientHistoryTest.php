<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Chamber;
use App\Models\Condition;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\PatientAccount;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\ScheduleSession;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VisitRecord;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PlatformPatientHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        tenancy()->end();
        parent::tearDown();
    }

    public function test_history_shows_own_visits_even_when_share_is_off_and_hides_other_phones(): void
    {
        $clinicA = $this->clinic('hist-a', 'Clinic A');
        $clinicB = $this->clinic('hist-b', 'Clinic B');

        $mineA = $this->completedVisit($clinicA, 'Fatima', '01730000001', 'NAPA', share: false);
        $mineB = $this->completedVisit($clinicB, 'Fatima', '01730000001', 'OMEE', share: true);
        $this->completedVisit($clinicA, 'Other Person', '01730000099', 'SECLO', share: true, serial: 2);

        $account = PatientAccount::create([
            'phone' => '01730000001',
            'name' => 'Fatima',
        ]);

        $this->actingAs($account, 'patient')
            ->get('http://localhost/me/history')
            ->assertOk()
            ->assertSee('NAPA', escape: false)
            ->assertSee('OMEE', escape: false)
            ->assertDontSee('SECLO', escape: false)
            ->assertDontSee('Other Person', escape: false);

        $this->actingAs($account, 'patient')
            ->get('http://localhost/me/prescriptions/'.$mineA['prescription']->id)
            ->assertOk()
            ->assertSee('NAPA', escape: false);

        $this->actingAs($account, 'patient')
            ->get('http://localhost/me/prescriptions/'.$mineB['prescription']->id)
            ->assertOk()
            ->assertSee('OMEE', escape: false);
    }

    public function test_another_phone_cannot_open_this_prescription(): void
    {
        $clinic = $this->clinic('hist-c', 'Clinic C');
        $visit = $this->completedVisit($clinic, 'Fatima', '01730000001', 'NAPA', share: true);

        $stranger = PatientAccount::create([
            'phone' => '01730000088',
            'name' => 'Stranger',
        ]);

        $this->actingAs($stranger, 'patient')
            ->get('http://localhost/me/prescriptions/'.$visit['prescription']->id)
            ->assertNotFound();
    }

    /**
     * @return array{prescription: Prescription}
     */
    private function completedVisit(Tenant $tenant, string $name, string $phone, string $medicine, bool $share, int $serial = 1): array
    {
        tenancy()->initialize($tenant);

        $doctorUser = User::query()->first() ?? User::create([
            'name' => 'Dr '.$tenant->id,
            'email' => $tenant->id.'@hist.test',
            'password' => Hash::make('secret'),
            'role' => User::ROLE_DOCTOR,
            'tenant_id' => $tenant->id,
        ]);

        $session = ScheduleSession::query()->firstOrFail();
        $patient = Patient::create([
            'name' => $name,
            'phone' => $phone,
            'share_clinical_history' => $share,
        ]);
        $booking = Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $session->id,
            'booking_date' => today()->subDay(),
            'patient_id' => $patient->id,
            'patient_name' => $name,
            'patient_phone' => $phone,
            'serial_number' => $serial,
            'status' => 'completed',
            'completed_at' => now()->subDay(),
        ]);

        $condition = Condition::query()->first() ?? Condition::create([
            'code' => 'HIST-'.$tenant->id,
            'name' => 'Fever',
            'aliases' => [],
        ]);

        $visit = VisitRecord::create([
            'booking_id' => $booking->id,
            'patient_id' => $patient->id,
            'recorded_by' => $doctorUser->id,
            'condition_id' => $condition->id,
            'recorded_at' => now()->subDay(),
        ]);

        $prescription = Prescription::create([
            'visit_record_id' => $visit->id,
            'patient_id' => $patient->id,
            'prescribed_by' => $doctorUser->id,
        ]);
        PrescriptionItem::create([
            'prescription_id' => $prescription->id,
            'medicine_name' => $medicine,
            'dose' => '500 mg',
            'frequency' => '1+0+1',
            'duration' => '5 days',
            'sort_order' => 1,
        ]);

        tenancy()->end();

        return ['prescription' => $prescription];
    }

    private function clinic(string $id, string $name): Tenant
    {
        $tenant = Tenant::create([
            'id' => $id,
            'name' => $name,
            'plan_tier' => 'solo',
            'billing_status' => 'active',
        ]);

        tenancy()->initialize($tenant);
        User::create([
            'name' => 'Dr '.$name,
            'email' => $id.'@hist.test',
            'password' => Hash::make('secret'),
            'role' => User::ROLE_DOCTOR,
            'tenant_id' => $tenant->id,
        ]);
        $chamber = Chamber::create(['name' => 'Main']);
        $doctor = Doctor::create(['name' => 'Dr '.$name]);
        ScheduleSession::create([
            'chamber_id' => $chamber->id,
            'doctor_id' => $doctor->id,
            'day_of_week' => Carbon::today()->dayOfWeek,
            'session_name' => 'Evening',
            'start_time' => '18:00',
            'end_time' => '21:00',
            'slot_cap' => 20,
        ]);
        tenancy()->end();

        return $tenant;
    }
}
