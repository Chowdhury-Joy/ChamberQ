<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Chamber;
use App\Models\Doctor;
use App\Models\Domain;
use App\Models\LiveSession;
use App\Models\Patient;
use App\Models\ScheduleSession;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VisitRecord;
use App\Services\VisitMediaService;
use App\Services\VisitRecordService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PatientRecordsStage4DeferredTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $doctor;

    private User $staff;

    private Patient $patient;

    private Booking $completedBooking;

  protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->tenant = Tenant::create(['id' => 'stage4-deferred', 'plan_tier' => 'solo']);
        Domain::create(['domain' => 'stage4-deferred.localhost', 'tenant_id' => $this->tenant->id]);

        tenancy()->initialize($this->tenant);

        $this->doctor = User::create([
            'name' => 'Dr Deferred',
            'email' => 'doc@deferred.test',
            'password' => Hash::make('secret'),
            'role' => User::ROLE_DOCTOR,
            'tenant_id' => $this->tenant->id,
        ]);

        $this->staff = User::create([
            'name' => 'Staff',
            'email' => 'staff@deferred.test',
            'password' => Hash::make('secret'),
            'role' => User::ROLE_STAFF,
            'tenant_id' => $this->tenant->id,
        ]);

        $chamber = Chamber::create(['name' => 'Main', 'address' => 'Dhaka']);
        $doctorProfile = Doctor::create([
            'name' => 'Dr Deferred',
            'qualifications' => 'MBBS',
            'registration_number' => 'A-99999',
        ]);
        $session = ScheduleSession::create([
            'chamber_id' => $chamber->id,
            'doctor_id' => $doctorProfile->id,
            'day_of_week' => Carbon::today()->dayOfWeek,
            'session_name' => 'Evening',
            'start_time' => '18:00',
            'end_time' => '21:00',
            'slot_cap' => 20,
        ]);

        $this->patient = Patient::create([
            'name' => 'Rahim Ali',
            'phone' => '01799887766',
            'age' => 35,
            'age_recorded_at' => today(),
            'sex' => 'male',
        ]);

        $this->completedBooking = Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $session->id,
            'booking_date' => today(),
            'patient_id' => $this->patient->id,
            'patient_name' => $this->patient->name,
            'patient_phone' => $this->patient->phone,
            'serial_number' => 1,
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        tenancy()->end();
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    public function test_doctor_can_save_voice_note_and_play_route_is_authorized(): void
    {
        tenancy()->initialize($this->tenant);

        $voicePath = 'visit-audio/stage4-deferred/test-voice.webm';
        Storage::disk('local')->put($voicePath, 'fake-audio');

        $record = app(VisitRecordService::class)->saveForCompletedBooking($this->completedBooking, $this->doctor, [
            'voice_path' => $voicePath,
            'voice_transcript' => 'Patient had gastric pain',
        ]);

        $this->assertNotNull($record);
        $this->assertSame($voicePath, $record->voice_path);
        $this->assertNull($record->condition_id);

        $voiceUrl = 'http://stage4-deferred.localhost/visit-records/'.$record->id.'/voice';

        $this->actingAs($this->doctor)->get($voiceUrl)->assertOk();
        $this->actingAs($this->staff)->get($voiceUrl)->assertForbidden();
        $this->get($voiceUrl)->assertForbidden();
    }

    public function test_doctor_can_upload_voice_via_api(): void
    {
        tenancy()->initialize($this->tenant);

        $file = UploadedFile::fake()->create('note.webm', 100, 'audio/webm');

        $response = $this->actingAs($this->doctor)
            ->post('http://stage4-deferred.localhost/api/visit-media/upload-voice', [
                'voice' => $file,
            ]);

        $response->assertOk()->assertJsonStructure(['path']);
        Storage::disk('local')->assertExists($response->json('path'));

        $this->actingAs($this->staff)
            ->post('http://stage4-deferred.localhost/api/visit-media/upload-voice', [
                'voice' => $file,
            ])
            ->assertForbidden();
    }

    public function test_photo_upload_stores_and_doctor_can_view(): void
    {
        tenancy()->initialize($this->tenant);

        $photoPath = 'visit-photos/stage4-deferred/prescription.jpg';
        Storage::disk('local')->put($photoPath, 'fake-image');

        $record = app(VisitRecordService::class)->saveForCompletedBooking($this->completedBooking, $this->doctor, [
            'prescription_photo' => $photoPath,
        ]);

        $this->assertNotNull($record);
        $this->assertSame($photoPath, $record->photo_path);

        $photoUrl = 'http://stage4-deferred.localhost/visit-records/'.$record->id.'/photo';

        $this->actingAs($this->doctor)->get($photoUrl)->assertOk();
        $this->actingAs($this->staff)->get($photoUrl)->assertForbidden();
    }

    public function test_doctor_can_upload_and_view_report_photos_staff_cannot(): void
    {
        tenancy()->initialize($this->tenant);

        $file = UploadedFile::fake()->image('cbc.jpg');

        $response = $this->actingAs($this->doctor)
            ->post('http://stage4-deferred.localhost/api/visit-media/upload-report-photo', [
                'photo' => $file,
            ]);

        $response->assertOk()->assertJsonStructure(['path']);
        $path = $response->json('path');
        $this->assertStringStartsWith('visit-reports/stage4-deferred/', $path);
        Storage::disk('local')->assertExists($path);

        $this->actingAs($this->staff)
            ->post('http://stage4-deferred.localhost/api/visit-media/upload-report-photo', [
                'photo' => $file,
            ])
            ->assertForbidden();

        $record = app(VisitRecordService::class)->saveForCompletedBooking($this->completedBooking, $this->doctor, [
            'report_photos' => [$path],
        ]);

        $url = 'http://stage4-deferred.localhost/visit-records/'.$record->id.'/report-photos/0';

        $this->actingAs($this->doctor)->get($url)->assertOk();
        $this->actingAs($this->staff)->get($url)->assertForbidden();
        $this->get($url)->assertForbidden();
    }

    public function test_catch_up_counts_completed_bookings_without_notes_today(): void
    {
        tenancy()->initialize($this->tenant);

        $session = ScheduleSession::query()->first();
        $liveSession = LiveSession::create([
            'schedule_session_id' => $session->id,
            'session_date' => Carbon::today(),
            'status' => 'active',
            'started_at' => now(),
        ]);

        Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $session->id,
            'booking_date' => today(),
            'patient_id' => $this->patient->id,
            'patient_name' => 'Another',
            'patient_phone' => '01711112222',
            'serial_number' => 2,
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $service = app(VisitRecordService::class);
        $this->assertSame(2, $service->countCompletedBookingsWithoutNotesToday($liveSession));

        VisitRecord::create([
            'tenant_id' => $this->tenant->id,
            'booking_id' => $this->completedBooking->id,
            'patient_id' => $this->patient->id,
            'recorded_by' => $this->doctor->id,
            'voice_path' => 'visit-audio/stage4-deferred/one.webm',
            'recorded_at' => now(),
        ]);

        $this->assertSame(1, $service->countCompletedBookingsWithoutNotesToday($liveSession));
    }

    public function test_staff_cannot_access_voice_transcript_fields_via_service(): void
    {
        tenancy()->initialize($this->tenant);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        app(VisitRecordService::class)->saveForCompletedBooking($this->completedBooking, $this->staff, [
            'voice_transcript' => 'Should fail',
        ]);
    }

    public function test_ticket_and_portal_do_not_expose_visit_media(): void
    {
        tenancy()->initialize($this->tenant);

        $voicePath = 'visit-audio/stage4-deferred/secret.webm';
        Storage::disk('local')->put($voicePath, 'secret-audio');

        VisitRecord::create([
            'tenant_id' => $this->tenant->id,
            'booking_id' => $this->completedBooking->id,
            'patient_id' => $this->patient->id,
            'recorded_by' => $this->doctor->id,
            'voice_path' => $voicePath,
            'voice_transcript' => 'Secret transcript',
            'photo_path' => 'visit-photos/stage4-deferred/secret.jpg',
            'recorded_at' => now(),
        ]);

        $ticketUrl = 'http://stage4-deferred.localhost/bookings/'.$this->completedBooking->id;

        $this->get($ticketUrl)
            ->assertOk()
            ->assertDontSee('Secret transcript')
            ->assertDontSee('visit-audio')
            ->assertDontSee('visit-photos');

        $portalUrl = 'http://stage4-deferred.localhost/portal?phone=01799887766';

        $this->get($portalUrl)
            ->assertOk()
            ->assertDontSee('Secret transcript')
            ->assertDontSee('visit-audio');
    }

    public function test_voice_transcript_does_not_set_coded_diagnosis(): void
    {
        tenancy()->initialize($this->tenant);

        $record = app(VisitRecordService::class)->saveForCompletedBooking($this->completedBooking, $this->doctor, [
            'voice_transcript' => 'gastric problem, omeprazole দিলাম',
        ]);

        $this->assertNotNull($record);
        $this->assertNull($record->condition_id);
        $this->assertNull($record->diagnosis_uncoded);
        $this->assertSame('gastric problem, omeprazole দিলাম', $record->voice_transcript);
    }

    public function test_visit_media_service_stores_uploaded_voice_file(): void
    {
        tenancy()->initialize($this->tenant);

        $file = UploadedFile::fake()->create('clip.webm', 50, 'audio/webm');
        $path = app(VisitMediaService::class)->storeVoiceUpload($file);

        $this->assertStringStartsWith('visit-audio/stage4-deferred/', $path);
        Storage::disk('local')->assertExists($path);
    }

    public function test_saving_notes_rejects_another_chambers_media_path_and_does_not_delete_it(): void
    {
        tenancy()->initialize($this->tenant);

        $foreign = 'visit-audio/other-chamber/secret.webm';
        Storage::disk('local')->put($foreign, 'do-not-delete');

        $own = 'visit-audio/stage4-deferred/keep.webm';
        Storage::disk('local')->put($own, 'ours');

        $existing = VisitRecord::create([
            'booking_id' => $this->completedBooking->id,
            'patient_id' => $this->patient->id,
            'recorded_by' => $this->doctor->id,
            'voice_path' => $own,
            'recorded_at' => now(),
        ]);

        $record = app(VisitRecordService::class)->saveForCompletedBooking(
            $this->completedBooking->fresh(),
            $this->doctor,
            [
                'voice_path' => $foreign,
                'advice' => 'Keep the old note',
            ],
        );

        $this->assertSame($own, $record->voice_path);
        Storage::disk('local')->assertExists($foreign);
        Storage::disk('local')->assertExists($own);
        $this->assertSame($existing->id, $record->id);
    }
}
