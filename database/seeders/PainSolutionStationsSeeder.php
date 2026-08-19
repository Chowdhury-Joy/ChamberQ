<?php

namespace Database\Seeders;

use App\Models\Chamber;
use App\Models\Doctor;
use App\Models\Domain;
use App\Models\Employee;
use App\Models\FeeCatalogItem;
use App\Models\ReferringDoctor;
use App\Models\ScheduleSession;
use App\Models\Tenant;
use App\Models\User;
use App\Scopes\TenantScope;
use App\Support\SeedAccounts;
use Illuminate\Database\Seeder;

/**
 * Dr. Moin Uddin — Pain Solution (Chittagong), first Stations client.
 *
 * Online booking on; most patients are still expected to walk in at the desk.
 * Two own branches, one doctor, three room lines.
 *
 * Run: php artisan db:seed --class=PainSolutionStationsSeeder
 */
class PainSolutionStationsSeeder extends Seeder
{
    public const TENANT_ID = 'painsolution';

    public function run(): void
    {
        SeedAccounts::refuseProduction();

        $tenant = Tenant::updateOrCreate(['id' => self::TENANT_ID], [
            'name' => 'Pain Solution Center',
            'plan_tier' => 'clinic',
            'slot_cap_mode' => 'per_session',
            'contact_phone' => '01800000000',
            'whatsapp_number' => '8801800000000',
            'theme_color' => '#0f766e',
            'favicon_url' => '/icons/health-favicon.svg',
            'font_family' => 'Hind Siliguri',
            'default_locale' => 'bn',
            'tagline' => 'Pain and intervention clinic — book online or walk in at the chamber.',
            'sms_balance' => 200,
            'billing_status' => 'active',
            'queue_runner' => Tenant::QUEUE_RUNNER_STAFF,
            'practice_rules' => \App\Services\PracticeRules::normalize([
                'referral_visit_taka' => 200,
                'referral_intervention_taka' => 1000,
                'referral_msk_taka' => 0,
            ]),
            'feature_flags' => Tenant::mergeOptInModuleFlag(
                Tenant::mergeOptInModuleFlag(
                    Tenant::mergeStationsFlag(
                        Tenant::featureFlagsWithModules([], [
                            Tenant::MODULE_FRONT_DOOR,
                            Tenant::MODULE_LIVE_QUEUE,
                            Tenant::MODULE_PRESCRIPTION,
                        ]),
                        true,
                    ),
                    Tenant::MODULE_REFERRALS,
                    true,
                ),
                Tenant::MODULE_HR,
                true,
            ),
        ]);

        Domain::firstOrCreate(
            ['domain' => 'painsolution.localhost'],
            ['tenant_id' => self::TENANT_ID],
        );

        SeedAccounts::upsert(
            ['email' => 'admin@painsolution.local', 'tenant_id' => self::TENANT_ID],
            [
                'name' => 'ChamberQ Support',
                'role' => User::ROLE_HELPER,
            ],
            'pass',
        );

        SeedAccounts::upsert(
            ['email' => 'owner@painsolution.local', 'tenant_id' => self::TENANT_ID],
            [
                'name' => 'Dr. Moin Uddin (Owner)',
                'role' => User::ROLE_ADMIN,
            ],
            'pass',
        );

        $doctorUser = SeedAccounts::upsert(
            ['email' => 'doctor@painsolution.local', 'tenant_id' => self::TENANT_ID],
            [
                'name' => 'Dr. Moin Uddin',
                'role' => User::ROLE_DOCTOR,
            ],
            'pass',
        );

        SeedAccounts::upsert(
            ['email' => 'staff@painsolution.local', 'tenant_id' => self::TENANT_ID],
            [
                'name' => 'Desk Staff',
                'role' => User::ROLE_STAFF,
            ],
            'pass',
        );

        tenancy()->initialize($tenant);

        Chamber::query()->delete();
        Doctor::query()->delete();
        ScheduleSession::query()->delete();
        FeeCatalogItem::query()->delete();

        $doctor = Doctor::create([
            'name' => 'Dr. Moin Uddin',
            'user_id' => $doctorUser->id,
            'practice_type' => Doctor::PRACTICE_GENERAL,
            'qualifications' => 'MBBS, FCPS (Physical Medicine)',
            'default_fee_taka' => 1000,
        ]);

        $panchlaish = Chamber::create([
            'name' => 'Pain Solution — Panchlaish',
            'address' => 'Panchlaish, Chattogram',
            'contact' => '01800000000',
        ]);

        $halishahar = Chamber::create([
            'name' => 'Pain Solution — Halishahar',
            'address' => 'Halishahar, Chattogram',
            'contact' => '01800000001',
        ]);

        // One branch per day — edit days to match his real rota.
        $this->seedBranchWeek($panchlaish, $doctor, [6, 1, 3]); // Sat, Mon, Wed
        $this->seedBranchWeek($halishahar, $doctor, [0, 2, 4]); // Sun, Tue, Thu

        $this->seedFeeCatalogue();
        $this->seedReferringDoctors();
        $this->seedHr();

        tenancy()->end();

        $this->call(PainSolutionDemoSeeder::class);
    }

