<?php

namespace Tests\Feature;

use App\Filament\TenantAdmin\Pages\ConsultScreen;
use App\Filament\TenantAdmin\Pages\DailyRoster;
use App\Models\Booking;
use App\Models\Chamber;
use App\Models\Doctor;
use App\Models\Domain;
use App\Models\LiveSession;
use App\Models\Patient;
use App\Models\ScheduleSession;
use App\Models\Tenant;
use App\Models\User;
use App\Services\VisitRecordService;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class StaffVisitPaperScanTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $doctor;

    private User $staff;

    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->tenant = Tenant::create(['id' => 'paper-scan', 'plan_tier' => 'solo']);
        Domain::create(['domain' => 'paper-scan.localhost', 'tenant_id' => $this->tenant->id]);

        tenancy()->initialize($this->tenant);

        $this->doctor = User::create([
            'name' => 'Dr Scan',
            'email' => 'doc@paper-scan.test',
            'password' => Hash::make('secret'),
            'role' => User::ROLE_DOCTOR,
            'tenant_id' => $this->tenant->id,
        ]);

        $this->staff = User::create([
            'name' => 'Desk',
            'email' => 'staff@paper-scan.test',
            'password' => Hash::make('secret'),
            'role' => User::ROLE_STAFF,
            'tenant_id' => $this->tenant->id,
        ]);

        $chamber = Chamber::create(['name' => 'Main']);
        $profile = Doctor::create(['name' => 'Dr Scan']);
        $session = ScheduleSession::create([
            'chamber_id' => $chamber->id,
            'doctor_id' => $profile->id,
            'day_of_week' => Carbon::today()->dayOfWeek,
            'session_name' => 'Morning',
            'start_time' => '09:00',
            'end_time' => '21:00',
            'slot_cap' => 20,
        ]);

        $patient = Patient::create([
            'name' => 'Fatima Rahman',
            'phone' => '01711112222',
        ]);

        $this->booking = Booking::create([
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $session->id,
            'booking_date' => Carbon::today(),
            'patient_id' => $patient->id,
            'patient_name' => $patient->name,
            'patient_phone' => $patient->phone,
            'serial_number' => 1,
            'status' => 'waiting',
        ]);
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    public function test_staff_scan_papers_on_a_waiting_row(): void
    {
        $path = 'visit-reports/'.$this->tenant->id.'/cbc.jpg';
        Storage::disk('local')->put($path, 'scan-bytes');

        $record = app(VisitRecordService::class)->saveStaffVisitPaper(
            $this->booking,
            $this->staff,
            ['report_photos' => [$path]],
        );

        $this->assertSame([$path], $record?->report_photo_paths);
        $this->assertFalse($this->doctor->attachesVisitPaperOnConsult());
        $this->assertTrue($this->staff->attachesVisitPaperAtDesk());
        $this->assertFalse($this->doctor->attachesVisitPaperAtDesk());
    }

    public function test_doctor_with_staff_cannot_scan_at_the_desk(): void
    {
        $this->expectException(HttpException::class);

        app(VisitRecordService::class)->saveStaffVisitPaper(
            $this->booking,
            $this->doctor,
            ['report_photos' => ['visit-reports/'.$this->tenant->id.'/x.jpg']],
        );
    }

    public function test_consult_pad_tells_a_staffed_doctor_to_leave_scanning_to_the_desk(): void
    {
        $this->booking->update(['status' => 'in_chamber']);

        LiveSession::create([
            'schedule_session_id' => $this->booking->bookable_id,
            'session_date' => Carbon::today(),
            'status' => 'active',
            'started_at' => now(),
            'current_booking_id' => $this->booking->id,
        ]);

        Filament::setCurrentPanel(Filament::getPanel('tenantAdmin'));
        $this->actingAs($this->doctor);

        Livewire::test(ConsultScreen::class)
            ->assertSee('Staff scan papers at the desk.')
            ->assertSee('canAttachVisitPaper: false', false);
    }

    public function test_solo_doctor_keeps_scan_and_photo_on_the_pad(): void
    {
        $this->staff->delete();
        $this->tenant->unsetRelation('queueRolePresence');

        $this->assertTrue($this->doctor->fresh()->attachesVisitPaperOnConsult());

        $this->booking->update(['status' => 'in_chamber']);

        LiveSession::create([
            'schedule_session_id' => $this->booking->bookable_id,
            'session_date' => Carbon::today(),
            'status' => 'active',
            'started_at' => now(),
            'current_booking_id' => $this->booking->id,
        ]);

        Filament::setCurrentPanel(Filament::getPanel('tenantAdmin'));
        $this->actingAs($this->doctor);

        $html = Livewire::test(ConsultScreen::class)->html();

        $this->assertStringContainsString('capture="environment"', $html);
        $this->assertStringContainsString(__('Scan'), $html);
        $this->assertStringContainsString(__('Take photo'), $html);
        $this->assertStringContainsString(__('Use the desk scanner first. Take a photo only if there is no scanner.'), $html);
    }

    public function test_roster_exposes_scan_papers_to_staff(): void
    {
        Filament::setCurrentPanel('tenantAdmin');
        $this->actingAs($this->staff);

        Livewire::test(DailyRoster::class)
            ->assertTableActionVisible('scanPapers', $this->booking);
    }

    public function test_pad_save_from_a_staffed_doctor_does_not_wipe_desk_scans(): void
    {
        $path = 'visit-reports/'.$this->tenant->id.'/old.jpg';
        Storage::disk('local')->put($path, 'old');

        app(VisitRecordService::class)->saveStaffVisitPaper(
            $this->booking,
            $this->staff,
            ['report_photos' => [$path]],
        );

        $this->booking->update(['status' => 'in_chamber']);

        LiveSession::create([
            'schedule_session_id' => $this->booking->bookable_id,
            'session_date' => Carbon::today(),
            'status' => 'active',
            'started_at' => now(),
            'current_booking_id' => $this->booking->id,
        ]);

        Filament::setCurrentPanel(Filament::getPanel('tenantAdmin'));
        $this->actingAs($this->doctor);

        Livewire::test(ConsultScreen::class)
            ->call('saveRxDesk', [
                'advice' => 'Rest',
                'report_photos' => [],
            ]);

        $this->assertSame(
            [$path],
            $this->booking->fresh()->visitRecord?->report_photo_paths,
        );
    }
}
