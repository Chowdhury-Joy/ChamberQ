<?php

namespace Database\Seeders;

use App\Models\Chamber;
use App\Models\Doctor;
use App\Models\Domain;
use App\Models\LabCollectionSlot;
use App\Models\LabTest;
use App\Models\ScheduleSession;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Note: Intentionally omitting WithoutModelEvents because tenant_id is assigned in model events.

        // Super Admin
        User::firstOrCreate(
            ['email' => 'super@demo.com'],
            ['name' => 'Super Admin', 'password' => Hash::make('password'), 'role' => 'super_admin']
        );

        // 1. Seed Solo Doctor Tier
        $soloTenant = Tenant::firstOrCreate(
            ['id' => 'solo'],
            ['plan_tier' => 'solo', 'slot_cap_mode' => 'per_session']
        );
        Domain::firstOrCreate(['domain' => 'solo.localhost'], ['tenant_id' => 'solo']);
        User::firstOrCreate(
            ['email' => 'admin@solo.com'],
            ['name' => 'Solo Admin', 'password' => Hash::make('password'), 'role' => 'tenant_admin', 'tenant_id' => 'solo']
        );

        tenancy()->initialize($soloTenant);

        $soloChamber = Chamber::firstOrCreate(['name' => 'Solo Main Chamber', 'tenant_id' => 'solo']);
        $soloDoctor = Doctor::firstOrCreate(['name' => 'Dr. Solo Example', 'tenant_id' => 'solo']);
        
        ScheduleSession::firstOrCreate(
            ['chamber_id' => $soloChamber->id, 'doctor_id' => $soloDoctor->id, 'day_of_week' => 1],
            ['tenant_id' => 'solo', 'session_name' => 'Morning Shift', 'start_time' => '09:00', 'end_time' => '13:00', 'slot_cap' => 10]
        );

        tenancy()->end();

        // 2. Seed Clinic Tier
        $clinicTenant = Tenant::firstOrCreate(
            ['id' => 'demo'],
            ['plan_tier' => 'clinic', 'slot_cap_mode' => 'per_session']
        );
        Domain::firstOrCreate(['domain' => 'demo.localhost'], ['tenant_id' => 'demo']);
        User::firstOrCreate(
            ['email' => 'admin@demo.com'],
            ['name' => 'Demo Admin', 'password' => Hash::make('password'), 'role' => 'tenant_admin', 'tenant_id' => 'demo']
        );

        tenancy()->initialize($clinicTenant);

        $chamber1 = Chamber::firstOrCreate(['name' => 'Main Clinic', 'tenant_id' => 'demo']);
        $chamber2 = Chamber::firstOrCreate(['name' => 'Branch Clinic', 'tenant_id' => 'demo']);
        
        $doc1 = Doctor::firstOrCreate(['name' => 'Dr. Alpha', 'tenant_id' => 'demo']);
        $doc2 = Doctor::firstOrCreate(['name' => 'Dr. Beta', 'tenant_id' => 'demo']);

        // Doctors sharing a weekday in the same chamber
        ScheduleSession::firstOrCreate(
            ['chamber_id' => $chamber1->id, 'doctor_id' => $doc1->id, 'day_of_week' => 2],
            ['tenant_id' => 'demo', 'session_name' => 'Morning Shift', 'start_time' => '09:00', 'end_time' => '12:00', 'slot_cap' => 15]
        );
        ScheduleSession::firstOrCreate(
            ['chamber_id' => $chamber1->id, 'doctor_id' => $doc2->id, 'day_of_week' => 2],
            ['tenant_id' => 'demo', 'session_name' => 'Morning Shift', 'start_time' => '10:00', 'end_time' => '14:00', 'slot_cap' => 20]
        );

        LabTest::firstOrCreate(['name' => 'Complete Blood Count', 'tenant_id' => 'demo'], ['price' => 500, 'description' => 'Fasting 12 hours']);
        LabCollectionSlot::firstOrCreate(
            ['chamber_id' => $chamber1->id, 'day_of_week' => 2],
            ['tenant_id' => 'demo', 'start_time' => '08:00', 'end_time' => '10:00', 'slot_cap' => 30]
        );

        tenancy()->end();
    }
}
