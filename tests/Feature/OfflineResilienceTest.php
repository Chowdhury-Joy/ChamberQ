<?php

namespace Tests\Feature;

use App\Filament\TenantAdmin\Pages\VisitingDay;
use App\Models\Booking;
use App\Models\Chamber;
use App\Models\Doctor;
use App\Models\Domain;
use App\Models\Patient;
use App\Models\ScheduleSession;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VisitRecord;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class OfflineResilienceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $doctor;

    private User $staff;

    private ScheduleSession $session;

    private string $host;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'id' => 'offline-kit',
            'plan_tier' => 'solo',
            'queue_runner' => 'doctor',
        ]);
        Domain::create(['domain' => 'offline-kit.localhost', 'tenant_id' => $this->tenant->id]);
        $this->host = 'http://offline-kit.localhost';

        tenancy()->initialize($this->tenant);

        $this->doctor = User::create([
            'name' => 'Dr Offline',
            'email' => 'doc@offline.test',
            'password' => Hash::make('secret'),
            'role' => User::ROLE_DOCTOR,
            'tenant_id' => $this->tenant->id,
        ]);
        $this->staff = User::create([
            'name' => 'Staff Offline',
            'email' => 'staff@offline.test',
            'password' => Hash::make('secret'),
            'role' => User::ROLE_STAFF,
            'tenant_id' => $this->tenant->id,
        ]);

        $chamber = Chamber::create(['name' => 'Main', 'address' => 'Dhaka']);
        $doctorProfile = Doctor::create([
            'name' => 'Dr Offline',
            'user_id' => $this->doctor->id,
            'registration_number' => 'B-99887',
        ]);
        $this->session = ScheduleSession::create([
            'chamber_id' => $chamber->id,
            'doctor_id' => $doctorProfile->id,
            'day_of_week' => 0,
            'session_name' => 'Morning',
            'start_time' => '09:00',
            'end_time' => '12:00',
            'slot_cap' => 1,
        ]);

        tenancy()->end();
    }

    public function test_doctor_can_download_the_travel_bag(): void
    {
        tenancy()->initialize($this->tenant);

        $this->actingAs($this->doctor)
            ->getJson($this->host.'/api/offline/bag')
            ->assertOk()
            ->assertJsonPath('letterhead.doctor_name', 'Dr Offline')
            ->assertJsonPath('letterhead.registration_number', 'B-99887')
            ->assertJsonPath('sessions.0.chamber_name', 'Main')
            ->assertJsonStructure(['packed_at', 'packs', 'my_medicines', 'sessions', 'patients', 'letterhead']);

        tenancy()->end();
    }

    public function test_staff_cannot_download_or_sync_the_bag(): void
    {
        $this->actingAs($this->staff)
            ->getJson($this->host.'/api/offline/bag')
            ->assertForbidden();

        $this->actingAs($this->staff)
            ->postJson($this->host.'/api/offline/sync', ['items' => []])
            ->assertForbidden();
    }

    public function test_rx_save_writes_notes_without_completing_or_calling_next(): void
    {
        tenancy()->initialize($this->tenant);

        $patient = Patient::create(['name' => 'Fatima', 'phone' => '01710000001']);
        $booking = Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $this->session->id,
            'booking_date' => today()->toDateString(),
            'patient_id' => $patient->id,
            'patient_name' => $patient->name,
            'patient_phone' => $patient->phone,
            'serial_number' => 1,
            'status' => 'in_chamber',
            'in_chamber_at' => now(),
        ]);

        $syncId = '11111111-1111-4111-8111-111111111111';

        $this->actingAs($this->doctor)
            ->postJson($this->host.'/api/offline/sync', [
                'items' => [[
                    'id' => $syncId,
                    'type' => 'rx_save',
                    'booking_id' => $booking->id,
                    'data' => [
                        'diagnosis' => '__free__:IHD',
                        'prescription_items' => [[
                            'medicine_name' => 'NAPA',
                            'dose' => '500 mg',
                            'frequency' => '1+0+1',
                            'duration' => '5 days',
                        ]],
                    ],
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('results.0.ok', true);

        $booking->refresh();
        $this->assertSame('in_chamber', $booking->status);
        $this->assertNull($booking->completed_at);

        $visit = VisitRecord::query()->where('booking_id', $booking->id)->first();
        $this->assertNotNull($visit);
        $this->assertSame($syncId, $visit->offline_sync_id);
        $this->assertSame('IHD', $visit->diagnosis_uncoded);
        $this->assertSame('NAPA', $visit->prescription->items->first()->medicine_name);

        tenancy()->end();
    }

    public function test_visiting_visit_creates_a_completed_record_even_when_the_session_is_full_on_the_wrong_weekday(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-12 11:00:00')); // Wednesday
        tenancy()->initialize($this->tenant);

        $seated = Patient::create(['name' => 'Already Seated', 'phone' => '01710000002']);
        Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $this->session->id,
            'booking_date' => '2026-08-12',
            'patient_id' => $seated->id,
            'patient_name' => $seated->name,
            'patient_phone' => $seated->phone,
            'serial_number' => 1,
            'status' => 'waiting',
        ]);

        $syncId = '22222222-2222-4222-8222-222222222222';

        $this->actingAs($this->doctor)
            ->postJson($this->host.'/api/offline/sync', [
                'items' => [[
                    'id' => $syncId,
                    'type' => 'visiting_visit',
                    'schedule_session_id' => $this->session->id,
                    'patient_name' => 'Karim Camp',
                    'patient_phone' => '01710000003',
                    'visit_date' => '2026-08-12',
                    'data' => [
                        'diagnosis' => '__free__:Fever',
                        'prescription_items' => [[
                            'medicine_name' => 'NAPA',
                            'dose' => '500 mg',
                            'frequency' => '1+0+1',
                            'duration' => '3 days',
                        ]],
                    ],
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('results.0.ok', true);

        $booking = Booking::query()->where('patient_phone', '01710000003')->first();
        $this->assertNotNull($booking);
        $this->assertSame('completed', $booking->status);
        $this->assertSame(2, $booking->serial_number);
        $this->assertSame('Fever', $booking->visitRecord->diagnosis_uncoded);

        $this->actingAs($this->doctor)
            ->postJson($this->host.'/api/offline/sync', [
                'items' => [[
                    'id' => $syncId,
                    'type' => 'visiting_visit',
                    'schedule_session_id' => $this->session->id,
                    'patient_name' => 'Karim Camp',
                    'patient_phone' => '01710000003',
                    'visit_date' => '2026-08-12',
                    'data' => [
                        'diagnosis' => '__free__:Should not overwrite',
                    ],
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('results.0.ok', true);

        $this->assertSame('Fever', $booking->fresh()->visitRecord->diagnosis_uncoded);
        $this->assertSame(1, VisitRecord::query()->where('offline_sync_id', $syncId)->count());

        Carbon::setTestNow();
        tenancy()->end();
    }

    public function test_sync_rejects_queue_mutations(): void
    {
        $this->actingAs($this->doctor)
            ->postJson($this->host.'/api/offline/sync', [
                'items' => [[
                    'id' => '33333333-3333-4333-8333-333333333333',
                    'type' => 'call_next',
                ]],
            ])
            ->assertUnprocessable();
    }

    public function test_doctors_can_open_visiting_day_and_staff_cannot(): void
    {
        tenancy()->initialize($this->tenant);
        Filament::setCurrentPanel(Filament::getPanel('tenantAdmin'));

        $this->actingAs($this->doctor);
        Livewire::test(VisitingDay::class)
            ->assertOk()
            ->assertSee('Pack the bag before you leave');

        $this->actingAs($this->staff);
        Livewire::test(VisitingDay::class)->assertForbidden();

        tenancy()->end();
    }

    public function test_prescription_module_off_hides_offline_routes(): void
    {
        $this->tenant->update([
            'feature_flags' => Tenant::featureFlagsWithModules([], [
                Tenant::MODULE_FRONT_DOOR,
            ]),
        ]);

        $this->actingAs($this->doctor)
            ->getJson($this->host.'/api/offline/bag')
            ->assertNotFound();
    }
}
