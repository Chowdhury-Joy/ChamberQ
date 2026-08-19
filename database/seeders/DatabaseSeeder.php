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
use App\Support\SeedAccounts;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Note: intentionally NOT using WithoutModelEvents — it disables the
        // `creating` hook that assigns tenant_id, so every scoped insert would
        // fail its NOT NULL constraint.

        SeedAccounts::refuseProduction();

        SeedAccounts::upsert(
            ['email' => 'super@demo.com'],
            ['name' => 'Super Admin', 'role' => 'super_admin'],
            'pass',
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

        SeedAccounts::upsert(
            ['email' => 'admin@solo.com'],
            ['name' => 'Solo Owner', 'role' => User::ROLE_ADMIN, 'tenant_id' => 'solo'],
            'pass',
        );

        SeedAccounts::upsert(
            ['email' => 'support@solo.chamberq.internal'],
            ['name' => 'ChamberQ Support', 'role' => User::ROLE_HELPER, 'tenant_id' => 'solo'],
            'pass',
        );

        SeedAccounts::upsert(
            ['email' => 'doctor@solo.com'],
            ['name' => 'Dr. Shamim Ahmed', 'role' => User::ROLE_DOCTOR, 'tenant_id' => 'solo'],
            'pass',
        );

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
            'default_fee_taka' => 800,
            'extra_fees' => [
                ['label' => 'Follow-up', 'amount' => 500],
                ['label' => 'Review with reports', 'amount' => 600],
            ],
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
                ['type' => 'trust_bar', 'data' => [
                    'badges' => [
                        ['text_badge' => 'MRCP (London)'],
                        ['text_badge' => 'CCD — Diabetes'],
                        ['text_badge' => 'Belle Vue Hospital, Chattogram'],
                        ['text_badge' => 'Online serial · pay at chamber'],
                    ],
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
                        [
                            'name' => 'Thyroid Care',
                            'description' => 'Clear plans for under-active and over-active thyroid, with simple follow-up.',
                            'features' => [
                                'TSH review and dose adjustment',
                                'Pregnancy-related thyroid checks',
                                'Symptom tracking between visits',
                                'When to repeat blood tests',
                            ],
                        ],
                        [
                            'name' => 'Infections & Fever',
                            'description' => 'Same-week care for cough, flu, urine infection, and unexplained fever.',
                            'features' => [
                                'Chest and throat examination',
                                'When antibiotics are needed',
                                'Home isolation advice',
                                'Red-flag symptoms to watch',
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
                        [
                            'title' => 'Chamber',
                            'description' => 'Belle Vue Hospital, Room 311 — Saturday to Thursday mornings, plus selected evenings.',
                        ],
                    ],
                ]],
                ['type' => 'patient_journey', 'data' => [
                    'heading' => 'How a visit works',
                    'steps' => [
                        [
                            'step_number' => '01',
                            'title' => 'Book a serial',
                            'description' => 'Pick a morning or evening sitting from your phone. No payment until you see the doctor.',
                        ],
                        [
                            'step_number' => '02',
                            'title' => 'Watch the queue',
                            'description' => 'The ticket shows when to leave home. Come around your turn — not two hours early.',
                        ],
                        [
                            'step_number' => '03',
                            'title' => 'Consult in chamber',
                            'description' => 'Bring old prescriptions and recent reports. Pay at the desk after the visit.',
                        ],
                        [
                            'step_number' => '04',
                            'title' => 'Follow the plan',
                            'description' => 'You leave with medicines, tests if needed, and a date to come back.',
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
                        [
                            'title' => 'Thyroid disorders explained',
                            'type' => 'link',
                            'video_url' => 'https://www.youtube.com/watch?v=Z5VyU3eEZ8E',
                        ],
                        [
                            'title' => 'Diabetic kidney disease — Osmosis',
                            'type' => 'link',
                            'video_url' => 'https://www.youtube.com/watch?v=Y8HIFRPU6pM',
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
                        [
                            'quote' => 'My thyroid dose finally made sense. He wrote the next blood-test date on the prescription so I did not have to guess.',
                            'name' => 'Sultana Ahmed',
                            'label' => 'Verified Patient',
                        ],
                        [
                            'quote' => 'Evening serials on Tuesday saved me a day off work. The queue on my phone meant I arrived just in time.',
                            'name' => 'Rafiq Islam',
                            'label' => 'Verified Patient',
                        ],
                    ],
                ]],
                ['type' => 'location_hours', 'data' => [
                    'heading' => 'Chamber location & hours',
                    'address' => '3rd Floor, Room #311, Prabartak Hill, 12/12 O.R. Nizam Road, Panchlaish, Chattogram',
                    'operating_hours' => 'Sat–Thu 11:00 am – 2:00 pm · Sun, Tue, Thu 7:00 pm – 9:00 pm · Closed Friday',
                    'phone' => '01333-709771',
                    'email' => 'admin@solo.com',
                    'google_maps_url' => 'https://www.google.com/maps?q=22.3470%2C91.8123',
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
                        [
                            'question' => 'How much is the consultation fee?',
                            'answer' => 'A new visit is ৳800. Follow-up is ৳500. Review with reports is ৳600. Pay at the desk after you see the doctor.',
                        ],
                        [
                            'question' => 'Can I bring family on the same phone number?',
                            'answer' => 'Yes. Book separately for each person. At the desk we can keep household members on the same phone so history stays together.',
                        ],
                    ],
                ]],
                ['type' => 'cta_banner', 'data' => [
                    'headline' => 'Need a serial this week? Book from your phone.',
                    'subheadline' => 'Pick a sitting, watch the live queue, and pay at Belle Vue after the visit.',
                    'cta_text' => 'Book Appointment',
                    'cta_link' => '/book',
                    'trust_phone' => '01333-709771',
                    'trust_address' => 'Belle Vue Hospital, Room 311, Panchlaish',
                ]],
            ],
        ]);

        $this->call(SoloDemoSeeder::class);

        tenancy()->end();
    }

    private function seedClinicTenant(): void
    {
        // Clinic demo mirrors public/previews/clireo-homepage.html (CBPH).
        $tenant = Tenant::updateOrCreate(['id' => 'demo'], [
            'name' => 'Chattogram Best Physiotherapy Hospital',
            'plan_tier' => 'clinic',
            'slot_cap_mode' => 'per_session',
            'contact_phone' => '01630-078675',
            'whatsapp_number' => '8801630078675',
            'theme_color' => '#1B2978',
            'logo_url' => '/images/cbph-logo.png',
            'favicon_url' => '/images/cbph-logo.png',
            'font_family' => 'Golos Text',
            'default_locale' => 'en',
            'tagline' => 'Chattogram Best Physiotherapy Hospital — specialized rehab in Panchlaish, Chattogram.',
        ]);

        Domain::firstOrCreate(['domain' => 'demo.localhost'], ['tenant_id' => 'demo']);

        SeedAccounts::upsert(
            ['email' => 'admin@demo.com'],
            ['name' => 'Demo Owner', 'role' => User::ROLE_ADMIN, 'tenant_id' => 'demo'],
            'pass',
        );

        SeedAccounts::upsert(
            ['email' => 'support@demo.chamberq.internal'],
            ['name' => 'ChamberQ Support', 'role' => User::ROLE_HELPER, 'tenant_id' => 'demo'],
            'pass',
        );

        tenancy()->initialize($tenant);

        $main = Chamber::firstOrCreate(['name' => 'Panchlaish Clinic'], [
            'address' => '553 O.R. Nizam Road, GEC, Panchlaish, Chattogram',
            'contact' => '01630-078675',
            'map_url' => 'https://www.google.com/maps?q=22.3594%2C91.8215',
        ]);
        $main->update([
            'address' => '553 O.R. Nizam Road, GEC, Panchlaish, Chattogram',
            'contact' => '01630-078675',
            'map_url' => 'https://www.google.com/maps?q=22.3594%2C91.8215',
        ]);
        $branch = Chamber::firstOrCreate(['name' => 'GEC Therapy Suite'], [
            'address' => '553 O.R. Nizam Road, GEC, Panchlaish, Chattogram',
            'contact' => '01882-373894',
            'map_url' => 'https://www.google.com/maps?q=22.3594%2C91.8215',
        ]);

        // Migrate older Shefa demo doctor names when re-seeding an existing DB.
        $doctorSeeds = [
            ['aliases' => ['Dr. Antar Das', 'Dr. Nasreen Akhter'], 'name' => 'Dr. Antar Das', 'qualifications' => 'Consultant Physiotherapist, MPT (Neurology)'],
            ['aliases' => ['Batia Nahar Ahsan', 'Dr. Kamrul Hasan'], 'name' => 'Batia Nahar Ahsan', 'qualifications' => 'Senior Physiotherapist, Female Dept.'],
            ['aliases' => ['Dr. Mohammad Golam Eazdani', 'Dr. Sabbir Ahmed'], 'name' => 'Dr. Mohammad Golam Eazdani', 'qualifications' => 'Clinical Physiotherapist'],
        ];
        $seededDoctors = [];
        foreach ($doctorSeeds as $seed) {
            $doctor = Doctor::whereIn('name', $seed['aliases'])->first() ?? new Doctor;
            $doctor->fill(['name' => $seed['name'], 'qualifications' => $seed['qualifications']])->save();
            $seededDoctors[] = $doctor;
        }
        [$antar, $batia, $eazdani] = $seededDoctors;

        // Two doctors deliberately share a weekday in the same chamber so
        // multi-doctor scheduling conflicts surface in development.
        ScheduleSession::firstOrCreate(
            ['chamber_id' => $main->id, 'doctor_id' => $antar->id, 'day_of_week' => 2],
            ['session_name' => 'Morning', 'start_time' => '09:00', 'end_time' => '12:00', 'slot_cap' => 15]
        );
        ScheduleSession::firstOrCreate(
            ['chamber_id' => $main->id, 'doctor_id' => $batia->id, 'day_of_week' => 2],
            ['session_name' => 'Evening', 'start_time' => '15:00', 'end_time' => '20:00', 'slot_cap' => 20]
        );
        ScheduleSession::firstOrCreate(
            ['chamber_id' => $branch->id, 'doctor_id' => $eazdani->id, 'day_of_week' => 4],
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

        // Clinic landing page — 1:1 Clireo HTML section order & CBPH copy.
        // Only intentional delta vs HTML: hero right side is a live booking form.
        WebPage::updateOrCreate(['slug' => '/'], [
            'title' => 'Chattogram Best Physiotherapy Hospital',
            'is_published' => true,
            'content' => [
                ['type' => 'hero', 'data' => [
                    'headline' => 'Where every recovery matters',
                    'subheadline' => 'Specialized physiotherapy and rehabilitation in Panchlaish, Chattogram — stroke recovery, chronic pain, paralysis, sports injuries, and neuromuscular care.',
                    'backed_lead' => 'Backed by',
                    'backed_strong' => '8+ Physiotherapists',
                    'rating_score' => '4.9*',
                    'rating_copy' => 'Patients trust our recovery-focused physiotherapy care!',
                    'cta_text' => 'Book appointment',
                    'cta_link' => '/book',
                ]],
                ['type' => 'about_facility', 'data' => [
                    'heading' => 'About CBPH',
                    'mission_statement' => 'Chattogram Best Physiotherapy Hospital combines expert physiotherapists, modern rehab techniques & compassionate care to help every patient restore mobility, reduce pain & regain independence.',
                    'cta_text' => 'More about us',
                    'cta_link' => 'https://www.facebook.com/cbphbd',
                    'trust_copy' => 'Trusted by',
                    'trust_strong' => 'patients across Chattogram',
                    'gallery' => [
                        [
                            'title' => 'Stroke & Neurology Rehab',
                            'description' => 'Evidence-based programs for stroke, paralysis, SCI, and neurological conditions using modern neuro-rehabilitation techniques.',
                            'image_url' => 'https://framerusercontent.com/images/SebajAOsz6a8sWPvrYcEDu50c.svg?width=140&height=123',
                        ],
                        [
                            'title' => 'Pain & Musculoskeletal Care',
                            'description' => 'Manual therapy, electrotherapy, and exercise therapy for back pain, arthritis, sports injuries, and post-surgical recovery.',
                            'image_url' => 'https://framerusercontent.com/images/xa99qvpg8IUc9n1GZ7kGFxevJ0.svg?width=129&height=140',
                        ],
                        [
                            'title' => 'Patient-First Rehabilitation',
                            'description' => 'Personalized treatment plans with dedicated male and female therapy departments for comfort, privacy, and better outcomes.',
                            'image_url' => 'https://framerusercontent.com/images/QVULYcKsFklavhQU9fbshqfZw.svg?width=150&height=150',
                        ],
                    ],
                ]],
                ['type' => 'trust_bar', 'data' => [
                    'badges' => [
                        ['text_badge' => 'Stroke Rehab'],
                        ['text_badge' => 'Pain Relief'],
                        ['text_badge' => 'Sports Injury'],
                        ['text_badge' => 'Neurology'],
                        ['text_badge' => 'Manual Therapy'],
                    ],
                ]],
                ['type' => 'service_matrix', 'data' => [
                    'heading' => 'Expert Physiotherapy For Every Recovery Need',
                    'footer_text' => 'Explore evidence-based rehabilitation programs tailored to every recovery goal.',
                    'view_all_text' => 'View all services',
                    'view_all_link' => 'https://www.facebook.com/cbphbd',
                    'items' => [
                        [
                            'title' => 'Stroke Rehabilitation',
                            'description' => 'Specialized programs to restore movement, balance, and independence after stroke and neurological injury.',
                            'image_url' => 'https://images.pexels.com/photos/8460125/pexels-photo-8460125.jpeg?auto=compress&cs=tinysrgb&w=800&h=800&fit=crop',
                            'icon_url' => 'https://framerusercontent.com/images/pJSdUV9FevHoktES8cyLiFSD8jM.svg',
                        ],
                        [
                            'title' => 'Pain & Paralysis',
                            'description' => 'Targeted therapy for chronic pain, hemiplegia, paralysis, and mobility-limiting conditions.',
                            'image_url' => 'https://images.pexels.com/photos/8460126/pexels-photo-8460126.jpeg?auto=compress&cs=tinysrgb&w=800&h=800&fit=crop',
                            'icon_url' => 'https://framerusercontent.com/images/0U0qZF8m25gT9NcRC3H63SGwo.svg',
                        ],
                        [
                            'title' => 'Sports Injury Rehab',
                            'description' => 'Recovery plans for sprains, strains, and sports-related injuries to get you back in action safely.',
                            'image_url' => 'https://images.pexels.com/photos/8460127/pexels-photo-8460127.jpeg?auto=compress&cs=tinysrgb&w=800&h=800&fit=crop',
                            'icon_url' => 'https://framerusercontent.com/images/5jWBbSjEFIHfAYKeD5tZGxvBGZ0.svg',
                        ],
                        [
                            'title' => 'Neurological Rehab',
                            'description' => 'Advanced neuro-physiotherapy for Parkinsonism, neuropathy, ataxia, and vestibular disorders.',
                            'image_url' => 'https://images.pexels.com/photos/8460128/pexels-photo-8460128.jpeg?auto=compress&cs=tinysrgb&w=800&h=800&fit=crop',
                            'icon_url' => 'https://framerusercontent.com/images/KUlEux6SUGltrbOun5GlhZaydtQ.svg',
                        ],
                        [
                            'title' => 'Orthopedic Physiotherapy',
                            'description' => 'Musculoskeletal assessment, manual therapy, and exercise for fractures, arthritis, and back pain.',
                            'image_url' => 'https://images.pexels.com/photos/7579834/pexels-photo-7579834.jpeg?auto=compress&cs=tinysrgb&w=800&h=800&fit=crop',
                            'icon_url' => 'https://framerusercontent.com/images/qdBNVG8bwfV08i3g2bfy2XAc.svg',
                        ],
                    ],
                ]],
                ['type' => 'doctor_grid', 'data' => [
                    'eyebrow' => 'Our physiotherapists',
                    'heading' => 'Meet The Experts Behind Your Recovery',
                    'cards' => [
                        [
                            'name' => 'Dr. Antar Das',
                            'specialty' => 'Consultant Physiotherapist, MPT (Neurology)',
                            'image_url' => '/images/cbph/doctors/doctor-antar-das.jpg',
                        ],
                        [
                            'name' => 'Batia Nahar Ahsan',
                            'specialty' => 'Senior Physiotherapist, Female Dept.',
                            'image_url' => '/images/cbph/doctors/doctor-batia-ahsan.jpg',
                        ],
                        [
                            'name' => 'Dr. Mohammad Golam Eazdani',
                            'specialty' => 'Clinical Physiotherapist',
                            'image_url' => '/images/cbph/doctors/doctor-eazdani.jpg',
                        ],
                        [
                            'name' => 'Rehabilitation Team',
                            'specialty' => 'Orthopedic & Manual Therapy',
                            'image_url' => '/images/cbph/doctors/doctor-rehab-team.jpg',
                        ],
                    ],
                    'stats_heading' => 'Trusted Physiotherapy Centre In Panchlaish, Chattogram',
                    'stats' => [
                        ['value' => '8', 'label' => '+ Expert Physiotherapists'],
                        ['value' => '6', 'label' => 'Rehabilitation Programs'],
                        ['value' => '2', 'label' => 'Therapy Departments'],
                        ['value' => '100', 'label' => '% Patient-Focused Care'],
                    ],
                ]],
                ['type' => 'testimonials', 'data' => [
                    'eyebrow' => 'Recovery stories',
                    'heading' => 'Real Progress From Rehabilitation Treatment',
                    'promo_text' => 'Follow us on Facebook →',
                    'promo_link' => 'https://www.facebook.com/cbphbd',
                    'items' => [
                        [
                            'quote' => 'After my stroke, the CBPH team helped me walk again with patience, clear guidance, and excellent neuro-rehabilitation support every session.',
                            'name' => 'Fatima Rahman',
                            'label' => 'Stroke recovery patient',
                            'photo_url' => 'https://images.unsplash.com/photo-1531746020798-e6953c6e8e04?auto=format&fit=crop&w=120&h=120&q=80',
                        ],
                        [
                            'quote' => 'My chronic back pain improved significantly after manual therapy and exercise sessions. The physiotherapists explained every step clearly.',
                            'name' => 'Karim Hossain',
                            'label' => 'Back pain patient',
                            'photo_url' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=120&h=120&q=80',
                        ],
                        [
                            'quote' => 'I recovered from a sports injury faster than expected. The rehab plan was personalized and the staff were professional and encouraging.',
                            'name' => 'Nusrat Jahan',
                            'label' => 'Sports injury patient',
                            'photo_url' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=120&h=120&q=80',
                        ],
                        [
                            'quote' => 'The female department made my mother feel comfortable throughout her paralysis rehabilitation. We are grateful for the compassionate care.',
                            'name' => 'Abdul Malek',
                            'label' => 'Family caregiver',
                            'photo_url' => 'https://images.unsplash.com/photo-1584515933487-779824d29309?auto=format&fit=crop&w=120&h=120&q=80',
                        ],
                    ],
                ]],
                ['type' => 'health_insights', 'data' => [
                    'heading' => 'Latest Physiotherapy Tips & Insights',
                    'view_all_text' => 'View all posts',
                    'view_all_link' => 'https://www.facebook.com/cbphbd',
                    'articles' => [
                        [
                            'title' => '5 habits that support stroke recovery at home',
                            'meta' => 'Jan 12, 2026 · Stroke Care',
                            'link' => 'https://www.facebook.com/cbphbd',
                            'image_url' => 'https://images.pexels.com/photos/7579831/pexels-photo-7579831.jpeg?auto=compress&cs=tinysrgb&w=900&h=700&fit=crop',
                        ],
                        [
                            'title' => 'When to see a physiotherapist for chronic back pain',
                            'meta' => 'Nov 18, 2025 · Pain Relief',
                            'link' => 'https://www.facebook.com/cbphbd',
                            'image_url' => 'https://images.pexels.com/photos/7579832/pexels-photo-7579832.jpeg?auto=compress&cs=tinysrgb&w=900&h=700&fit=crop',
                        ],
                        [
                            'title' => 'How to recover safely after a sports injury',
                            'meta' => 'Sep 4, 2025 · Sports Rehab',
                            'link' => 'https://www.facebook.com/cbphbd',
                            'image_url' => 'https://images.pexels.com/photos/7579835/pexels-photo-7579835.jpeg?auto=compress&cs=tinysrgb&w=900&h=700&fit=crop',
                        ],
                    ],
                ]],
                ['type' => 'faq', 'data' => [
                    'heading' => 'Frequently Questions',
                    'promo_image_url' => 'https://images.pexels.com/photos/8460127/pexels-photo-8460127.jpeg?auto=compress&cs=tinysrgb&w=900&h=1200&fit=crop',
                    'promo_heading' => 'Need physiotherapy? Book an appointment',
                    'promo_cta_text' => 'Get in touch',
                    'promo_cta_link' => '/book',
                    'faqs' => [
                        [
                            'question' => 'What services does CBPH provide?',
                            'answer' => 'Chattogram Best Physiotherapy Hospital offers stroke rehabilitation, pain and paralysis care, sports injury rehab, neurological physiotherapy, orthopedic therapy, manual therapy, and electrotherapy — delivered by qualified BPT and MPT physiotherapists in Panchlaish, Chattogram.',
                        ],
                        [
                            'question' => 'How can I schedule an appointment?',
                            'answer' => 'Use the booking form on this page, call 01630-078675 or 01882-373894, or message us on Facebook at facebook.com/cbphbd. Our team will confirm your preferred date and time.',
                        ],
                        [
                            'question' => 'Do you have a separate department for women?',
                            'answer' => 'Yes. CBPH has a dedicated female physiotherapy department so women patients can receive comfortable, private rehabilitation care from our senior female physiotherapists.',
                        ],
                        [
                            'question' => 'What conditions do you treat?',
                            'answer' => 'We treat stroke and paralysis, chronic pain, back and neck pain, arthritis, sports injuries, post-surgical rehab, neurological disorders, and musculoskeletal conditions requiring physiotherapy.',
                        ],
                        [
                            'question' => 'What should I bring to my first visit?',
                            'answer' => 'Please bring your prescription or referral (if any), previous medical reports, X-rays or MRI scans, a list of current medications, and a photo ID.',
                        ],
                        [
                            'question' => 'Why choose CBPH for physiotherapy?',
                            'answer' => 'Expert physiotherapists, modern rehabilitation equipment, dedicated male and female departments, and a patient-first approach focused on mobility, pain relief, and long-term recovery.',
                        ],
                    ],
                ]],
                ['type' => 'cta_banner', 'data' => [
                    'headline' => 'Prioritize Your Recovery Today',
                    'subheadline' => 'Take the first step toward better mobility with expert physiotherapy and rehabilitation tailored to your needs in Panchlaish, Chattogram.',
                    'cta_text' => 'Book an appointment',
                    'cta_link' => '/book',
                    'trust_phone' => '01630-078675',
                    'trust_address' => '553 O.R. Nizam Road, GEC, Panchlaish',
                ]],
            ],
        ]);

        tenancy()->end();
    }
}