    /**
     * @param  list<int>  $daysOfWeek  Carbon dayOfWeek (0 = Sunday … 6 = Saturday)
     */
    private function seedBranchWeek(Chamber $chamber, Doctor $doctor, array $daysOfWeek): void
    {
        foreach ($daysOfWeek as $day) {
            ScheduleSession::create([
                'chamber_id' => $chamber->id,
                'doctor_id' => $doctor->id,
                'day_of_week' => $day,
                'session_name' => 'Intervention',
                'kind' => ScheduleSession::KIND_INTERVENTION,
                'start_time' => '10:00',
                'end_time' => '12:00',
                'slot_cap' => 18,
                'walk_in_overflow_cap' => 0,
            ]);

            ScheduleSession::create([
                'chamber_id' => $chamber->id,
                'doctor_id' => $doctor->id,
                'day_of_week' => $day,
                'session_name' => 'Visit',
                'kind' => ScheduleSession::KIND_VISIT,
                'start_time' => '12:00',
                'end_time' => '14:30',
                'slot_cap' => 25,
                'walk_in_overflow_cap' => 6,
            ]);

            ScheduleSession::create([
                'chamber_id' => $chamber->id,
                'doctor_id' => $doctor->id,
                'day_of_week' => $day,
                'session_name' => 'Counseling',
                'kind' => ScheduleSession::KIND_COUNSELING,
                'start_time' => '10:00',
                'end_time' => '14:30',
                'slot_cap' => 40,
                'walk_in_overflow_cap' => 0,
            ]);
        }
    }

