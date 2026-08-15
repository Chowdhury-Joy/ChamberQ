<?php

namespace Tests\Feature;

use App\Jobs\SendQueueApproachPushes;
use App\Models\Booking;
use App\Models\BookingPushSubscription;
use App\Models\Chamber;
use App\Models\Doctor;
use App\Models\Domain;
use App\Models\LiveSession;
use App\Models\ScheduleSession;
use App\Models\SmsMessage;
use App\Models\Tenant;
use App\Services\LiveSessionService;
use App\Services\WebPush\RecordingWebPushSender;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class TicketQueueApproachTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private ScheduleSession $session;

    private LiveSession $liveSession;

    private LiveSessionService $service;

    private RecordingWebPushSender $pushes;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'id' => 'approach',
            'name' => 'Dr. Karim Chamber',
        ]);
        Domain::create(['domain' => 'approach.localhost', 'tenant_id' => 'approach']);

        tenancy()->initialize($this->tenant);

        $chamber = Chamber::create(['name' => 'Dhanmondi']);
        $doctor = Doctor::create(['name' => 'Dr Karim']);
        $this->session = ScheduleSession::create([
            'chamber_id' => $chamber->id,
            'doctor_id' => $doctor->id,
            'day_of_week' => Carbon::today()->dayOfWeek,
            'session_name' => 'Evening',
            'start_time' => '17:00',
            'end_time' => '21:00',
            'slot_cap' => 20,
        ]);

        $this->liveSession = LiveSession::create([
            'schedule_session_id' => $this->session->id,
            'session_date' => Carbon::today()->toDateString(),
            'status' => 'active',
            'started_at' => now(),
        ]);

        $this->service = app(LiveSessionService::class);
        $this->pushes = app(RecordingWebPushSender::class);
    }

    public function test_ticket_offers_bangla_pocket_alerts_when_live_queue_is_on(): void
    {
        $booking = $this->waiting('Fatima', 4);

        tenancy()->end();

        $html = $this->get('http://approach.localhost/bookings/'.$booking->id)
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="pocketAlertCard"', $html);
        $this->assertStringContainsString('জানাতে দিন', $html);
        $this->assertStringContainsString('আপনার পালা কাছে — ফিরে আসতে শুরু করুন।', $html);
        $this->assertStringContainsString('queueApproach', $html);
        $this->assertStringContainsString('navigator.vibrate', $html);
    }

    public function test_front_door_only_ticket_does_not_offer_pocket_alerts(): void
    {
        $this->tenant->update([
            'feature_flags' => Tenant::featureFlagsWithModules([], [
                Tenant::MODULE_FRONT_DOOR,
            ]),
        ]);
        $this->tenant->refresh();

        $booking = $this->waiting('Fatima', 4);

        tenancy()->end();

        $html = $this->get('http://approach.localhost/bookings/'.$booking->id)
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('id="pocketAlertCard"', $html);
        $this->assertStringNotContainsString('queueApproach', $html);
        $this->assertStringNotContainsString('refreshQueue', $html);
    }

    public function test_patient_can_subscribe_the_ticket_for_a_pocket_buzz(): void
    {
        $booking = $this->waiting('Fatima', 4);

        tenancy()->end();

        $this->postJson('http://approach.localhost/api/queue/'.$booking->id.'/push', [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/fatima-token',
            'keys' => [
                'p256dh' => str_repeat('A', 80),
                'auth' => str_repeat('B', 24),
            ],
        ])->assertOk()->assertJson(['ok' => true]);

        tenancy()->initialize($this->tenant);

        $this->assertDatabaseHas('booking_push_subscriptions', [
            'booking_id' => $booking->id,
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/fatima-token',
        ]);
    }

    public function test_calling_the_first_patient_buzzes_called_next_and_two_away(): void
    {
        $first = $this->waiting('One', 1);
        $second = $this->waiting('Two', 2);
        $third = $this->waiting('Three', 3);
        $this->waiting('Four', 4);

        $this->subscribe($first);
        $this->subscribe($second);
        $this->subscribe($third);

        Bus::fake([SendQueueApproachPushes::class]);

        $this->service->callNextPatient($this->liveSession);

        $this->runQueuedApproachPushes();

        $stages = collect($this->pushes->sent)
            ->mapWithKeys(fn (array $row) => [
                $row['subscription']->booking_id => $row['payload']['stage'],
            ])
            ->all();

        $this->assertSame('called', $stages[$first->id]);
        $this->assertSame('next', $stages[$second->id]);
        $this->assertSame('two_away', $stages[$third->id]);
        $this->assertCount(3, $this->pushes->sent);

        foreach ($this->pushes->sent as $row) {
            $this->assertNotSame('', $row['payload']['title']);
            $this->assertMatchesRegularExpression('/[\x{0980}-\x{09FF}]/u', $row['payload']['title'].$row['payload']['body']);
        }

        $this->assertSame(0, SmsMessage::query()->count());
    }

    public function test_the_same_stage_is_not_sent_twice(): void
    {
        $first = $this->waiting('One', 1);
        $this->waiting('Two', 2);
        $this->subscribe($first);

        Bus::fake([SendQueueApproachPushes::class]);

        $this->service->callNextPatient($this->liveSession);
        $this->runQueuedApproachPushes();
        $this->assertCount(1, $this->pushes->sent);

        (new SendQueueApproachPushes($this->tenant->id, $this->liveSession->id))
            ->handle($this->pushes, $this->service);
        $this->assertCount(1, $this->pushes->sent);
    }

    public function test_service_worker_can_show_a_push_when_the_ticket_is_closed(): void
    {
        tenancy()->end();

        $this->get('http://approach.localhost/sw.js')
            ->assertOk()
            ->assertSee('addEventListener(\'push\'', false)
            ->assertSee('showNotification', false)
            ->assertSee('visibilityState', false);
    }

    private function waiting(string $name, int $serial): Booking
    {
        return Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $this->session->id,
            'booking_date' => Carbon::today()->toDateString(),
            'patient_name' => $name,
            'patient_phone' => '0171000000'.$serial,
            'serial_number' => $serial,
            'status' => 'waiting',
        ]);
    }

    private function subscribe(Booking $booking): BookingPushSubscription
    {
        return BookingPushSubscription::create([
            'booking_id' => $booking->id,
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/'.$booking->id,
            'p256dh' => str_repeat('A', 80),
            'auth_token' => str_repeat('B', 24),
        ]);
    }

    private function runQueuedApproachPushes(): void
    {
        Bus::assertDispatchedAfterResponse(SendQueueApproachPushes::class, function (SendQueueApproachPushes $job) {
            $job->handle($this->pushes, $this->service);

            return true;
        });
        Bus::fake([SendQueueApproachPushes::class]);
    }
}
