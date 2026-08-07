<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Chamber;
use App\Models\Doctor;
use App\Models\Domain;
use App\Models\LiveSession;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\ScheduleSession;
use App\Models\SmsMessage;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VisitRecord;
use App\Services\BookingService;
use App\Services\LiveSessionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotifyChannelsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Doctor $doctor;

    private ScheduleSession $session;

    private User $staff;

    private string $monday;

    protected function setUp(): void
    {
        parent::setUp();

        config(['sms.enabled' => true, 'sms.driver' => 'log']);

        $this->tenant = Tenant::create([
            'id' => 'notify-clinic',
            'name' => 'Notify Clinic',
            'plan_tier' => 'solo',
            'slot_cap_mode' => 'per_session',
            'sms_balance' => 10,
            'queue_runner' => Tenant::QUEUE_RUNNER_STAFF,
        ]);
        Domain::create(['domain' => 'notify-clinic.localhost', 'tenant_id' => 'notify-clinic']);

        tenancy()->initialize($this->tenant);

        $this->staff = User::factory()->create([
            'tenant_id' => 'notify-clinic',
            'role' => User::ROLE_STAFF,
        ]);

        $chamber = Chamber::create(['name' => 'Main']);
        $this->doctor = Doctor::create([
            'name' => 'Dr. Notify',
            'notify_channels' => Doctor::defaultNotifyChannels(),
        ]);
        $this->session = ScheduleSession::create([
            'chamber_id' => $chamber->id,
            'doctor_id' => $this->doctor->id,
            'day_of_week' => 1,
            'session_name' => 'Morning',
            'start_time' => '09:00',
            'end_time' => '12:00',
            'slot_cap' => 10,
        ]);
        $this->monday = Carbon::now()->next(Carbon::MONDAY)->format('Y-m-d');
        tenancy()->end();
    }

    public function test_booking_sms_skipped_when_doctor_turns_confirmation_sms_off(): void
    {
        tenancy()->initialize($this->tenant);
        $this->doctor->update([
            'notify_channels' => array_replace_recursive(
                Doctor::defaultNotifyChannels(),
                [Doctor::NOTIFY_BOOKING_CONFIRMATION => ['sms' => false, 'whatsapp' => false]],
            ),
        ]);
        tenancy()->end();

        $this->postJson('http://notify-clinic.localhost/api/bookings', [
            'bookable_type' => 'session',
            'bookable_id' => $this->session->id,
            'booking_date' => $this->monday,
            'patient_name' => 'Fatima',
            'patient_phone' => '01712345678',
        ])->assertOk();

        $this->assertSame(10, $this->tenant->fresh()->sms_balance);
        $message = SmsMessage::withoutGlobalScopes()->first();
        $this->assertSame(SmsMessage::STATUS_SKIPPED_PREF_OFF, $message->status);
        $this->assertSame(SmsMessage::PURPOSE_BOOKING_CONFIRMATION, $message->purpose);
        $this->assertSame(0, $message->credits);
    }

    public function test_mark_delay_auto_sms_when_doctor_late_sms_on(): void
    {
        tenancy()->initialize($this->tenant);
        $this->doctor->update([
            'notify_channels' => array_replace_recursive(
                Doctor::defaultNotifyChannels(),
                [Doctor::NOTIFY_DOCTOR_LATE => ['sms' => true, 'whatsapp' => false]],
            ),
        ]);

        $booking = app(BookingService::class)->createBookingForBookable(
            $this->session,
            $this->monday,
            'Rahim',
            '01712345679',
            sendSms: false,
        );

        // Treat as "today" for the live session date match.
        Carbon::setTestNow(Carbon::parse($this->monday.' 10:00:00'));

        $live = LiveSession::create([
            'schedule_session_id' => $this->session->id,
            'session_date' => $this->monday,
            'status' => 'scheduled',
        ]);

        app(LiveSessionService::class)->markDelay($live, 30);

        $message = SmsMessage::withoutGlobalScopes()
            ->where('booking_id', $booking->id)
            ->where('purpose', SmsMessage::PURPOSE_DOCTOR_LATE)
            ->first();

        $this->assertNotNull($message);
        $this->assertSame(SmsMessage::STATUS_SENT, $message->status);
        $this->assertStringContainsString('delayed by 30 minutes', $message->body);
        $this->assertSame($body = $message->body, mb_convert_encoding($body, 'ASCII', 'UTF-8'));
        $this->assertSame(9, $this->tenant->fresh()->sms_balance);

        Carbon::setTestNow();
        tenancy()->end();
    }

    public function test_mark_delay_does_not_sms_when_pref_off(): void
    {
        tenancy()->initialize($this->tenant);

        app(BookingService::class)->createBookingForBookable(
            $this->session,
            $this->monday,
            'Nusrat',
            '01712345680',
            sendSms: false,
        );

        Carbon::setTestNow(Carbon::parse($this->monday.' 10:00:00'));

        $live = LiveSession::create([
            'schedule_session_id' => $this->session->id,
            'session_date' => $this->monday,
            'status' => 'scheduled',
        ]);

        app(LiveSessionService::class)->markDelay($live, 15);

        $this->assertSame(0, SmsMessage::withoutGlobalScopes()
            ->where('purpose', SmsMessage::PURPOSE_DOCTOR_LATE)
            ->count());
        $this->assertSame(10, $this->tenant->fresh()->sms_balance);

        Carbon::setTestNow();
        tenancy()->end();
    }

    public function test_staff_can_send_cancellation_sms_when_pref_on(): void
    {
        tenancy()->initialize($this->tenant);
        $this->doctor->update([
            'notify_channels' => array_replace_recursive(
                Doctor::defaultNotifyChannels(),
                [Doctor::NOTIFY_CANCELLATION => ['sms' => true, 'whatsapp' => true]],
            ),
        ]);

        $booking = app(BookingService::class)->createBookingForBookable(
            $this->session,
            $this->monday,
            'Karim',
            '01712345681',
            sendSms: false,
        );
        $booking->update(['status' => 'cancelled', 'cancelled_at' => now()]);
        tenancy()->end();

        $this->actingAs($this->staff)
            ->postJson('http://notify-clinic.localhost/api/bookings/'.$booking->id.'/sms/cancellation')
            ->assertOk()
            ->assertJsonPath('status', SmsMessage::STATUS_SENT);

        $this->assertSame(9, $this->tenant->fresh()->sms_balance);
        $this->assertSame(
            SmsMessage::PURPOSE_CANCELLATION,
            SmsMessage::withoutGlobalScopes()->where('booking_id', $booking->id)->value('purpose'),
        );
    }

    public function test_staff_can_send_prescription_sms_when_pref_on(): void
    {
        tenancy()->initialize($this->tenant);
        $this->doctor->update([
            'notify_channels' => array_replace_recursive(
                Doctor::defaultNotifyChannels(),
                [Doctor::NOTIFY_PRESCRIPTION => ['sms' => true, 'whatsapp' => false]],
            ),
        ]);

        $booking = app(BookingService::class)->createBookingForBookable(
            $this->session,
            $this->monday,
            'Laila',
            '01712345682',
            sendSms: false,
        );
        $patient = Patient::create([
            'name' => 'Laila',
            'phone' => '01712345682',
        ]);
        $booking->update(['patient_id' => $patient->id]);

        $visit = VisitRecord::create([
            'booking_id' => $booking->id,
            'patient_id' => $patient->id,
            'recorded_by' => $this->staff->id,
            'recorded_at' => now(),
        ]);
        $prescription = Prescription::create([
            'visit_record_id' => $visit->id,
            'patient_id' => $patient->id,
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
        tenancy()->end();

        $this->actingAs($this->staff)
            ->postJson('http://notify-clinic.localhost/api/prescriptions/'.$prescription->id.'/sms')
            ->assertOk()
            ->assertJsonPath('status', SmsMessage::STATUS_SENT);

        $message = SmsMessage::withoutGlobalScopes()
            ->where('purpose', SmsMessage::PURPOSE_PRESCRIPTION)
            ->first();
        $this->assertNotNull($message);
        $this->assertStringContainsString('view:', $message->body);
        $this->assertSame($message->body, mb_convert_encoding($message->body, 'ASCII', 'UTF-8'));
    }

    public function test_doctor_defaults_match_legacy_behaviour(): void
    {
        $defaults = Doctor::defaultNotifyChannels();

        $this->assertTrue($defaults[Doctor::NOTIFY_BOOKING_CONFIRMATION]['sms']);
        $this->assertFalse($defaults[Doctor::NOTIFY_BOOKING_CONFIRMATION]['whatsapp']);
        $this->assertFalse($defaults[Doctor::NOTIFY_DOCTOR_LATE]['sms']);
        $this->assertFalse($defaults[Doctor::NOTIFY_DOCTOR_LATE]['whatsapp']);
        $this->assertFalse($defaults[Doctor::NOTIFY_CANCELLATION]['sms']);
        $this->assertTrue($defaults[Doctor::NOTIFY_CANCELLATION]['whatsapp']);
        $this->assertFalse($defaults[Doctor::NOTIFY_PRESCRIPTION]['sms']);
        $this->assertTrue($defaults[Doctor::NOTIFY_PRESCRIPTION]['whatsapp']);
    }

    public function test_null_notify_channels_merges_defaults(): void
    {
        tenancy()->initialize($this->tenant);
        $doctor = Doctor::create(['name' => 'Dr Null Prefs']); // notify_channels null
        $this->assertTrue($doctor->wantsSms(Doctor::NOTIFY_BOOKING_CONFIRMATION));
        $this->assertTrue($doctor->wantsWhatsapp(Doctor::NOTIFY_CANCELLATION));
        $this->assertFalse($doctor->wantsSms(Doctor::NOTIFY_DOCTOR_LATE));
        tenancy()->end();
    }
}
