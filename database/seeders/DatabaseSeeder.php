<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Chamber;
use App\Models\Doctor;
use App\Models\Domain;
use App\Models\LabCollectionSlot;
use App\Models\LabTest;
use App\Models\LiveSession;
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
            'name' => 'Dr. Shamim Ahmed',
            'plan_tier' => 'solo',
            'slot_cap_mode' => 'per_session',
            'contact_phone' => '01333709771',
            'whatsapp_number' => '8801333709771',
            'theme_color' => '#1B6CA8',
            'favicon_url' => '/icons/health-favicon.svg',
            'font_family' => 'Outfit',
            'default_locale' => 'en',
            'tagline' => 'Diabetes and medicine specialist at Belle Vue Hospital, Chattogram — book online, pay at the chamber.',
            'sms_balance' => 47,
        ]);

        Domain::firstOrCreate(['domain' => 'solo.localhost'], ['tenant_id' => 'solo']);

        User::withoutGlobalScope(\App\Scopes\TenantScope::class)->firstOrCreate(['email' => 'admin@solo.com'], [
            'name' => 'Solo Admin', 'password' => Hash::make('password'),
            'role' => User::ROLE_ADMIN, 'tenant_id' => 'solo',
        ]);

        User::withoutGlobalScope(\App\Scopes\TenantScope::class)->firstOrCreate(['email' => 'doctor@solo.com'], [
            'name' => 'Dr. Shamim Ahmed', 'password' => Hash::make('password'),
            'role' => User::ROLE_DOCTOR, 'tenant_id' => 'solo',
        ]);

        // Solo demo = doctor working alone. Keep queue_runner at the default
        // (staff-run); with no staff user, effectiveQueueRunner() falls back to
        // the doctor so demos don't need a second login.
        User::withoutGlobalScope(\App\Scopes\TenantScope::class)
            ->where('tenant_id', 'solo')
            ->where('role', User::ROLE_STAFF)
            ->delete();

        tenancy()->initialize($tenant);

        LiveSession::query()->delete();
        Booking::where('bookable_type', ScheduleSession::class)->delete();
        ScheduleSession::query()->delete();
        Chamber::query()->delete();
        Doctor::query()->delete();

        $doctor = Doctor::create([
            'name' => 'Dr. Shamim Ahmed',
            'practice_type' => Doctor::PRACTICE_GENERAL,
            'qualifications' => 'MBBS, MRCP (London, UK), CCD (Diabetes)',
        ]);

        $chamber = Chamber::create([
            'name' => 'Belle Vue Hospital',
            'address' => '3rd Floor, Room #311, Prabartak Hill, 12/12 O.R. Nizam Road, Panchlaish, Chattogram, Bangladesh',
            'contact' => '01333709771',
            'map_url' => 'https://www.google.com/maps?q=22.3470%2C91.8123',
        ]);

        // Saturday–Thursday morning (closed Friday).
        foreach ([6, 0, 1, 2, 3, 4] as $dayOfWeek) {
            ScheduleSession::create([
                'chamber_id' => $chamber->id,
                'doctor_id' => $doctor->id,
                'day_of_week' => $dayOfWeek,
                'session_name' => 'Morning',
                'start_time' => '11:00',
                'end_time' => '14:00',
                'slot_cap' => 15,
            ]);
        }

        // Sunday, Tuesday, Thursday evening.
        foreach ([0, 2, 4] as $dayOfWeek) {
            ScheduleSession::create([
                'chamber_id' => $chamber->id,
                'doctor_id' => $doctor->id,
                'day_of_week' => $dayOfWeek,
                'session_name' => 'Evening',
                'start_time' => '19:00',
                'end_time' => '21:00',
                'slot_cap' => 12,
            ]);
        }

        // Person-led homepage matching the solo client-site design.
        WebPage::updateOrCreate(['slug' => '/'], [
            'title' => 'Dr. Shamim Ahmed',
            'is_published' => true,
            'content' => [
                ['type' => 'hero', 'data' => [
                    'headline' => "Dr. Shamim\nAhmed",
                    'credentials' => 'MBBS, MRCP (London, UK), CCD (Diabetes)',
                    'role_location' => 'Diabetes & Medicine Specialist · Belle Vue Hospital, Chattogram',
                    'cta_text' => 'Book Appointment',
                    'cta_link' => '/book',
                    'image_url' => 'https://images.unsplash.com/photo-1612349317150-e413f6a5b16d?auto=format&fit=crop&w=1200&q=80',
                ]],
                ['type' => 'condition_library', 'data' => [
                    'heading' => 'Conditions I Treat',
                    'conditions' => [
                        [
                            'name' => 'Diabetes Care',
                            'description' => 'Structured plans for type 2 diabetes, blood sugar control, and complications screening.',
                            'features' => [
                                'HbA1c review and target setting',
                                'Medicine and insulin counselling',
                                'Diet and lifestyle guidance',
                                'Foot and kidney risk checks',
                            ],
                        ],
                        [
                            'name' => 'General Medicine',
                            'description' => 'Everyday adult medicine with clear follow-up between visits.',
                            'features' => [
                                'Fever, infection & flu care',
                                'Fatigue and unexplained symptoms',
                                'Routine health check-ups',
                                'Medication review',
                            ],
                        ],
                        [
                            'name' => 'Chronic Disease',
                            'description' => 'Steady monitoring for conditions that need long-term control.',
                            'features' => [
                                'High blood pressure',
                                'Thyroid disorders',
                                'Cholesterol & metabolic risk',
                                'Heart risk assessment',
                            ],
                        ],
                    ],
                ]],
                ['type' => 'about_doctor', 'data' => [
                    'heading' => 'Meet Dr. Shamim Ahmed',
                    'subheadline' => 'Diabetes and medicine specialist dedicated to clear, practical care you can follow at home.',
                    'cta_text' => 'Book Appointment',
                    'cta_link' => '/book',
                    'highlights' => [
                        [
                            'title' => 'Qualifications',
                            'description' => 'MBBS, MRCP (London, UK), and CCD (Diabetes).',
                        ],
                        [
                            'title' => 'Experience',
                            'description' => 'Former Clinical Associate at Sengkang General Hospital, Singapore.',
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
                            'question' => 'Where is the chamber?',
                            'answer' => 'Belle Vue Hospital, 3rd Floor Room #311, Prabartak Hill, 12/12 O.R. Nizam Road, Panchlaish, Chattogram.',
                        ],
                        [
                            'question' => 'What are your consultation hours?',
                            'answer' => 'Saturday–Thursday morning 11:00 am – 2:00 pm. Sunday, Tuesday, and Thursday evening 7:00 pm – 9:00 pm. Closed on Friday. Book a serial online before you arrive.',
                        ],
                        [
                            'question' => 'How do I book or ask about my serial?',
                            'answer' => 'Book online through this site, or call the serial desk on 01333-709771 or the hospital hotline on 01969-901166.',
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
                            'answer' => 'Diabetes and general medicine for adults — blood pressure, thyroid issues, infections, and ongoing chronic disease follow-up.',
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

        $this->call(SoloDemoSeeder::class);

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
            'favicon_url' => '/icons/health-favicon.svg',
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
            'map_url' => 'https://www.google.com/maps?q=23.8069%2C90.3687',
        ]);
        $branch = Chamber::firstOrCreate(['name' => 'Uttara Branch'], [
            'address' => 'House 15, Sector 7, Uttara, Dhaka 1230',
            'contact' => '029876544',
            'map_url' => 'https://www.google.com/maps?q=23.8759%2C90.3795',
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

        // Facility-led landing page — same visual language as solo, clinic content.
        WebPage::updateOrCreate(['slug' => '/'], [
            'title' => 'Shefa Diagnostic & Consultation Centre',
            'is_published' => true,
            'content' => [
                ['type' => 'hero', 'data' => [
                    'headline' => 'Consultants and diagnostics, one roof',
                    'subheadline' => 'Book a doctor or lab serial online and follow the queue from your phone.',
                    'cta_text' => 'Book Appointment',
                    'cta_link' => '/book',
                    'secondary_cta_text' => 'Patient’s Portal',
                    'secondary_cta_link' => '/portal',
                    'image_url' => 'https://images.unsplash.com/photo-1519494026892-80bbd2d6fd0d?auto=format&fit=crop&w=1800&q=80',
                ]],
                ['type' => 'condition_library', 'data' => [
                    'heading' => 'What we help with',
                    'conditions' => [
                        [
                            'name' => 'Cardiology',
                            'description' => 'Heart check-ups, ECG review, and ongoing cardiac care.',
                            'features' => ['ECG & consult', 'Follow-up serials', 'Clear next steps'],
                        ],
                        [
                            'name' => 'Internal medicine',
                            'description' => 'Fever, diabetes, blood pressure, and everyday adult medicine.',
                            'features' => ['Same-day serials', 'Medicine review', 'Lab when needed'],
                        ],
                        [
                            'name' => 'Paediatrics',
                            'description' => 'Gentle care for children — cough, fever, growth, and vaccines.',
                            'features' => ['Child-friendly slots', 'Parent guidance', 'Fast lab links'],
                        ],
                        [
                            'name' => 'Diagnostics',
                            'description' => 'Blood and urine tests with clear turnaround times.',
                            'features' => ['CBC & sugar', 'Lipid & thyroid', 'Same-day reports'],
                        ],
                    ],
                ]],
                ['type' => 'doctor_grid', 'data' => [
                    'heading' => 'Our Consultants',
                    'subheadline' => 'Experienced physicians across key specialties — book the doctor you need.',
                ]],
                ['type' => 'about_facility', 'data' => [
                    'heading' => 'About Shefa',
                    'mission_statement' => 'Shefa has served Dhaka since 2009 with consultant appointments and a full diagnostic laboratory under one roof. Book online, get a serial, and spend less time waiting.',
                    'gallery' => [
                        [
                            'title' => 'Consultation rooms',
                            'image_url' => 'https://images.unsplash.com/photo-1516549655169-df83a0774514?auto=format&fit=crop&w=900&q=80',
                        ],
                        [
                            'title' => 'Diagnostic lab',
                            'image_url' => 'https://images.unsplash.com/photo-1582719471384-894fbb16e074?auto=format&fit=crop&w=900&q=80',
                        ],
                    ],
                ]],
                ['type' => 'service_matrix', 'data' => [
                    'heading' => 'Why patients choose us',
                    'description' => 'Online serials, live queue updates, and diagnostics at two Dhaka branches.',
                    'items' => [
                        ['title' => 'Online serial', 'description' => 'Book from home and track the queue live — no waiting-room guessing.'],
                        ['title' => 'Two branches', 'description' => 'Mirpur and Uttara, both with consultants and lab collection.'],
                        ['title' => 'Same-day reports', 'description' => 'Most routine tests are reported the same day.'],
                        ['title' => 'Pay at chamber', 'description' => 'No online payment stress — settle at reception after your visit.'],
                    ],
                ]],
                ['type' => 'location_hours', 'data' => [
                    'heading' => 'Our locations',
                    'locations' => [
                        [
                            'name' => 'Mirpur Main Branch',
                            'address' => 'Plot 7, Block C, Mirpur 10, Dhaka 1216',
                            'operating_hours' => 'Sat–Thu: 9:00 AM – 8:00 PM',
                            'phone' => '029876543',
                            'google_maps_url' => 'https://www.google.com/maps?q=23.8069%2C90.3687',
                        ],
                        [
                            'name' => 'Uttara Branch',
                            'address' => 'House 15, Sector 7, Uttara, Dhaka 1230',
                            'operating_hours' => 'Sat–Thu: 9:00 AM – 8:00 PM',
                            'phone' => '029876544',
                            'google_maps_url' => 'https://www.google.com/maps?q=23.8759%2C90.3795',
                        ],
                    ],
                ]],
                ['type' => 'testimonials', 'data' => [
                    'heading' => 'What our patients say',
                    'items' => [
                        [
                            'quote' => 'I booked a cardiology serial from home, checked the queue on my phone, and was in and out without the old waiting chaos.',
                            'name' => 'Karim Hossain',
                            'label' => 'Verified Patient — Mirpur',
                        ],
                        [
                            'quote' => 'Lab and doctor on the same day at Uttara. The serial made it clear when to arrive.',
                            'name' => 'Nusrat Jahan',
                            'label' => 'Verified Patient — Uttara',
                        ],
                    ],
                ]],
                ['type' => 'faq', 'data' => [
                    'heading' => 'Everything you need to know',
                    'faqs' => [
                        [
                            'question' => 'How do I book a doctor or lab test?',
                            'answer' => 'Tap Book Appointment, choose a consultation or lab collection, pick a time, and enter your name and phone. You get a serial ticket you can reopen anytime.',
                        ],
                        [
                            'question' => 'Do I pay online?',
                            'answer' => 'No. Pay at the chamber after your visit. Cash and common mobile financial services are accepted at reception.',
                        ],
                        [
                            'question' => 'Can I book labs without seeing a doctor?',
                            'answer' => 'Yes. Choose lab collection in the booking flow, pick your tests and a collection slot, then come at your serial time.',
                        ],
                        [
                            'question' => 'Which branch should I choose?',
                            'answer' => 'Mirpur Main and Uttara both offer consultants and diagnostics. Pick the branch that matches the doctor or slot you need.',
                        ],
                    ],
                ]],
            ],
        ]);

        tenancy()->end();
    }
}
