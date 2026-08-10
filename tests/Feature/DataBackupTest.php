<?php

namespace Tests\Feature;

use App\Filament\SuperAdmin\Pages\DataBackup as SuperAdminDataBackup;
use App\Filament\TenantAdmin\Pages\DataBackup as TenantDataBackup;
use App\Models\Chamber;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\ScheduleSession;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DataBackupService;
use App\Services\DataImportService;
use App\Support\DataBackup\BackupCsv;
use App\Support\DataBackup\ImportOptions;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;
use ZipArchive;

class DataBackupTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::create(['id' => 'backup-a', 'plan_tier' => 'solo', 'name' => 'Chamber A']);
        $this->tenantB = Tenant::create(['id' => 'backup-b', 'plan_tier' => 'solo', 'name' => 'Chamber B']);
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    public function test_tenant_export_zip_contains_manifest_and_patient_without_password(): void
    {
        tenancy()->initialize($this->tenantA);

        Patient::create([
            'name' => 'Rahim Uddin',
            'phone' => '01711111111',
        ]);

        User::create([
            'name' => 'Admin A',
            'email' => 'admin-a@test.test',
            'password' => Hash::make('secret'),
            'role' => User::ROLE_ADMIN,
            'tenant_id' => $this->tenantA->id,
        ]);

        $zipPath = app(DataBackupService::class)->exportTenantToZipPath($this->tenantA->id);

        $this->assertFileExists($zipPath);

        $extracted = $this->extractZip($zipPath);
        $manifest = json_decode((string) file_get_contents($extracted.'/manifest.json'), true);

        $this->assertSame('tenant', $manifest['scope']);
        $this->assertSame('backup-a', $manifest['tenant_id']);
        $this->assertGreaterThanOrEqual(1, $manifest['tables']['patients']);

        $patients = BackupCsv::readFile($extracted.'/patients.csv');
        $this->assertContains('Rahim Uddin', array_column($patients['rows'], 'name'));

        $users = BackupCsv::readFile($extracted.'/users.csv');
        $this->assertNotContains('password', $users['header']);
        $this->assertNotContains('remember_token', $users['header']);

        tenancy()->end();
    }

    public function test_tenant_export_excludes_other_chamber_patients(): void
    {
        tenancy()->initialize($this->tenantA);
        Patient::create(['name' => 'Only A', 'phone' => '01710000001']);
        tenancy()->end();

        tenancy()->initialize($this->tenantB);
        Patient::create(['name' => 'Only B', 'phone' => '01710000002']);
        tenancy()->end();

        $zipPath = app(DataBackupService::class)->exportTenantToZipPath($this->tenantA->id);
        $extracted = $this->extractZip($zipPath);
        $patients = BackupCsv::readFile($extracted.'/patients.csv');

        $names = array_column($patients['rows'], 'name');
        $this->assertContains('Only A', $names);
        $this->assertNotContains('Only B', $names);
    }

    public function test_round_trip_replace_restores_patient_and_booking(): void
    {
        tenancy()->initialize($this->tenantA);

        $patient = Patient::create([
            'name' => 'Restore Me',
            'phone' => '01712223333',
        ]);

        $chamber = Chamber::create(['name' => 'Main']);
        $doctor = Doctor::create(['name' => 'Dr Restore']);
        $session = ScheduleSession::create([
            'chamber_id' => $chamber->id,
            'doctor_id' => $doctor->id,
            'day_of_week' => Carbon::today()->dayOfWeek,
            'session_name' => 'Morning',
            'start_time' => '09:00',
            'end_time' => '12:00',
            'slot_cap' => 10,
        ]);

        $bookingId = (string) Str::uuid();

        DB::table('bookings')->insert([
            'id' => $bookingId,
            'tenant_id' => $this->tenantA->id,
            'patient_id' => $patient->id,
            'bookable_type' => ScheduleSession::class,
            'bookable_id' => $session->id,
            'booking_date' => Carbon::today()->toDateString(),
            'patient_name' => 'Restore Me',
            'patient_phone' => '01712223333',
            'serial_number' => 1,
            'status' => 'completed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $zipPath = app(DataBackupService::class)->exportTenantToZipPath($this->tenantA->id);

        Patient::query()->delete();
        DB::table('bookings')->where('tenant_id', $this->tenantA->id)->delete();
        $this->assertSame(0, Patient::count());
        $this->assertSame(0, DB::table('bookings')->where('tenant_id', $this->tenantA->id)->count());

        $result = app(DataImportService::class)->importFromZip(
            $zipPath,
            new ImportOptions(
                scope: ImportOptions::SCOPE_TENANT,
                tenantId: $this->tenantA->id,
                mode: ImportOptions::MODE_REPLACE,
            ),
        );

        $this->assertGreaterThanOrEqual(1, $result->tableCounts['patients'] ?? 0);
        $this->assertSame('Restore Me', Patient::first()?->name);
        $this->assertSame(1, DB::table('bookings')->where('tenant_id', $this->tenantA->id)->count());

        tenancy()->end();
    }

    public function test_dry_run_does_not_write_rows(): void
    {
        tenancy()->initialize($this->tenantA);
        Patient::create(['name' => 'Before Dry Run', 'phone' => '01713334444']);
        $zipPath = app(DataBackupService::class)->exportTenantToZipPath($this->tenantA->id);
        Patient::query()->delete();
        tenancy()->end();

        app(DataImportService::class)->importFromZip(
            $zipPath,
            new ImportOptions(
                scope: ImportOptions::SCOPE_TENANT,
                tenantId: $this->tenantA->id,
                mode: ImportOptions::MODE_REPLACE,
                dryRun: true,
            ),
        );

        tenancy()->initialize($this->tenantA);
        $this->assertSame(0, Patient::count());
        tenancy()->end();
    }

    public function test_chamber_admin_can_access_backup_page(): void
    {
        tenancy()->initialize($this->tenantA);

        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin-backup@test.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_ADMIN,
            'tenant_id' => $this->tenantA->id,
        ]);

        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('tenantAdmin'));

        $this->assertTrue(TenantDataBackup::canAccess());

        Livewire::test(TenantDataBackup::class)
            ->assertSuccessful()
            ->call('downloadBackup')
            ->assertSuccessful();

        tenancy()->end();
    }

    public function test_doctor_cannot_access_chamber_backup_page(): void
    {
        tenancy()->initialize($this->tenantA);

        $doctor = User::create([
            'name' => 'Doctor',
            'email' => 'doctor-backup@test.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_DOCTOR,
            'tenant_id' => $this->tenantA->id,
        ]);

        $this->actingAs($doctor);
        Filament::setCurrentPanel(Filament::getPanel('tenantAdmin'));

        $this->assertFalse(TenantDataBackup::canAccess());

        tenancy()->end();
    }

    public function test_super_admin_can_access_platform_backup_page(): void
    {
        $super = User::create([
            'name' => 'Super',
            'email' => 'super-backup@test.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_SUPER_ADMIN,
            'tenant_id' => null,
        ]);

        $this->actingAs($super);
        Filament::setCurrentPanel(Filament::getPanel('superAdmin'));

        $this->assertTrue(SuperAdminDataBackup::canAccess());

        Livewire::test(SuperAdminDataBackup::class)
            ->assertSuccessful()
            ->call('downloadPlatformBackup')
            ->assertSuccessful();
    }

    public function test_artisan_export_and_import_commands(): void
    {
        tenancy()->initialize($this->tenantA);
        Patient::create(['name' => 'Artisan Patient', 'phone' => '01715556666']);
        tenancy()->end();

        $this->artisan('data:backup-export', ['tenant' => 'backup-a'])
            ->assertSuccessful();

        $files = glob(storage_path('app/backup-temp/*-backup-a.zip'));
        $this->assertNotEmpty($files);
        $zipPath = $files[array_key_last($files)];

        tenancy()->initialize($this->tenantA);
        Patient::query()->delete();
        tenancy()->end();

        $this->artisan('data:backup-import', [
            'zip' => $zipPath,
            '--tenant' => 'backup-a',
            '--mode' => 'replace',
        ])->assertSuccessful();

        tenancy()->initialize($this->tenantA);
        $this->assertSame('Artisan Patient', Patient::first()?->name);
        tenancy()->end();
    }

    private function extractZip(string $zipPath): string
    {
        $directory = storage_path('app/backup-test/'.uniqid('zip-', true));
        mkdir($directory, 0777, true);

        $zip = new ZipArchive;
        $zip->open($zipPath);
        $zip->extractTo($directory);
        $zip->close();

        return $directory;
    }
}
