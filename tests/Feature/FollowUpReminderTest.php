<?php

namespace Tests\Feature;

use App\Console\Commands\SendFollowUpRemindersCommand;
use App\Models\Booking;
use App\Models\Chamber;
use App\Models\Doctor;
use App\Models\Domain;
use App\Models\Patient;
use App\Models\ScheduleSession;
use App\Models\SmsMessage;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VisitRecord;
use App\Services\FollowUpReminderService;
use App\Services\SmsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FollowUpReminderTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Doctor $doctor;

    private ScheduleSession $session;

    private User $doctorUser;

    protected function setUp(): void
    {
        parent::setUp();

        config(['sms.enabled' => true, 'sms.driver' => 'log']);

        $this->tenant = Tenant::create([
            'id' => 'followup-clinic',
            'name' => 'Follow-up Clinic',
            'plan_tier' => 'solo',
            'sms_balance' => 10,
            'queue_runner' => 'doctor',
        ]);
        Domain::create(['domain' => 'followup-clinic.localhost', 'tenant_id' => 'followup-clinic']);

        tenancy()->initialize($this->tenant);

        $chamber = Chamber::create(['name' => 'Main']);
        $this->doctor = Doctor::create([
            'name' => 'Dr. Follow',
            'notify_channels' => Doctor::defaultNotifyChannels(),
        ]);
        $this->doctorUser = User::factory()->create([
            'tenant_id' => 'followup-clinic',
            'role' => User::ROLE_DOCTOR,
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

        tenancy()->end();
    }

    public function test_sms_reminder_sent_three_days_before_follow_up(): void
    {
        tenancy()->initialize($this->tenant);

        $patient = Patient::create([
            'name' => 'Karim Ahmed',
            'phone' => '01712345001',
        ]);

        $booking = Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $this->session->id,
            'patient_id' => $patient->id,
            'patient_name' => 'Karim Ahmed',
            'patient_phone' => '01712345001',
            'booking_date' => now()->subWeek()->toDateString(),
            'serial_number' => 1,
            'status' => 'completed',
        ]);

        $followUpDate = now()->addDays(FollowUpReminderService::DAYS_BEFORE)->toDateString();

        VisitRecord::create([
            'booking_id' => $booking->id,
            'patient_id' => $patient->id,
            'recorded_by' => $this->doctorUser->id,
            'recorded_at' => now(),
            'follow_up_date' => $followUpDate,
        ]);

        $result = app(FollowUpReminderService::class)->processTenant();

        $this->assertSame(1, $result['sms_sent']);
        $this->assertDatabaseHas('sms_messages', [
            'purpose' => SmsMessage::PURPOSE_FOLLOW_UP,
            'status' => SmsMessage::STATUS_SENT,
        ]);

        tenancy()->end();
    }

    public function test_whatsapp_pref_queues_without_auto_send(): void
    {
        tenancy()->initialize($this->tenant);

        $this->doctor->update([
            'notify_channels' => array_replace_recursive(
                Doctor::defaultNotifyChannels(),
                [Doctor::NOTIFY_FOLLOW_UP => ['sms' => false, 'whatsapp' => true]],
            ),
        ]);

        $booking = Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $this->session->id,
            'patient_name' => 'Sadia Khan',
            'patient_phone' => '01712345002',
            'booking_date' => now()->subWeek()->toDateString(),
            'serial_number' => 2,
            'status' => 'completed',
        ]);

        $visit = VisitRecord::create([
            'booking_id' => $booking->id,
            'recorded_by' => $this->doctorUser->id,
            'recorded_at' => now(),
            'follow_up_date' => now()->addDays(FollowUpReminderService::DAYS_BEFORE)->toDateString(),
        ]);

        $result = app(FollowUpReminderService::class)->processTenant();

        $this->assertSame(0, $result['sms_sent']);
        $this->assertSame(1, $result['whatsapp_queued']);
        $this->assertNotNull($visit->fresh()->follow_up_reminder_whatsapp_queued_at);
        $this->assertNull($visit->fresh()->follow_up_reminder_whatsapp_sent_at);

        tenancy()->end();
    }

    public function test_follow_up_message_uses_template_b(): void
    {
        tenancy()->initialize($this->tenant);

        $booking = Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $this->session->id,
            'patient_name' => 'Rahim',
            'patient_phone' => '01712345003',
            'booking_date' => now()->toDateString(),
            'serial_number' => 3,
            'status' => 'completed',
        ]);

        $visit = VisitRecord::create([
            'booking_id' => $booking->id,
            'recorded_by' => $this->doctorUser->id,
            'recorded_at' => now(),
            'follow_up_date' => Carbon::parse('2026-08-20'),
        ]);

        $body = app(SmsService::class)->followUpReminderBody($booking, $visit, $this->doctor);

        $this->assertStringContainsString('Reminder: your follow-up with Dr Dr. Follow is on 20 Aug 2026.', $body);
        $this->assertStringContainsString('Reply or book:', $body);

        tenancy()->end();
    }

    public function test_command_runs_across_tenants(): void
    {
        $this->artisan(SendFollowUpRemindersCommand::class)->assertSuccessful();
    }

    /**
     * The reminder run is scheduled and unattended, so a chamber that blows up
     * must not silently cost every chamber after it in the cursor their
     * reminders — those are patients being told to come back for a recheck.
     */
    public function test_one_failing_chamber_does_not_stop_the_rest(): void
    {
        Tenant::create([
            'id' => 'followup-other',
            'name' => 'Other Clinic',
            'plan_tier' => 'solo',
            'sms_balance' => 10,
        ]);

        $spy = new class(app(SmsService::class)) extends FollowUpReminderService
        {
            /** @var list<string> */
            public array $seen = [];

            public function processTenant(): array
            {
                $id = (string) tenant('id');
                $this->seen[] = $id;

                if ($id === 'followup-clinic') {
                    throw new \RuntimeException('gateway exploded');
                }

                return ['sms_sent' => 1, 'whatsapp_queued' => 0, 'failed' => 0];
            }
        };

        app()->instance(FollowUpReminderService::class, $spy);

        // Non-zero exit so a partly-failed night is visible, not reported clean.
        $this->artisan(SendFollowUpRemindersCommand::class)->assertFailed();

        $this->assertContains('followup-clinic', $spy->seen);
        $this->assertContains(
            'followup-other',
            $spy->seen,
            'The chamber after the failing one must still get its reminders',
        );

        $this->assertFalse(
            tenancy()->initialized,
            'A failed chamber must not leave its tenant context bound',
        );
    }

    /**
     * Same rule one level down: one unsendable patient must not cost the rest
     * of that clinic's list.
     */
    public function test_one_bad_patient_does_not_stop_the_clinics_other_reminders(): void
    {
        tenancy()->initialize($this->tenant);

        $followUpDate = now()->addDays(FollowUpReminderService::DAYS_BEFORE)->toDateString();

        foreach ([['Bad Row', '01712345900'], ['Good Row', '01712345901']] as $index => [$name, $phone]) {
            $patient = Patient::create(['name' => $name, 'phone' => $phone]);

            $booking = Booking::create([
                'bookable_type' => ScheduleSession::class,
                'bookable_id' => $this->session->id,
                'patient_id' => $patient->id,
                'patient_name' => $name,
                'patient_phone' => $phone,
                'booking_date' => now()->subWeek()->toDateString(),
                'serial_number' => 90 + $index,
                'status' => 'completed',
            ]);

            VisitRecord::create([
                'booking_id' => $booking->id,
                'patient_id' => $patient->id,
                'recorded_by' => $this->doctorUser->id,
                'recorded_at' => now(),
                'follow_up_date' => $followUpDate,
            ]);
        }

        // Blows up for one patient only — the shape of a gateway rejecting a
        // single number, or a row the sender cannot make sense of.
        app()->instance(SmsService::class, new class(app(\App\Contracts\SmsGateway::class)) extends SmsService
        {
            public function sendFollowUpReminder($booking, $visit, $doctor, bool $staffTap = false): ?SmsMessage
            {
                if ($booking->patient_phone === '01712345900') {
                    throw new \RuntimeException('unroutable number');
                }

                return parent::sendFollowUpReminder($booking, $visit, $doctor, $staffTap);
            }
        });

        $result = app(FollowUpReminderService::class)->processTenant();

        $this->assertSame(1, $result['failed'], 'the bad row is counted, not swallowed');
        $this->assertSame(1, $result['sms_sent'], 'the other patient is still reminded');

        tenancy()->end();
    }
}
