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
use App\Support\DataBackup\BackupTableMap;
use App\Support\DataBackup\ImportOptions;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
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

    /** @var list<string> */
    private array $artifactsBefore = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->artifactsBefore = $this->backupArtifacts();

        $this->tenantA = Tenant::create(['id' => 'backup-a', 'plan_tier' => 'solo', 'name' => 'Chamber A']);
        $this->tenantB = Tenant::create(['id' => 'backup-b', 'plan_tier' => 'solo', 'name' => 'Chamber B']);
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        $this->deleteArtifactsWrittenByThisTest();

        parent::tearDown();
    }

    /**
     * Every ZIP and scratch directory the backup feature can leave behind:
     * `backup-temp` holds exports written by the service and the artisan
     * command, `backup-test` the directories this class creates for hostile
     * and extracted ZIPs. Neither is cleaned by the application.
     *
     * @return list<string>
     */
    private function backupArtifacts(): array
    {
        return array_merge(
            glob(storage_path('app/backup-temp/*')) ?: [],
            glob(storage_path('app/backup-test/*')) ?: [],
        );
    }

    /**
     * Remove only what this test added. Leftovers from earlier runs are not
     * ours to delete, and diffing against the setUp snapshot stays correct
     * whatever order the tests run in.
     */
    private function deleteArtifactsWrittenByThisTest(): void
    {
        foreach (array_diff($this->backupArtifacts(), $this->artifactsBefore) as $path) {
            is_dir($path) ? File::deleteDirectory($path) : @unlink($path);
        }
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

        $before = glob(storage_path('app/backup-temp/*-backup-a.zip')) ?: [];

        $this->artisan('data:backup-export', ['tenant' => 'backup-a'])
            ->assertSuccessful();

        // glob() sorts alphabetically and these filenames lead with a UUID, so
        // array_key_last() returned the alphabetically-largest ZIP left over by
        // any previous run rather than the one this test just wrote.
        $created = array_values(array_diff(
            glob(storage_path('app/backup-temp/*-backup-a.zip')) ?: [],
            $before,
        ));

        $this->assertCount(1, $created, 'The export command should write exactly one new ZIP');
        $zipPath = $created[0];

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

    /**
     * A chamber admin restoring a ZIP that reuses somebody else's row id must
     * not be able to rewrite that row into their own chamber. `users.id` is a
     * plain auto-increment integer, so the ids of the central Super Admin and
     * of every other chamber's staff are guessable by counting.
     */
    public function test_restore_refuses_a_zip_that_reuses_another_accounts_user_id(): void
    {
        $superAdmin = User::create([
            'name' => 'Platform Super Admin',
            'email' => 'super@platform.test',
            'password' => Hash::make('secret'),
            'role' => User::ROLE_SUPER_ADMIN,
            'tenant_id' => null,
        ]);

        $chamberBStaff = User::create([
            'name' => 'Dr B',
            'email' => 'drb@b.test',
            'password' => Hash::make('secret'),
            'role' => User::ROLE_DOCTOR,
            'tenant_id' => $this->tenantB->id,
        ]);

        $zipPath = $this->makeHostileZip($this->tenantA->id, 'users', [
            'id,tenant_id,role,name,email,email_verified_at,created_at,updated_at',
            "{$superAdmin->id},{$this->tenantB->id},admin,Pwned,attacker+1@evil.test,,,",
            "{$chamberBStaff->id},{$this->tenantB->id},admin,Pwned,attacker+2@evil.test,,,",
        ]);

        tenancy()->initialize($this->tenantA);

        try {
            app(DataImportService::class)->importFromZip($zipPath, new ImportOptions(
                scope: ImportOptions::SCOPE_TENANT,
                tenantId: $this->tenantA->id,
                mode: ImportOptions::MODE_MERGE,
            ));
            $this->fail('Restore should have been refused.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('another chamber', $e->getMessage());
        }

        tenancy()->end();

        $super = DB::table('users')->where('id', $superAdmin->id)->first();
        $this->assertNull($super->tenant_id, 'Super Admin must not be pulled into a chamber');
        $this->assertSame(User::ROLE_SUPER_ADMIN, $super->role);
        $this->assertSame('super@platform.test', $super->email);

        $other = DB::table('users')->where('id', $chamberBStaff->id)->first();
        $this->assertSame($this->tenantB->id, $other->tenant_id);
        $this->assertSame('drb@b.test', $other->email);
    }

    /**
     * A replace restore wipes first. If the import then fails, the wipe must go
     * with it — a chamber left empty because a restore half-ran is the single
     * worst outcome this feature can produce.
     */
    public function test_a_failed_replace_restore_rolls_back_the_wipe(): void
    {
        tenancy()->initialize($this->tenantA);
        Patient::create(['name' => 'Keep Me', 'phone' => '01715556666']);
        Chamber::create(['name' => 'Keep This Chamber']);
        tenancy()->end();

        $before = [
            'patients' => DB::table('patients')->where('tenant_id', $this->tenantA->id)->count(),
            'chambers' => DB::table('chambers')->where('tenant_id', $this->tenantA->id)->count(),
        ];
        $this->assertSame(1, $before['patients']);

        // live_sessions pointing at a booking id that is in no CSV — the import
        // cannot satisfy the foreign key, so the whole restore must abort.
        $zipPath = $this->makeHostileZip($this->tenantA->id, 'live_sessions', [
            'id,tenant_id,schedule_session_id,session_date,status,current_booking_id,started_at,created_at,updated_at',
            '1,'.$this->tenantA->id.',999999,'.Carbon::today()->toDateString().',active,'.Str::uuid().',,,',
        ]);

        tenancy()->initialize($this->tenantA);

        try {
            app(DataImportService::class)->importFromZip($zipPath, new ImportOptions(
                scope: ImportOptions::SCOPE_TENANT,
                tenantId: $this->tenantA->id,
                mode: ImportOptions::MODE_REPLACE,
            ));
        } catch (\Throwable) {
            // Expected — the point is what the database looks like afterwards.
        }

        tenancy()->end();

        $after = [
            'patients' => DB::table('patients')->where('tenant_id', $this->tenantA->id)->count(),
            'chambers' => DB::table('chambers')->where('tenant_id', $this->tenantA->id)->count(),
        ];

        $this->assertSame($before, $after, 'A failed restore must leave the chamber untouched');
    }

    /**
     * `live_sessions.current_booking_id` is a foreign key to `bookings.id`, so
     * bookings must be imported first and deleted last. Restoring a chamber
     * whose queue is live used to trip that key in both directions.
     */
    public function test_replace_restore_works_while_a_queue_is_live(): void
    {
        tenancy()->initialize($this->tenantA);

        $patient = Patient::create(['name' => 'In Queue', 'phone' => '01716667777']);
        $chamber = Chamber::create(['name' => 'Main']);
        $doctor = Doctor::create(['name' => 'Dr Queue']);
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
            'patient_name' => 'In Queue',
            'patient_phone' => '01716667777',
            'serial_number' => 1,
            'status' => 'in_chamber',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('live_sessions')->insert([
            'id' => 1,
            'tenant_id' => $this->tenantA->id,
            'schedule_session_id' => $session->id,
            'session_date' => Carbon::today()->toDateString(),
            'status' => 'active',
            'current_booking_id' => $bookingId,
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $zipPath = app(DataBackupService::class)->exportTenantToZipPath($this->tenantA->id);

        $result = app(DataImportService::class)->importFromZip($zipPath, new ImportOptions(
            scope: ImportOptions::SCOPE_TENANT,
            tenantId: $this->tenantA->id,
            mode: ImportOptions::MODE_REPLACE,
        ));

        $this->assertGreaterThanOrEqual(1, $result->tableCounts['bookings'] ?? 0);
        $this->assertSame(1, DB::table('live_sessions')->where('tenant_id', $this->tenantA->id)->count());
        $this->assertSame(
            $bookingId,
            DB::table('live_sessions')->where('tenant_id', $this->tenantA->id)->value('current_booking_id'),
        );

        tenancy()->end();
    }

    /**
     * `prescription_items` has no tenant_id — it is owned through its parent
     * prescription — so a crafted CSV must not be able to graft medicines onto
     * another chamber's prescription.
     */
    public function test_restore_refuses_prescription_lines_for_another_chambers_prescription(): void
    {
        $foreignPrescriptionId = (string) Str::uuid();

        tenancy()->initialize($this->tenantB);
        $patientB = Patient::create(['name' => 'Patient B', 'phone' => '01718889999']);
        $userB = User::create([
            'name' => 'Dr B', 'email' => 'rxb@b.test', 'password' => Hash::make('secret'),
            'role' => User::ROLE_DOCTOR, 'tenant_id' => $this->tenantB->id,
        ]);
        $bookingB = (string) Str::uuid();
        DB::table('bookings')->insert([
            'id' => $bookingB, 'tenant_id' => $this->tenantB->id, 'patient_id' => $patientB->id,
            'bookable_type' => ScheduleSession::class, 'bookable_id' => 1,
            'booking_date' => Carbon::today()->toDateString(), 'patient_name' => 'Patient B',
            'patient_phone' => '01718889999', 'serial_number' => 1, 'status' => 'completed',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $visitB = (string) Str::uuid();
        DB::table('visit_records')->insert([
            'id' => $visitB, 'tenant_id' => $this->tenantB->id, 'booking_id' => $bookingB,
            'patient_id' => $patientB->id, 'recorded_by' => $userB->id, 'recorded_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('prescriptions')->insert([
            'id' => $foreignPrescriptionId, 'tenant_id' => $this->tenantB->id,
            'visit_record_id' => $visitB, 'patient_id' => $patientB->id,
            'prescribed_by' => $userB->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        tenancy()->end();

        $zipPath = $this->makeHostileZip($this->tenantA->id, 'prescription_items', [
            'id,prescription_id,medicine_name,generic_name,dose,frequency,duration,sort_order,created_at,updated_at',
            Str::uuid().','.$foreignPrescriptionId.',INJECTED,,,,,0,,',
        ]);

        tenancy()->initialize($this->tenantA);

        try {
            app(DataImportService::class)->importFromZip($zipPath, new ImportOptions(
                scope: ImportOptions::SCOPE_TENANT,
                tenantId: $this->tenantA->id,
                mode: ImportOptions::MODE_MERGE,
            ));
            $this->fail('Restore should have been refused.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('does not own', $e->getMessage());
        }

        tenancy()->end();

        $this->assertSame(
            0,
            DB::table('prescription_items')->where('prescription_id', $foreignPrescriptionId)->count(),
            'No line item may be grafted onto another chamber\'s prescription',
        );
    }

    /**
     * @param  list<string>  $csvLines
     */
    private function makeHostileZip(string $tenantId, string $table, array $csvLines): string
    {
        $directory = storage_path('app/backup-test/'.uniqid('hostile-', true));
        mkdir($directory, 0777, true);

        file_put_contents($directory.'/manifest.json', json_encode([
            'version' => BackupTableMap::MANIFEST_VERSION,
            'scope' => ImportOptions::SCOPE_TENANT,
            'tenant_id' => $tenantId,
        ]));
        file_put_contents($directory.'/'.$table.'.csv', implode("\n", $csvLines)."\n");

        $zipPath = $directory.'/hostile.zip';
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFile($directory.'/manifest.json', 'manifest.json');
        $zip->addFile($directory.'/'.$table.'.csv', $table.'.csv');
        $zip->close();

        return $zipPath;
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
