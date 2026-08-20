<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Chamber;
use App\Models\Doctor;
use App\Models\Domain;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\ScheduleSession;
use App\Models\SmsMessage;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VisitRecord;
use App\Support\GsmText;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class VisitReviewNoticeTest extends TestCase
{
    use RefreshDatabase;

    private const REVIEW = 'https://g.page/r/AbCdEfGhIjKl/review';

    private Tenant $tenant;

    private User $staff;

    private Chamber $chamber;

    private Doctor $doctor;

    private ScheduleSession $session;

    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();

        config(['sms.enabled' => true, 'sms.driver' => 'log']);

        $this->tenant = Tenant::create([
            'id' => 'review-clinic',
            'name' => 'Review Clinic',
            'plan_tier' => 'solo',
            'sms_balance' => 20,
        ]);
        Domain::create(['domain' => 'review-clinic.localhost', 'tenant_id' => 'review-clinic']);
        tenancy()->initialize($this->tenant);

        $this->staff = User::create([
            'name' => 'Desk',
            'email' => 'staff@review-clinic.test',
            'password' => Hash::make('secret'),
            'role' => User::ROLE_STAFF,
            'tenant_id' => $this->tenant->id,
        ]);

        $this->chamber = Chamber::create([
            'name' => 'Main',
            'review_url' => self::REVIEW,
        ]);
        $this->doctor = Doctor::create([
            'name' => 'Dr Review',
            'notify_channels' => array_replace_recursive(
                Doctor::defaultNotifyChannels(),
                [Doctor::NOTIFY_PRESCRIPTION => ['sms' => true, 'whatsapp' => true]],
            ),
        ]);
        $this->session = ScheduleSession::create([
            'chamber_id' => $this->chamber->id,
            'doctor_id' => $this->doctor->id,
            'day_of_week' => Carbon::today()->dayOfWeek,
            'session_name' => 'Morning',
            'start_time' => '09:00',
            'end_time' => '12:00',
            'slot_cap' => 20,
        ]);

        $patient = Patient::create([
            'name' => 'Fatima Rahman',
            'phone' => '01711113333',
        ]);

        $this->booking = Booking::create([
            'patient_id' => $patient->id,
            'patient_name' => $patient->name,
            'patient_phone' => $patient->phone,
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $this->session->id,
            'booking_date' => Carbon::today()->toDateString(),
            'serial_number' => 1,
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $this->actingAs($this->staff);
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    public function test_prescription_sms_includes_the_google_review_link(): void
    {
        $prescription = $this->makePrescription();

        $this->postJson('http://review-clinic.localhost/api/prescriptions/'.$prescription->id.'/sms')
            ->assertOk()
            ->assertJsonPath('status', SmsMessage::STATUS_SENT);

        $body = SmsMessage::withoutGlobalScopes()
            ->where('booking_id', $this->booking->id)
            ->value('body');

        $this->assertStringContainsString('/p/', $body);
        $this->assertStringContainsString(self::REVIEW, $body);
        $this->assertSame($body, GsmText::toSingleSegment($body));
    }

    public function test_staff_can_send_a_review_sms_without_the_prescription_module(): void
    {
        $this->tenant->update([
            'feature_flags' => Tenant::featureFlagsWithModules(
                $this->tenant->feature_flags,
                [Tenant::MODULE_FRONT_DOOR, Tenant::MODULE_LIVE_QUEUE],
            ),
        ]);

        $this->postJson('http://review-clinic.localhost/api/bookings/'.$this->booking->id.'/sms/review')
            ->assertOk()
            ->assertJsonPath('status', SmsMessage::STATUS_SENT);

        $row = SmsMessage::withoutGlobalScopes()
            ->where('booking_id', $this->booking->id)
            ->first();

        $this->assertSame(SmsMessage::PURPOSE_PRESCRIPTION, $row->purpose);
        $this->assertStringContainsString(self::REVIEW, $row->body);
        $this->assertStringNotContainsString('/p/', $row->body);
        $this->assertSame(1, GsmText::segments($row->body));
    }

    public function test_review_sms_is_refused_when_no_review_link_is_saved(): void
    {
        $this->chamber->update(['review_url' => null]);

        $this->postJson('http://review-clinic.localhost/api/bookings/'.$this->booking->id.'/sms/review')
            ->assertStatus(422);
    }

    public function test_chamber_review_link_overrides_the_practice_link(): void
    {
        $this->tenant->update(['review_url' => 'https://g.page/r/TenantOnly/review']);

        $this->assertSame(
            self::REVIEW,
            Chamber::reviewUrlForBooking($this->booking->fresh(['bookable.chamber'])),
        );
    }

    public function test_practice_review_link_is_used_when_the_chamber_has_none(): void
    {
        $this->chamber->update(['review_url' => null]);
        $this->tenant->update(['review_url' => 'https://g.page/r/TenantOnly/review']);

        $this->assertSame(
            'https://g.page/r/TenantOnly/review',
            Chamber::reviewUrlForBooking($this->booking->fresh(['bookable.chamber'])),
        );
    }

    public function test_non_google_review_links_are_ignored(): void
    {
        $this->assertFalse(Chamber::isGoogleReviewUrl('https://evil.example/review'));
        $this->assertTrue(Chamber::isGoogleReviewUrl(self::REVIEW));
        $this->assertTrue(Chamber::isGoogleReviewUrl('https://search.google.com/local/writereview?placeid=ChIJ123'));
        $this->assertTrue(Chamber::isGoogleReviewUrl('https://maps.app.goo.gl/aBcDeF123'));
    }

    public function test_truncation_keeps_every_link_whole(): void
    {
        $rx = 'https://review-clinic.localhost/p/abcdefghij';
        $body = str_repeat('Please keep both of these links intact in this message. ', 8)
            .' '.$rx.' Review: '.self::REVIEW;

        $out = GsmText::toSingleSegment($body);

        $this->assertStringContainsString($rx, $out);
        $this->assertStringContainsString(self::REVIEW, $out);
    }

    private function makePrescription(): Prescription
    {
        $visit = VisitRecord::create([
            'booking_id' => $this->booking->id,
            'patient_id' => $this->booking->patient_id,
            'recorded_by' => $this->staff->id,
            'recorded_at' => now(),
        ]);
        $prescription = Prescription::create([
            'visit_record_id' => $visit->id,
            'patient_id' => $this->booking->patient_id,
            'prescribed_by' => $this->staff->id,
        ]);
        PrescriptionItem::create([
            'prescription_id' => $prescription->id,
            'medicine_name' => 'NAPA',
            'dose' => '500mg',
            'frequency' => '1+0+1',
            'duration' => '5 days',
            'sort_order' => 0,
        ]);

        return $prescription;
    }
}
