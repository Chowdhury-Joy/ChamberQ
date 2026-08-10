<?php

namespace Database\Seeders;

use App\Models\Chamber;
use App\Models\Doctor;
use App\Models\Domain;
use App\Models\ScheduleSession;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WebPage;
use App\Scopes\TenantScope;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Solo tenant for Dr. Nusrat Sultana Urmi (Dermavilla, Chattogram).
 *
 * Source: https://www.facebook.com/nusraturmidoc and https://drurminusrat.com/
 *
 * Run: php artisan db:seed --class=NusratUrmiSeeder
 */
class NusratUrmiSeeder extends Seeder
{
    public const TENANT_ID = 'nusraturmi';

    public function run(): void
    {
        $tenant = Tenant::updateOrCreate(['id' => self::TENANT_ID], [
            'name' => 'Nusrat Urmi',
            'plan_tier' => 'solo',
            'slot_cap_mode' => 'per_session',
            'contact_phone' => '01323934554',
            'whatsapp_number' => '8801323934554',
            'theme_color' => '#00bbff',
            'favicon_url' => '/icons/health-favicon.svg',
            'font_family' => 'Hind Siliguri',
            'default_locale' => 'bn',
            'tagline' => 'Dermatologist, dermatosurgeon and anti-ageing expert at Dermavilla, Chattogram — book online, pay at the chamber.',
            'sms_balance' => 50,
            'billing_status' => 'trial',
            'queue_runner' => Tenant::QUEUE_RUNNER_DOCTOR,
            'feature_flags' => [
                'multiple_chambers' => true,
            ],
        ]);

        Domain::firstOrCreate(['domain' => 'nusraturmi.localhost'], ['tenant_id' => self::TENANT_ID]);
        Domain::firstOrCreate(['domain' => 'drurminusrat.com'], ['tenant_id' => self::TENANT_ID]);

        User::withoutGlobalScope(TenantScope::class)->firstOrCreate(
            ['email' => 'admin@nusraturmi.local', 'tenant_id' => self::TENANT_ID],
            [
                'name' => 'Dermavilla Admin',
                'password' => Hash::make('password'),
                'role' => User::ROLE_ADMIN,
            ],
        );

        $doctorUser = User::withoutGlobalScope(TenantScope::class)->firstOrCreate(
            ['email' => 'doctor@nusraturmi.local', 'tenant_id' => self::TENANT_ID],
            [
                'name' => 'Dr. Nusrat Sultana Urmi',
                'password' => Hash::make('password'),
                'role' => User::ROLE_DOCTOR,
            ],
        );

        tenancy()->initialize($tenant);

        Chamber::query()->delete();
        Doctor::query()->delete();
        ScheduleSession::query()->delete();

        $doctor = Doctor::create([
            'name' => 'Dr. Nusrat Sultana Urmi',
            'user_id' => $doctorUser->id,
            'practice_type' => Doctor::PRACTICE_DERMATOLOGIST,
            'qualifications' => 'MBBS, Dip. Dermatology (UK), DCD (India), MSc',
        ]);

        $dermavilla = Chamber::create([
            'name' => 'Dermavilla by Dr. Nusrat',
            'address' => '21 Zismath, Mehedibag, Chattogram, Bangladesh',
            'contact' => '01323934554',
            'map_url' => 'https://www.google.com/maps/search/Dermavilla+Mehedibag+Chittagong',
        ]);

        Chamber::create([
            'name' => 'Apollo Imperial Hospital',
            'address' => 'Apollo Imperial Hospital, Chattogram, Bangladesh',
            'contact' => '01845565366',
            'map_url' => 'https://www.google.com/maps/search/Apollo+Imperial+Hospital+Chittagong',
        ]);

        // Saturday–Thursday: morning and afternoon (clinic hours 9 AM – 8 PM on site).
        foreach ([6, 0, 1, 2, 3, 4] as $dayOfWeek) {
            ScheduleSession::create([
                'chamber_id' => $dermavilla->id,
                'doctor_id' => $doctor->id,
                'day_of_week' => $dayOfWeek,
                'session_name' => 'Morning',
                'start_time' => '10:00',
                'end_time' => '13:00',
                'slot_cap' => 12,
            ]);
            ScheduleSession::create([
                'chamber_id' => $dermavilla->id,
                'doctor_id' => $doctor->id,
                'day_of_week' => $dayOfWeek,
                'session_name' => 'Afternoon',
                'start_time' => '15:00',
                'end_time' => '20:00',
                'slot_cap' => 15,
            ]);
        }

        // Friday: afternoon only (3 PM – 8 PM).
        ScheduleSession::create([
            'chamber_id' => $dermavilla->id,
            'doctor_id' => $doctor->id,
            'day_of_week' => 5,
            'session_name' => 'Afternoon',
            'start_time' => '15:00',
            'end_time' => '20:00',
            'slot_cap' => 12,
        ]);

        WebPage::updateOrCreate(['slug' => '/'], [
            'title' => 'Dr. Nusrat Sultana Urmi',
            'is_published' => true,
            'content' => [
                ['type' => 'hero', 'data' => [
                    'headline' => "Dr. Nusrat\nSultana Urmi",
                    'credentials' => 'MBBS, Dip. Dermatology (UK), DCD (India), MSc',
                    'role_location' => 'Dermatologist · Dermatosurgeon · Anti-Ageing Expert · Dermavilla, Chattogram',
                    'cta_text' => 'Book Appointment',
                    'cta_link' => '/book',
                    'image_url' => 'https://drurminusrat.com/img/doctor/dr-nusrat-5.jpg',
                ]],
                ['type' => 'condition_library', 'data' => [
                    'heading' => 'Treatments & Services',
                    'conditions' => [
                        [
                            'name' => 'Medical Dermatology',
                            'description' => 'Expert diagnosis and treatment of acne, eczema, psoriasis, vitiligo, infections and all skin, hair and nail conditions.',
                            'features' => [
                                'Acne and rosacea management',
                                'Eczema and psoriasis care',
                                'Pigmentation and melasma',
                                'Hair loss and scalp disorders',
                            ],
                        ],
                        [
                            'name' => 'Dermatosurgery',
                            'description' => 'Precision surgical removal of moles, cysts, lipomas, warts and skin lesions with minimal scarring.',
                            'features' => [
                                'Mole and skin tag removal',
                                'Cyst and lipoma excision',
                                'Keloid and scar management',
                                'Skin cancer screening',
                            ],
                        ],
                        [
                            'name' => 'Anti-Ageing & Aesthetics',
                            'description' => 'Botox, fillers, PRP, laser therapy and evidence-based rejuvenation tailored to your skin goals.',
                            'features' => [
                                'Botox and dermal fillers',
                                'PRP and skin boosters',
                                'Laser pigmentation and hair removal',
                                'Chemical peels and HydraFacial',
                            ],
                        ],
                    ],
                ]],
                ['type' => 'about_doctor', 'data' => [
                    'heading' => 'Meet Dr. Nusrat Sultana Urmi',
                    'subheadline' => 'Bangladesh\'s leading skin specialist — combining scientific rigour with aesthetic sensitivity for results that are medically sound and visually transformative.',
                    'cta_text' => 'Book Appointment',
                    'cta_link' => '/book',
                    'highlights' => [
                        [
                            'title' => 'Qualifications',
                            'description' => 'MBBS, Diploma in Dermatology (UK), DCD (India), MSc. Board member of AAAM.',
                        ],
                        [
                            'title' => 'Practice',
                            'description' => 'Chief Consultant at Dermavilla by Dr. Nusrat and Consultant at Apollo Imperial Hospital, Chattogram.',
                        ],
                    ],
                ]],
                ['type' => 'video_gallery', 'data' => [
                    'heading' => 'Educational Videos',
                    'follow_text' => 'Follow on YouTube',
                    'follow_url' => 'https://www.youtube.com/@Dr.NusratSultanaUrmi',
                    'videos' => [
                        [
                            'title' => 'Dr. Nusrat Sultana Urmi on YouTube',
                            'type' => 'link',
                            'video_url' => 'https://www.youtube.com/@Dr.NusratSultanaUrmi',
                        ],
                        [
                            'title' => 'Dermavilla on Facebook',
                            'type' => 'link',
                            'video_url' => 'https://www.facebook.com/nusraturmidoc',
                        ],
                    ],
                ]],
                ['type' => 'testimonials', 'data' => [
                    'heading' => 'What Our Patients Say',
                    'items' => [
                        [
                            'quote' => 'Dr. Nusrat completely cleared my acne after years of struggle. Her diagnosis was spot-on and the treatment worked within weeks.',
                            'name' => 'Rina Begum',
                            'label' => 'Acne treatment · Chattogram',
                        ],
                        [
                            'quote' => 'Excellent dermatosurgery for my mole removal. The procedure was painless and scarring is minimal. Highly professional.',
                            'name' => 'Karim Ahmed',
                            'label' => 'Mole removal · Dhaka',
                        ],
                        [
                            'quote' => 'The anti-ageing treatment has taken years off my face. Dr. Nusrat\'s expertise in fillers is truly remarkable.',
                            'name' => 'Sabina Rahman',
                            'label' => 'Anti-ageing · Chattogram',
                        ],
                        [
                            'quote' => 'My eczema has been controlled for the first time in 10 years. Dr. Nusrat\'s holistic approach makes all the difference.',
                            'name' => 'Mehedi Hasan',
                            'label' => 'Eczema · Jessore',
                        ],
                        [
                            'quote' => 'Laser pigmentation treatment was very effective. Melasma reduced significantly after three sessions.',
                            'name' => 'Nasrin Khanam',
                            'label' => 'Laser therapy · Chattogram',
                        ],
                    ],
                ]],
                ['type' => 'faq', 'data' => [
                    'heading' => 'Everything You Need To Know',
                    'faqs' => [
                        [
                            'question' => 'Where is the chamber?',
                            'answer' => 'Primary chamber: Dermavilla by Dr. Nusrat, 21 Zismath, Mehedibag, Chattogram. Dr. Nusrat also consults at Apollo Imperial Hospital, Chattogram.',
                        ],
                        [
                            'question' => 'What are your consultation hours?',
                            'answer' => 'Saturday–Thursday: morning 10:00 am – 1:00 pm and afternoon 3:00 pm – 8:00 pm. Friday: afternoon 3:00 pm – 8:00 pm only. Book a serial online before you arrive.',
                        ],
                        [
                            'question' => 'How do I book or ask about my serial?',
                            'answer' => 'Book online through this site, call 01323-934554, or message on WhatsApp at the same number.',
                        ],
                        [
                            'question' => 'Do you offer teleconsultation?',
                            'answer' => 'In-clinic visits are the main service. Online booking and live queue tracking are available so you spend less time waiting on site.',
                        ],
                        [
                            'question' => 'What conditions do you treat?',
                            'answer' => 'Full-spectrum dermatology — medical skin disease, dermatosurgery, laser therapy, hair and scalp care, and aesthetic anti-ageing treatments.',
                        ],
                        [
                            'question' => 'What payment methods do you accept?',
                            'answer' => 'Pay at the chamber after your visit. Cash and common mobile financial services are accepted.',
                        ],
                    ],
                ]],
            ],
        ]);

        $this->call(NusratUrmiDemoSeeder::class);

        tenancy()->end();
    }
}
