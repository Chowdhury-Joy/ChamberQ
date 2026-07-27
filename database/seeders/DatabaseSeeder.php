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
use App\Models\WebPage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Note: intentionally NOT using WithoutModelEvents — it disables the
        // `creating` hook that assigns tenant_id, so every scoped insert would
        // fail its NOT NULL constraint.

        User::withoutGlobalScope(\App\Scopes\TenantScope::class)->firstOrCreate(
            ['email' => 'super@demo.com'],
            ['name' => 'Super Admin', 'password' => Hash::make('password'), 'role' => 'super_admin']
        );

        $this->seedSoloTenant();
        $this->seedClinicTenant();
    }

    private function seedSoloTenant(): void
    {
        $tenant = Tenant::updateOrCreate(['id' => 'solo'], [
            'name' => 'Dr. Rahman Chamber',
            'plan_tier' => 'solo',
            'slot_cap_mode' => 'per_session',
            'contact_phone' => '01712345678',
            'whatsapp_number' => '8801712345678',
            'theme_color' => Tenant::DEFAULT_THEME_COLOR,
            'font_family' => 'Outfit',
            'default_locale' => 'en',
            'tagline' => 'Consultant physician care with online serial booking',
        ]);

        Domain::firstOrCreate(['domain' => 'solo.localhost'], ['tenant_id' => 'solo']);

        User::withoutGlobalScope(\App\Scopes\TenantScope::class)->firstOrCreate(['email' => 'admin@solo.com'], [
            'name' => 'Solo Admin', 'password' => Hash::make('password'),
            'role' => User::ROLE_ADMIN, 'tenant_id' => 'solo',
        ]);

        User::withoutGlobalScope(\App\Scopes\TenantScope::class)->firstOrCreate(['email' => 'doctor@solo.com'], [
            'name' => 'Solo Doctor', 'password' => Hash::make('password'),
            'role' => User::ROLE_DOCTOR, 'tenant_id' => 'solo',
        ]);

        User::withoutGlobalScope(\App\Scopes\TenantScope::class)->firstOrCreate(['email' => 'staff@solo.com'], [
            'name' => 'Solo Staff', 'password' => Hash::make('password'),
            'role' => User::ROLE_STAFF, 'tenant_id' => 'solo',
        ]);

        tenancy()->initialize($tenant);

        $chamber = Chamber::firstOrCreate(['name' => 'Dhanmondi Chamber'], [
            'address' => 'House 42, Road 9/A, Dhanmondi, Dhaka 1209',
            'contact' => '01712345678',
            'latitude' => '23.7461',
            'longitude' => '90.3742',
        ]);

        $doctor = Doctor::firstOrCreate(['name' => 'Dr. Mahfuzur Rahman']);

        ScheduleSession::firstOrCreate(
            ['chamber_id' => $chamber->id, 'doctor_id' => $doctor->id, 'day_of_week' => 1],
            ['session_name' => 'Morning', 'start_time' => '09:00', 'end_time' => '13:00', 'slot_cap' => 10]
        );
        ScheduleSession::firstOrCreate(
            ['chamber_id' => $chamber->id, 'doctor_id' => $doctor->id, 'day_of_week' => 3],
            ['session_name' => 'Evening', 'start_time' => '17:00', 'end_time' => '21:00', 'slot_cap' => 12]
        );

        // A person-led landing page, per the solo tier's emphasis.
        WebPage::updateOrCreate(['slug' => '/'], [
            'title' => 'Dr. Rahman Chamber',
            'is_published' => true,
            'content' => [
                ['type' => 'hero', 'data' => [
                    'headline' => 'Care that respects your time',
                    'subheadline' => 'Book a serial online and follow the live queue from your phone — pay at the chamber.',
                    'cta_text' => 'Book Appointment',
                    'cta_link' => '/book',
                    'image_url' => 'https://images.unsplash.com/photo-1631217868264-e5b90bb7e133?auto=format&fit=crop&w=1800&q=80',
                ]],
                ['type' => 'rich_text', 'data' => [
                    'content' => '<h2>About</h2><p>Dr. Mahfuzur Rahman has been practising internal medicine '
                        . 'in Dhaka for over fifteen years, with a focus on diabetes, hypertension and general '
                        . 'medicine. Appointments are by serial — book online and track the queue from your phone '
                        . 'instead of waiting at the chamber.</p>',
                ]],
                ['type' => 'doctor_grid', 'data' => [
                    'heading' => 'Your Doctor',
                    'subheadline' => 'Book a consultation with Dr. Mahfuzur Rahman.',
                ]],
                ['type' => 'service_matrix', 'data' => [
                    'heading' => 'Why patients choose this chamber',
                    'description' => 'Simple online serials and a live queue on your phone.',
                    'items' => [
                        ['title' => 'Online serial', 'description' => 'Book from home and track the queue live.'],
                        ['title' => 'Live waiting room screen', 'description' => 'See who is being called in the chamber.'],
                        ['title' => 'Pay at the clinic', 'description' => 'No online payment — settle at reception.'],
                    ],
                ]],
            ],
        ]);

        tenancy()->end();
    }

    private function seedClinicTenant(): void
    {
        $tenant = Tenant::updateOrCreate(['id' => 'demo'], [
            'name' => 'Shefa Diagnostic & Consultation Centre',
            'plan_tier' => 'clinic',
            'slot_cap_mode' => 'per_session',
            'contact_phone' => '029876543',
            'whatsapp_number' => '8801812345678',
            'theme_color' => Tenant::DEFAULT_THEME_COLOR,
            'font_family' => 'Outfit',
            'default_locale' => 'en',
            'tagline' => 'Diagnostics and consultations under one roof',
        ]);

        Domain::firstOrCreate(['domain' => 'demo.localhost'], ['tenant_id' => 'demo']);

        User::withoutGlobalScope(\App\Scopes\TenantScope::class)->firstOrCreate(['email' => 'admin@demo.com'], [
            'name' => 'Demo Admin', 'password' => Hash::make('password'),
            'role' => User::ROLE_ADMIN, 'tenant_id' => 'demo',
        ]);

        tenancy()->initialize($tenant);

        $main = Chamber::firstOrCreate(['name' => 'Mirpur Main Branch'], [
            'address' => 'Plot 7, Block C, Mirpur 10, Dhaka 1216',
            'contact' => '029876543',
            'latitude' => '23.8069',
            'longitude' => '90.3687',
        ]);
        $branch = Chamber::firstOrCreate(['name' => 'Uttara Branch'], [
            'address' => 'House 15, Sector 7, Uttara, Dhaka 1230',
            'contact' => '029876544',
            'latitude' => '23.8759',
            'longitude' => '90.3795',
        ]);

        $cardiologist = Doctor::firstOrCreate(['name' => 'Dr. Nasreen Akhter']);
        $physician = Doctor::firstOrCreate(['name' => 'Dr. Kamrul Hasan']);
        $paediatrician = Doctor::firstOrCreate(['name' => 'Dr. Sabbir Ahmed']);

        // Two doctors deliberately share a weekday in the same chamber so
        // multi-doctor scheduling conflicts surface in development.
        ScheduleSession::firstOrCreate(
            ['chamber_id' => $main->id, 'doctor_id' => $cardiologist->id, 'day_of_week' => 2],
            ['session_name' => 'Morning', 'start_time' => '09:00', 'end_time' => '12:00', 'slot_cap' => 15]
        );
        ScheduleSession::firstOrCreate(
            ['chamber_id' => $main->id, 'doctor_id' => $physician->id, 'day_of_week' => 2],
            ['session_name' => 'Evening', 'start_time' => '17:00', 'end_time' => '20:00', 'slot_cap' => 20]
        );
        ScheduleSession::firstOrCreate(
            ['chamber_id' => $branch->id, 'doctor_id' => $paediatrician->id, 'day_of_week' => 4],
            ['session_name' => 'Morning', 'start_time' => '10:00', 'end_time' => '14:00', 'slot_cap' => 18]
        );

        // Preparation instructions live in their own field — they are the text a
        // patient re-reads the night before, not marketing copy.
        $tests = [
            ['name' => 'Complete Blood Count (CBC)', 'price' => 500,
             'preparation_instructions' => 'No special preparation needed.',
             'sample_type' => 'Blood', 'turnaround_time' => 'Same day', 'display_order' => 1],
            ['name' => 'Fasting Blood Sugar', 'price' => 300,
             'preparation_instructions' => 'Do not eat or drink anything except water for 12 hours before your sample is taken.',
             'sample_type' => 'Blood', 'turnaround_time' => 'Same day', 'display_order' => 2],
            ['name' => 'Lipid Profile', 'price' => 1200,
             'preparation_instructions' => '12 hours fasting required. Water is allowed.',
             'sample_type' => 'Blood', 'turnaround_time' => '24 hours', 'display_order' => 3],
            ['name' => 'Thyroid Function Test (TSH)', 'price' => 900,
             'preparation_instructions' => 'No fasting needed. Tell staff about any thyroid medication you take.',
             'sample_type' => 'Blood', 'turnaround_time' => '48 hours', 'display_order' => 4],
            ['name' => 'Urine R/E', 'price' => 250,
             'preparation_instructions' => 'A morning midstream sample is preferred.',
             'sample_type' => 'Urine', 'turnaround_time' => 'Same day', 'display_order' => 5],
        ];

        foreach ($tests as $test) {
            LabTest::firstOrCreate(['name' => $test['name']], $test);
        }

        LabCollectionSlot::firstOrCreate(
            ['chamber_id' => $main->id, 'day_of_week' => 2],
            ['start_time' => '08:00', 'end_time' => '11:00', 'slot_cap' => 30]
        );
        LabCollectionSlot::firstOrCreate(
            ['chamber_id' => $main->id, 'day_of_week' => 4],
            ['start_time' => '08:00', 'end_time' => '11:00', 'slot_cap' => 30]
        );

        // Facility-led landing page, per the clinic tier's emphasis.
        WebPage::updateOrCreate(['slug' => '/'], [
            'title' => 'Shefa Diagnostic & Consultation Centre',
            'is_published' => true,
            'content' => [
                ['type' => 'hero', 'data' => [
                    'headline' => 'Consultants and diagnostics, one roof',
                    'subheadline' => 'Book a doctor or lab serial online and follow the queue from your phone.',
                    'cta_text' => 'Book Appointment',
                    'cta_link' => '/book',
                    'image_url' => 'https://images.unsplash.com/photo-1519494026892-80bbd2d6fd0d?auto=format&fit=crop&w=1800&q=80',
                ]],
                ['type' => 'rich_text', 'data' => [
                    'content' => '<h2>About us</h2><p>Shefa has served Dhaka since 2009, offering consultant '
                        . 'appointments alongside a full diagnostic laboratory. Book a doctor or a test online, '
                        . 'get a serial number, and follow the queue from your phone.</p>',
                ]],
                ['type' => 'doctor_grid', 'data' => [
                    'heading' => 'Our Consultants',
                    'subheadline' => 'Experienced physicians across key specialties.',
                ]],
                ['type' => 'service_matrix', 'data' => [
                    'heading' => 'Why choose us',
                    'description' => 'Book online, get a serial, and follow the queue from your phone.',
                    'items' => [
                        ['title' => 'Online serial', 'description' => 'Book from home and track the queue live — no waiting room guessing.'],
                        ['title' => 'Two branches', 'description' => 'Mirpur and Uttara, both with full diagnostic facilities.'],
                        ['title' => 'Same-day reports', 'description' => 'Most routine tests are reported the same day.'],
                    ],
                ]],
            ],
        ]);

        tenancy()->end();
    }
}