    private function seedFeeCatalogue(): void
    {
        $rows = [
            ['label' => 'Visit (new)', 'list_price_taka' => 1000, 'house_share_taka' => 200, 'sitting_kind' => ScheduleSession::KIND_VISIT, 'sort_order' => 10],
            ['label' => 'Follow-up', 'list_price_taka' => 800, 'house_share_taka' => 100, 'sitting_kind' => ScheduleSession::KIND_VISIT, 'sort_order' => 20],
            ['label' => 'MSK', 'list_price_taka' => 3500, 'house_share_taka' => 500, 'sitting_kind' => ScheduleSession::KIND_INTERVENTION, 'sort_order' => 100],
            ['label' => 'ED', 'list_price_taka' => 15000, 'house_share_taka' => 1000, 'sitting_kind' => ScheduleSession::KIND_INTERVENTION, 'sort_order' => 110],
            ['label' => 'KOA', 'list_price_taka' => 8000, 'house_share_taka' => 1000, 'sitting_kind' => ScheduleSession::KIND_INTERVENTION, 'sort_order' => 120],
            ['label' => 'Shoulder (single)', 'list_price_taka' => 8000, 'house_share_taka' => 1000, 'sitting_kind' => ScheduleSession::KIND_INTERVENTION, 'sort_order' => 130],
            ['label' => 'Shoulder (both)', 'list_price_taka' => 12000, 'house_share_taka' => 1000, 'sitting_kind' => ScheduleSession::KIND_INTERVENTION, 'sort_order' => 140],
            ['label' => 'CTS (single)', 'list_price_taka' => 8000, 'house_share_taka' => 1000, 'sitting_kind' => ScheduleSession::KIND_INTERVENTION, 'sort_order' => 150],
            ['label' => 'CTS (both)', 'list_price_taka' => 15000, 'house_share_taka' => 1000, 'sitting_kind' => ScheduleSession::KIND_INTERVENTION, 'sort_order' => 160],
            ['label' => 'DQ', 'list_price_taka' => 8000, 'house_share_taka' => 1000, 'sitting_kind' => ScheduleSession::KIND_INTERVENTION, 'sort_order' => 170],
            ['label' => 'T/E', 'list_price_taka' => 8000, 'house_share_taka' => 1000, 'sitting_kind' => ScheduleSession::KIND_INTERVENTION, 'sort_order' => 180],
            ['label' => 'Hip joint', 'list_price_taka' => 10000, 'house_share_taka' => 1000, 'sitting_kind' => ScheduleSession::KIND_INTERVENTION, 'sort_order' => 190],
            ['label' => 'SI joint', 'list_price_taka' => 10000, 'house_share_taka' => 1000, 'sitting_kind' => ScheduleSession::KIND_INTERVENTION, 'sort_order' => 200],
            ['label' => 'PRP knee (single)', 'list_price_taka' => 8000, 'house_share_taka' => 1000, 'sitting_kind' => ScheduleSession::KIND_INTERVENTION, 'sort_order' => 210],
            ['label' => 'PRP T/E', 'list_price_taka' => 8000, 'house_share_taka' => 1000, 'sitting_kind' => ScheduleSession::KIND_INTERVENTION, 'sort_order' => 220],
            ['label' => 'PRP hip (single)', 'list_price_taka' => 10000, 'house_share_taka' => 1000, 'sitting_kind' => ScheduleSession::KIND_INTERVENTION, 'sort_order' => 230],
            ['label' => 'PRP hip (both)', 'list_price_taka' => 20000, 'house_share_taka' => 1000, 'sitting_kind' => ScheduleSession::KIND_INTERVENTION, 'sort_order' => 240],
            ['label' => 'C.E.D', 'list_price_taka' => 8000, 'house_share_taka' => 1000, 'sitting_kind' => ScheduleSession::KIND_INTERVENTION, 'sort_order' => 250],
            ['label' => 'I/A', 'list_price_taka' => 8000, 'house_share_taka' => 1000, 'sitting_kind' => ScheduleSession::KIND_INTERVENTION, 'sort_order' => 260],
        ];

        foreach ($rows as $row) {
            FeeCatalogItem::create($row);
        }
    }

    private function seedReferringDoctors(): void
    {
        ReferringDoctor::query()->delete();

        ReferringDoctor::create([
            'name' => 'Dr. Karim (Agrabad)',
            'phone' => '01811000001',
            'specialty' => 'General practice',
        ]);

        ReferringDoctor::create([
            'name' => 'Dr. Sultana (Halishahar)',
            'phone' => '01811000002',
            'specialty' => 'Orthopaedic',
        ]);
    }

    private function seedHr(): void
    {
        Employee::query()->delete();

        $staff = User::withoutGlobalScope(TenantScope::class)
            ->where('email', 'staff@painsolution.local')
            ->first();

        Employee::create([
            'user_id' => $staff?->id,
            'name' => 'Desk Staff',
            'phone' => '01822000001',
            'job_title' => 'Reception',
            'monthly_salary_taka' => 18000,
            'joined_on' => now()->subMonths(6)->toDateString(),
        ]);

        Employee::create([
            'name' => 'Nurse Rina',
            'phone' => '01822000002',
            'job_title' => 'Procedure nurse',
            'monthly_salary_taka' => 22000,
            'joined_on' => now()->subYear()->toDateString(),
        ]);
    }
}
