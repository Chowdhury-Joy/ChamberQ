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
            'name' => "Dr. Mahfuz's Care",
            'plan_tier' => 'solo',
            'slot_cap_mode' => 'per_session',
            'contact_phone' => '01712345678',
            'whatsapp_number' => '8801712345678',
            'theme_color' => '#30A9E5',
            'font_family' => 'Outfit',
            'default_locale' => 'en',
            'tagline' => 'Consultant physician care in Dhanmondi — book online, pay at the chamber.',
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

        // Person-led homepage matching the solo client-site design.
        WebPage::updateOrCreate(['slug' => '/'], [
            'title' => "Dr. Mahfuz's Care",
            'is_published' => true,
            'content' => [
                ['type' => 'hero', 'data' => [
                    'headline' => 'Dr. Mahfuzur Rahman',
                    'credentials' => 'MBBS, FCPS (Medicine)',
                    'role_location' => 'Consultant Physician, Dhanmondi',
                    'cta_text' => 'Book Appointment',
                    'cta_link' => '/book',
                    'image_url' => 'https://images.unsplash.com/photo-1612349317150-e413f6a5b16d?auto=format&fit=crop&w=1200&q=80',
                ]],
                ['type' => 'condition_library', 'data' => [
                    'heading' => 'Conditions I Treat',
                    'conditions' => [
                        [
                            'name' => 'General Medicine',
                            'description' => 'Everyday adult medicine with clear plans you can follow at home between visits.',
                            'features' => [
                                'Fever, infection & flu care',
                                'Fatigue and unexplained symptoms',
                                'Routine health check-ups',
                                'Medication review & counselling',
                            ],
                        ],
                        [
                            'name' => 'Chronic Disease Care',
                            'description' => 'Long-term control for conditions that need steady monitoring, not rushed visits.',
                            'features' => [
                                'Type 2 diabetes management',
                                'High blood pressure control',
                                'Thyroid disorders',
                                'Cholesterol & metabolic risk',
                            ],
                        ],
                        [
                            'name' => 'Heart & Respiratory',
                            'description' => 'Focused evaluation when chest symptoms, breathlessness, or heart risk need a physician’s eye.',
                            'features' => [
                                'Chest pain evaluation',
                                'Asthma & COPD follow-up',
                                'Palpitations & rhythm concerns',
                            ],
                        ],
                    ],
                ]],
                ['type' => 'about_doctor', 'data' => [
                    'heading' => 'Meet Dr. Mahfuzur Rahman',
                    'subheadline' => 'Dedicated to delivering compassionate, personalized care that puts your health and well-being first.',
                    'cta_text' => 'Book Appointment',
                    'cta_link' => '/book',
                    'highlights' => [
                        [
                            'title' => 'Study',
                            'description' => 'FCPS (Medicine) with clinical training at Dhaka Medical College and postgraduate medicine at BSMMU (PG Hospital).',
                        ],
                        [
                            'title' => 'Awards & Honors',
                            'description' => 'Recognized for patient teaching, diabetes clinic leadership, and consistent chamber practice in Dhanmondi.',
                        ],
                    ],
                ]],
                ['type' => 'video_gallery', 'data' => [
                    'heading' => 'Latest Educational Videos',
                    'follow_text' => 'Follow for More',
                    'follow_url' => 'https://www.youtube.com/@Osmosis',
                    'videos' => [
                        [
                            'title' => 'Diabetes mellitus explained',
                            'type' => 'link',
                            'video_url' => 'https://www.youtube.com/watch?v=-B-RVybvffU',
                        ],
                        [
                            'title' => 'Hypertension: causes and treatment',
                            'type' => 'link',
                            'video_url' => 'https://www.youtube.com/watch?v=Qm5kB5X70oA',
                        ],
                        [
                            'title' => 'Cholesterol numbers — Mayo Clinic',
                            'type' => 'link',
                            'video_url' => 'https://www.youtube.com/watch?v=SMcIfrki97k',
                        ],
                        [
                            'title' => 'Asthma medicines explained',
                            'type' => 'link',
                            'video_url' => 'https://www.youtube.com/watch?v=h6DVjzDcHMA',
                        ],
                    ],
                ]],
                ['type' => 'testimonials', 'data' => [
                    'heading' => 'What My Patients Say About My Treatments',
                    'items' => [
                        [
                            'quote' => 'He explained my diabetes plan in plain language and never rushed me. Serial booking from my phone means I no longer waste a morning in the waiting room.',
                            'name' => 'Rashida Begum',
                            'label' => 'Verified Patient',
                        ],
                        [
                            'quote' => 'My blood pressure finally settled after years of guesswork. Follow-ups are clear, and I can check my queue status before I leave home.',
                            'name' => 'Karim Hossain',
                            'label' => 'Verified Patient',
                        ],
                        [
                            'quote' => 'Thoughtful, careful medicine. He reviewed every medicine I was taking and cut the ones I did not need. I trust this chamber completely.',
                            'name' => 'Nasreen Akhtar',
                            'label' => 'Verified Patient',
                        ],
                        [
                            'quote' => 'From the first visit I felt heard. The online serial and live queue updates made the whole process calm instead of stressful.',
                            'name' => 'Imran Chowdhury',
                            'label' => 'Verified Patient',
                        ],
                        [
                            'quote' => 'Clear diagnosis, clear next steps, and a doctor who treats you like a person — not a ticket number.',
                            'name' => 'Farzana Islam',
                            'label' => 'Verified Patient',
                        ],
                    ],
                ]],
                ['type' => 'faq', 'data' => [
                    'heading' => 'Everything You Need To Know',
                    'faqs' => [
                        [
                            'question' => 'Where is your chamber located?',
                            'answer' => 'House 42, Road 9/A, Dhanmondi, Dhaka 1209. The chamber is easy to reach by rickshaw, CNG, or private car. Call ahead if you need landmark help.',
                        ],
                        [
                            'question' => 'What are your consultation hours?',
                            'answer' => 'Monday mornings 9:00 am – 1:00 pm and Wednesday evenings 5:00 pm – 9:00 pm. Book a serial online before you arrive.',
                        ],
                        [
                            'question' => 'How many patients do you see per session?',
                            'answer' => 'Each session has a fixed serial cap so visits stay unhurried. Once the cap is reached, later patients are asked to book the next available session.',
                        ],
                        [
                            'question' => 'Do I need an appointment or can I walk in?',
                            'answer' => 'Online serial booking is preferred. Limited walk-ins may be taken if slots remain, but booking ahead guarantees your place in the queue.',
                        ],
                        [
                            'question' => 'What types of conditions do you treat?',
                            'answer' => 'General medicine for adults — diabetes, hypertension, thyroid issues, infections, respiratory complaints, and ongoing chronic disease follow-up.',
                        ],
                        [
                            'question' => 'Do you offer telemedicine or online consultations?',
                            'answer' => 'Consultations are in-chamber. Booking and live queue tracking are online so you spend less time waiting on site.',
                        ],
                        [
                            'question' => 'What should I bring to my first visit?',
                            'answer' => 'Bring a photo ID, previous prescriptions, recent lab reports, and a list of medicines you currently take — including supplements.',
                        ],
                        [
                            'question' => 'What payment methods do you accept?',
                            'answer' => 'Pay at the chamber after your visit. Cash and common mobile financial services are accepted at reception.',
                        ],
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
