<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Chamber;
use App\Models\Doctor;
use App\Models\Domain;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\ScheduleSession;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VisitRecord;
use App\Services\VisitMediaService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Consultation voice notes and prescription photos must never be fetchable
 * without a doctor login. They previously sat on the `public` disk, which is
 * symlinked into the web root and served directly by the web server — the
 * authenticated controller was real but bypassable by anyone holding the URL.
 *
 * These tests assert the property, not the implementation: whatever disk is
 * chosen, the bytes must not be reachable over HTTP.
 */
class ClinicalMediaPrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_clinical_media_is_not_written_inside_the_web_root(): void
    {
        $root = realpath(base_path());
        $webRoot = realpath(public_path());

        $probe = 'visit-audio/probe-tenant/'.uniqid().'.webm';
        Storage::disk('local')->put($probe, 'consultation-audio');

        $absolute = realpath(Storage::disk('local')->path($probe));

        $this->assertNotFalse($absolute);
        $this->assertStringStartsWith($root, $absolute);
        $this->assertStringNotContainsString(
            $webRoot.DIRECTORY_SEPARATOR,
            $absolute,
            'Clinical media was written inside the public web root and is served without auth.'
        );

        Storage::disk('local')->delete($probe);
    }

    public function test_clinical_media_is_not_served_over_http(): void
    {
        $probe = 'visit-photos/probe-tenant/'.uniqid().'.jpg';
        Storage::disk('local')->put($probe, 'prescription-photo');

        // Laravel registers a /storage/{path} route for the local disk; it must
        // refuse private files rather than hand them out.
        $this->get('/storage/'.$probe)->assertForbidden();

        Storage::disk('local')->delete($probe);
    }

    public function test_prescription_photo_directory_matches_private_form_upload(): void
    {
        tenancy()->initialize(Tenant::create([
            'id' => 'photo-dir-test',
            'plan_tier' => 'solo',
        ]));

        $directory = app(VisitMediaService::class)->photoDirectory();

        $this->assertSame('visit-photos/photo-dir-test', $directory);

        $probe = $directory.'/form-upload.jpg';
        Storage::disk('local')->put($probe, 'prescription-photo');

        $absolute = realpath(Storage::disk('local')->path($probe));
        $webRoot = realpath(public_path());

        $this->assertNotFalse($absolute);
        $this->assertStringNotContainsString(
            $webRoot.DIRECTORY_SEPARATOR,
            $absolute,
            'Form prescription photos must not land in the public web root.'
        );

        $this->get('/storage/'.$probe)->assertForbidden();

        Storage::disk('local')->delete($probe);

        tenancy()->end();
    }

    /**
     * All panels share one host and therefore one session cookie, so a doctor
     * signed in at one practice stays authenticated while requesting another
     * practice's tenant routes. Role alone is not authorisation here.
     */
    public function test_a_doctor_from_another_practice_cannot_read_clinical_media(): void
    {
        [$ownTenant, $ownDoctor, $visitRecord] = $this->seedPracticeWithVisitRecord('clinic-a');
        [, $outsideDoctor] = $this->seedPracticeWithVisitRecord('clinic-b');

        // The practice's own doctor is fine.
        $this->actingAs($ownDoctor)
            ->get("http://clinic-a.localhost/visit-records/{$visitRecord->id}/photo")
            ->assertOk();

        // A doctor of clinic-b holding the same URL must not be.
        $this->actingAs($outsideDoctor)
            ->get("http://clinic-a.localhost/visit-records/{$visitRecord->id}/photo")
            ->assertForbidden();

        $this->actingAs($outsideDoctor)
            ->get("http://clinic-a.localhost/visit-records/{$visitRecord->id}/voice")
            ->assertForbidden();

        $this->actingAs($ownDoctor)
            ->get("http://clinic-a.localhost/visit-records/{$visitRecord->id}/report-photos/0")
            ->assertOk();

        $this->actingAs($outsideDoctor)
            ->get("http://clinic-a.localhost/visit-records/{$visitRecord->id}/report-photos/0")
            ->assertForbidden();
    }

    /**
     * Defence in depth, not a live hole.
     *
     * `FilesystemTenancyBootstrapper` already suffixes the `local` disk per
     * tenant, so a path naming another chamber's folder resolves inside the
     * *viewing* chamber's own root and simply is not there. This asserts the
     * stored-path contract directly instead: these columns are written from
     * browser form state, so the service must reject anything that is not under
     * this practice's own directory — which is what keeps the rule true if the
     * disk is ever moved to S3, where that per-tenant suffixing does not apply
     * the same way.
     */
    public function test_the_service_refuses_media_paths_outside_this_practice(): void
    {
        tenancy()->initialize(Tenant::create([
            'id' => 'own-practice',
            'plan_tier' => 'solo',
        ]));

        $media = app(VisitMediaService::class);

        $this->assertTrue($media->isOwnedVoicePath('visit-audio/own-practice/note.webm'));
        $this->assertTrue($media->isOwnedPhotoPath('visit-photos/own-practice/slip.jpg'));
        $this->assertTrue($media->isOwnedReportPhotoPath('visit-reports/own-practice/cbc.jpg'));

        $this->assertFalse($media->isOwnedVoicePath('visit-audio/other-practice/note.webm'));
        $this->assertFalse($media->isOwnedPhotoPath('visit-photos/other-practice/slip.jpg'));
        $this->assertFalse($media->isOwnedReportPhotoPath('visit-reports/other-practice/cbc.jpg'));

        // A prefix collision must not read as ownership.
        $this->assertFalse($media->isOwnedVoicePath('visit-audio/own-practice-evil/note.webm'));

        $this->assertFalse($media->isOwnedVoicePath('visit-audio/own-practice/../../../.env'));
        $this->assertFalse($media->isOwnedVoicePath(null));
        $this->assertFalse($media->isOwnedVoicePath(''));

        tenancy()->end();
    }

    public function test_a_traversing_media_path_is_refused(): void
    {
        [$ownTenant, $ownDoctor, $visitRecord] = $this->seedPracticeWithVisitRecord('trav-a');

        tenancy()->initialize($ownTenant);
        $visitRecord->forceFill([
            'voice_path' => 'visit-audio/trav-a/../../../.env',
        ])->save();
        tenancy()->end();

        $this->actingAs($ownDoctor)
            ->get("http://trav-a.localhost/visit-records/{$visitRecord->id}/voice")
            ->assertNotFound();
    }

    public function test_report_photo_directory_is_private(): void
    {
        tenancy()->initialize(Tenant::create([
            'id' => 'report-dir-test',
            'plan_tier' => 'solo',
        ]));

        $directory = app(VisitMediaService::class)->reportPhotoDirectory();

        $this->assertSame('visit-reports/report-dir-test', $directory);

        $probe = $directory.'/cbc.jpg';
        Storage::disk('local')->put($probe, 'lab-report');

        $absolute = realpath(Storage::disk('local')->path($probe));
        $webRoot = realpath(public_path());

        $this->assertNotFalse($absolute);
        $this->assertStringNotContainsString(
            $webRoot.DIRECTORY_SEPARATOR,
            $absolute,
            'Report photos must not land in the public web root.'
        );

        $this->get('/storage/'.$probe)->assertForbidden();

        Storage::disk('local')->delete($probe);

        tenancy()->end();
    }

    public function test_a_doctor_from_another_practice_cannot_print_a_prescription(): void
    {
        [, $ownDoctor, $visitRecord] = $this->seedPracticeWithVisitRecord('print-a');
        [, $outsideDoctor] = $this->seedPracticeWithVisitRecord('print-b');

        tenancy()->initialize(Tenant::find('print-a'));
        $prescription = Prescription::create([
            'visit_record_id' => $visitRecord->id,
            'patient_id' => $visitRecord->patient_id,
            'prescribed_by' => $ownDoctor->id,
        ]);
        tenancy()->end();

        $this->actingAs($ownDoctor)
            ->get("http://print-a.localhost/prescriptions/{$prescription->id}/print")
            ->assertOk();

        $this->actingAs($outsideDoctor)
            ->get("http://print-a.localhost/prescriptions/{$prescription->id}/print")
            ->assertForbidden();
    }

    /**
     * @return array{0: Tenant, 1: User, 2: VisitRecord}
     */
    private function seedPracticeWithVisitRecord(string $id): array
    {
        $tenant = Tenant::create(['id' => $id, 'plan_tier' => 'solo']);
        Domain::create(['domain' => $id.'.localhost', 'tenant_id' => $id]);

        tenancy()->initialize($tenant);

        $doctorUser = User::create([
            'name' => 'Dr '.$id,
            'email' => 'doctor@'.$id.'.loc',
            'password' => Hash::make('secret'),
            'role' => User::ROLE_DOCTOR,
            'tenant_id' => $id,
        ]);

        $chamber = Chamber::create(['name' => 'Main']);
        $doctor = Doctor::create(['name' => 'Dr '.$id]);
        $session = ScheduleSession::create([
            'chamber_id' => $chamber->id,
            'doctor_id' => $doctor->id,
            'day_of_week' => Carbon::today()->dayOfWeek,
            'session_name' => 'Morning',
            'start_time' => '09:00',
            'end_time' => '23:59',
            'slot_cap' => 10,
        ]);

        $patient = Patient::create(['name' => 'Patient '.$id, 'phone' => '01712345678']);

        $booking = Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $session->id,
            'booking_date' => Carbon::today()->toDateString(),
            'patient_id' => $patient->id,
            'patient_name' => $patient->name,
            'patient_phone' => $patient->phone,
            'serial_number' => 1,
            'status' => 'completed',
        ]);

        $photoPath = 'visit-photos/'.$id.'/slip.jpg';
        $voicePath = 'visit-audio/'.$id.'/note.webm';
        $reportPath = 'visit-reports/'.$id.'/cbc.jpg';
        Storage::disk('local')->put($photoPath, 'prescription-photo');
        Storage::disk('local')->put($voicePath, 'consultation-audio');
        Storage::disk('local')->put($reportPath, 'lab-report');

        $visitRecord = VisitRecord::create([
            'booking_id' => $booking->id,
            'patient_id' => $patient->id,
            'recorded_by' => $doctorUser->id,
            'photo_path' => $photoPath,
            'voice_path' => $voicePath,
            'report_photo_paths' => [$reportPath],
            'recorded_at' => now(),
        ]);

        tenancy()->end();

        return [$tenant, $doctorUser, $visitRecord];
    }

    public function test_service_exposes_no_public_url_accessor(): void
    {
        // A URL accessor would recreate the unauthenticated path the private
        // disk exists to remove, so the service must not grow one.
        $methods = get_class_methods(VisitMediaService::class);

        foreach ($methods as $method) {
            $this->assertStringNotContainsStringIgnoringCase(
                'url',
                $method,
                "VisitMediaService::{$method}() looks like a public URL accessor."
            );
        }
    }
}
