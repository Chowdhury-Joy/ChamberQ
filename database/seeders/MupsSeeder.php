<?php

namespace Database\Seeders;

use App\Models\AttendanceRecord;
use App\Models\BlogPost;
use App\Models\Chamber;
use App\Models\Department;
use App\Models\Doctor;
use App\Models\Domain;
use App\Models\Employee;
use App\Models\FeeCatalogItem;
use App\Models\LeaveRequest;
use App\Models\PayrollPayment;
use App\Models\ReferralCommission;
use App\Models\ReferringDoctor;
use App\Models\ScheduleSession;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WebPage;
use App\Scopes\TenantScope;
use App\Support\SeedAccounts;
use Illuminate\Database\Seeder;

/**
 * Clinic Front door + full floor for MUPS (Dr. Moin Uddin Pain Solution).
 *
 * Two branches: Panchlaish (Chattogram) and Uttara (Dhaka). Super Admin
 * modules all on (website, live queue, Rx, Stations, Referrals, HR).
 * Copy from https://www.drmups.com; visual shell is the Clireo clinic look.
 *
 * Run: php artisan db:seed --class=MupsSeeder
 */
class MupsSeeder extends Seeder
{
    public const TENANT_ID = 'mups';

    private const IMG = 'https://www.drmups.com/assets/img';

    public function run(): void
    {
        SeedAccounts::refuseProduction();

        $tenant = Tenant::updateOrCreate(['id' => self::TENANT_ID], [
            'name' => 'MUPS — Dr. Moin Uddin Pain Solution',
            'plan_tier' => 'clinic',
            'slot_cap_mode' => 'per_session',
            'contact_phone' => '01880728711',
            'whatsapp_number' => '8801880728711',
            'theme_color' => '#1B2978',
            'logo_url' => self::IMG.'/logo.webp',
            'favicon_url' => '/images/mups/favicon.svg',
            'font_family' => 'Golos Text',
            'default_locale' => 'en',
            'tagline' => 'Relief without surgery — book a serial, pay at the chamber.',
            'sms_balance' => 200,
            'billing_status' => 'active',
            'queue_runner' => Tenant::QUEUE_RUNNER_STAFF,
            'eta_model' => Tenant::ETA_LIVE_AVERAGE,
            'call_announce_mode' => Tenant::ANNOUNCE_CHIME_AND_VOICE,
            'call_announce_locale' => 'bn',
            'call_timeout_seconds' => 15,
            'estimated_time_buffer_minutes' => 20,
            'first_n_patients' => 3,
            'first_n_arrival_offset_minutes' => 15,
            'call_audio_preset' => 'chime',
            'feature_flags' => Tenant::mergeOptInModuleFlag(
                Tenant::mergeOptInModuleFlag(
                    Tenant::mergeStationsFlag(
                        array_merge(
                            Tenant::featureFlagsWithModules([], [
                                Tenant::MODULE_FRONT_DOOR,
                                Tenant::MODULE_LIVE_QUEUE,
                                Tenant::MODULE_PRESCRIPTION,
                            ]),
                            [
                                'bangla_homepage' => true,
                                'lab_tests' => true,
                                'multiple_chambers' => true,
                                'multiple_doctors' => true,
                            ],
                        ),
                        true,
                    ),
                    Tenant::MODULE_REFERRALS,
                    true,
                ),
                Tenant::MODULE_HR,
                true,
            ),
        ]);

        Domain::firstOrCreate(['domain' => 'mups.localhost'], ['tenant_id' => self::TENANT_ID]);
        Domain::firstOrCreate(['domain' => 'drmups.com'], ['tenant_id' => self::TENANT_ID]);
        Domain::firstOrCreate(['domain' => 'www.drmups.com'], ['tenant_id' => self::TENANT_ID]);

        SeedAccounts::upsert(
            ['email' => 'admin@mups.local', 'tenant_id' => self::TENANT_ID],
            [
                'name' => 'MUPS Owner',
                'role' => User::ROLE_ADMIN,
            ],
            'pass',
        );

        SeedAccounts::upsert(
            ['email' => 'support@mups.chamberq.internal', 'tenant_id' => self::TENANT_ID],
            [
                'name' => 'ChamberQ Support',
                'role' => User::ROLE_HELPER,
            ],
            'pass',
        );

        $doctorUser = SeedAccounts::upsert(
            ['email' => 'doctor@mups.local', 'tenant_id' => self::TENANT_ID],
            [
                'name' => 'Dr. Mohammad Moin Uddin',
                'role' => User::ROLE_DOCTOR,
            ],
            'pass',
        );

        SeedAccounts::upsert(
            ['email' => 'staff@mups.local', 'tenant_id' => self::TENANT_ID],
            [
                'name' => 'MUPS Desk',
                'role' => User::ROLE_STAFF,
            ],
            'pass',
        );

        tenancy()->initialize($tenant);

        MupsDemoSeeder::wipeTenantOperationalData();
        ReferralCommission::query()->delete();
        PayrollPayment::query()->delete();
        LeaveRequest::query()->delete();
        AttendanceRecord::query()->delete();
        Employee::query()->delete();
        ReferringDoctor::query()->delete();
        FeeCatalogItem::query()->delete();
        ScheduleSession::query()->delete();
        Chamber::query()->delete();
        Doctor::query()->delete();
        Department::query()->delete();
        BlogPost::query()->delete();

        $doctor = Doctor::create([
            'name' => 'Dr. Mohammad Moin Uddin',
            'user_id' => $doctorUser->id,
            'practice_type' => Doctor::PRACTICE_GENERAL,
            'qualifications' => 'MBBS, MD, FIPM (World Institute of Pain)',
            'registration_number' => 'A-65432',
            'public_title' => 'Interventional Pain Physician · MSKUS pioneer, Bangladesh',
            'public_slug' => 'dr-mohammad-moin-uddin',
            'show_on_website' => true,
            'website_sort_order' => 1,
            'photo_url' => self::IMG.'/hero/dr-moin-portrait.jpg',
            'bio' => '<p>“A patient’s pain is never just a symptom. It is a story — and my job is to listen before I treat.”</p><p>Dr. Mohammad Moin Uddin is an interventional pain physician (MBBS, MD, FIPM — World Institute of Pain), trained in Korea and India. He founded MUPS so Bangladesh would have a dedicated, image-guided pain centre rather than a last stop after surgery had already been offered.</p><p>He leads the MUPS Fellowship and consults across the Chittagong and Dhaka centres.</p>',
            'default_fee_taka' => 1000,
            'extra_fees' => [
                ['label' => 'Follow-up', 'amount' => 800],
            ],
            'staff_may_enter_prescriptions' => true,
            'allows_repeat_serials' => true,
            'notify_channels' => [
                Doctor::NOTIFY_BOOKING_CONFIRMATION => ['sms' => true, 'whatsapp' => true],
                Doctor::NOTIFY_DOCTOR_LATE => ['sms' => true, 'whatsapp' => true],
                Doctor::NOTIFY_CANCELLATION => ['sms' => true, 'whatsapp' => true],
                Doctor::NOTIFY_PRESCRIPTION => ['sms' => true, 'whatsapp' => true],
                Doctor::NOTIFY_FOLLOW_UP => ['sms' => true, 'whatsapp' => true],
            ],
        ]);

        $panchlaish = Chamber::create([
            'name' => 'Moin Uddin Pain Solution — Panchlaish',
            'address' => 'Neurosense, Panchlaish (near Chittagong Medical College), Chattogram',
            'contact' => '01880728711',
            'map_url' => 'https://www.google.com/maps/search/?api=1&query='.rawurlencode('Neurosense Panchlaish Chittagong Medical College'),
        ]);

        $uttara = Chamber::create([
            'name' => 'MUPS Dhaka Centre — Uttara',
            'address' => 'Sector 4, Uttara, Dhaka (about 10 minutes from the airport)',
            'contact' => '01880728711',
            'map_url' => 'https://www.google.com/maps/search/?api=1&query='.rawurlencode('MUPS Pain Solution Sector 4 Uttara Dhaka'),
        ]);

        // Two branches. Days never overlap — Dr. Moin is in one city at a time.
        // Chittagong (Panchlaish): Sat, Sun, Mon, Fri.
        // Dhaka (Uttara): Tue, Wed, Thu.
        $this->seedBranchDay($panchlaish, $doctor, 6, '10:00', '13:00', '13:00', '20:00');
        foreach ([0, 1] as $day) {
            $this->seedBranchDay($panchlaish, $doctor, $day, '16:00', '17:30', '17:30', '21:00');
        }
        $this->seedBranchDay($panchlaish, $doctor, 5, '16:00', '17:30', '17:30', '20:00');
        foreach ([2, 3] as $day) {
            $this->seedBranchDay($uttara, $doctor, $day, '17:00', '18:00', '18:00', '21:00');
        }
        $this->seedBranchDay($uttara, $doctor, 4, '10:00', '12:00', '12:00', '13:00');
        $this->seedBranchDay($uttara, $doctor, 4, '17:00', '18:00', '18:00', '21:00');

        $this->seedFeeCatalogue();
        $this->seedReferringDoctors();
        $this->seedHr();

        $this->seedDepartments();
        $this->seedBlog();
        $this->seedPages();

        $this->call(MupsDemoSeeder::class);

        tenancy()->end();
    }

    private function seedBranchDay(
        Chamber $chamber,
        Doctor $doctor,
        int $dayOfWeek,
        string $interventionStart,
        string $interventionEnd,
        string $visitStart,
        string $visitEnd,
    ): void {
        ScheduleSession::create([
            'chamber_id' => $chamber->id,
            'doctor_id' => $doctor->id,
            'day_of_week' => $dayOfWeek,
            'session_name' => 'Intervention',
            'kind' => ScheduleSession::KIND_INTERVENTION,
            'start_time' => $interventionStart,
            'end_time' => $interventionEnd,
            'slot_cap' => 12,
            'walk_in_overflow_cap' => 0,
        ]);

        ScheduleSession::create([
            'chamber_id' => $chamber->id,
            'doctor_id' => $doctor->id,
            'day_of_week' => $dayOfWeek,
            'session_name' => 'Visit',
            'kind' => ScheduleSession::KIND_VISIT,
            'start_time' => $visitStart,
            'end_time' => $visitEnd,
            'slot_cap' => 20,
            'walk_in_overflow_cap' => 6,
        ]);

        ScheduleSession::create([
            'chamber_id' => $chamber->id,
            'doctor_id' => $doctor->id,
            'day_of_week' => $dayOfWeek,
            'session_name' => 'Counseling',
            'kind' => ScheduleSession::KIND_COUNSELING,
            'start_time' => $interventionStart,
            'end_time' => $visitEnd,
            'slot_cap' => 40,
            'walk_in_overflow_cap' => 0,
        ]);
    }

    private function seedFeeCatalogue(): void
    {
        $rows = [
            ['label' => 'Visit (new)', 'list_price_taka' => 1000, 'house_share_taka' => 200, 'sitting_kind' => ScheduleSession::KIND_VISIT, 'sort_order' => 10],
            ['label' => 'Follow-up', 'list_price_taka' => 800, 'house_share_taka' => 100, 'sitting_kind' => ScheduleSession::KIND_VISIT, 'sort_order' => 20],
            ['label' => 'Epidural (caudal / TF)', 'list_price_taka' => 8000, 'house_share_taka' => 1000, 'sitting_kind' => ScheduleSession::KIND_INTERVENTION, 'sort_order' => 100],
            ['label' => 'Facet / RFA', 'list_price_taka' => 12000, 'house_share_taka' => 1000, 'sitting_kind' => ScheduleSession::KIND_INTERVENTION, 'sort_order' => 110],
            ['label' => 'Knee HA / cortisone', 'list_price_taka' => 5000, 'house_share_taka' => 800, 'sitting_kind' => ScheduleSession::KIND_INTERVENTION, 'sort_order' => 120],
            ['label' => 'PRP knee (single)', 'list_price_taka' => 8000, 'house_share_taka' => 1000, 'sitting_kind' => ScheduleSession::KIND_INTERVENTION, 'sort_order' => 130],
            ['label' => 'Shoulder (single)', 'list_price_taka' => 8000, 'house_share_taka' => 1000, 'sitting_kind' => ScheduleSession::KIND_INTERVENTION, 'sort_order' => 140],
            ['label' => 'Nerve block / pulsed RF', 'list_price_taka' => 8000, 'house_share_taka' => 1000, 'sitting_kind' => ScheduleSession::KIND_INTERVENTION, 'sort_order' => 150],
            ['label' => 'SI joint', 'list_price_taka' => 10000, 'house_share_taka' => 1000, 'sitting_kind' => ScheduleSession::KIND_INTERVENTION, 'sort_order' => 160],
            ['label' => 'Hip joint', 'list_price_taka' => 10000, 'house_share_taka' => 1000, 'sitting_kind' => ScheduleSession::KIND_INTERVENTION, 'sort_order' => 170],
        ];

        foreach ($rows as $row) {
            FeeCatalogItem::create($row);
        }
    }

    private function seedReferringDoctors(): void
    {
        ReferringDoctor::create([
            'name' => 'Dr. Karim (Panchlaish GP)',
            'phone' => '01811000011',
            'specialty' => 'General practice',
        ]);

        ReferringDoctor::create([
            'name' => 'Dr. Sultana (Uttara ortho)',
            'phone' => '01811000012',
            'specialty' => 'Orthopaedic',
        ]);
    }

    private function seedHr(): void
    {
        $staff = User::withoutGlobalScope(TenantScope::class)
            ->where('email', 'staff@mups.local')
            ->first();

        Employee::create([
            'user_id' => $staff?->id,
            'name' => 'MUPS Desk',
            'phone' => '01822000011',
            'job_title' => 'Reception — both centres',
            'monthly_salary_taka' => 18000,
            'joined_on' => now()->subMonths(8)->toDateString(),
        ]);

        Employee::create([
            'name' => 'Nurse Rina',
            'phone' => '01822000012',
            'job_title' => 'Procedure nurse — Panchlaish',
            'monthly_salary_taka' => 22000,
            'joined_on' => now()->subYear()->toDateString(),
        ]);

        Employee::create([
            'name' => 'Nurse Farhana',
            'phone' => '01822000013',
            'job_title' => 'Procedure nurse — Uttara',
            'monthly_salary_taka' => 22000,
            'joined_on' => now()->subMonths(10)->toDateString(),
        ]);
    }

    private function seedDepartments(): void
    {
        $rows = [
            [
                'title' => 'Spine & Back',
                'slug' => 'spine-back',
                'excerpt' => 'Disc, sciatica, facet joint and failed-back protocols — epidural, RFA and discography.',
                'image_url' => self::IMG.'/treatments-photo/spine-back.jpg',
                'body' => '<p>Disc, sciatica, facet joint and failed-back surgery pain, treated with image-guided day-care procedures rather than a long admission.</p><ul><li>Epidural (caudal and transforaminal)</li><li>Radiofrequency ablation</li><li>Discography</li></ul>',
            ],
            [
                'title' => 'Knee & Hip',
                'slug' => 'knee-hip',
                'excerpt' => 'Osteoarthritis, meniscal pain and hip bursitis — hyaluronic acid, cortisone and PRP.',
                'image_url' => self::IMG.'/treatments-photo/knee-hip.jpg',
                'body' => '<p>Joint-preserving injections for osteoarthritic knees and hips, aimed at delaying or avoiding replacement when that is still honest medicine.</p><ul><li>Hyaluronic acid (viscosupplement)</li><li>Cortisone</li><li>PRP</li></ul>',
            ],
            [
                'title' => 'Headache & Neck',
                'slug' => 'headache-neck',
                'excerpt' => 'Migraine, cervicogenic pain and occipital neuralgia — nerve block and Botox options.',
                'image_url' => self::IMG.'/treatments-photo/neck-headache.jpg',
                'body' => '<p>Neck-driven headache and occipital neuralgia, mapped with ultrasound so the block sits on the nerve that is actually firing.</p><ul><li>Nerve block</li><li>Botox where indicated</li></ul>',
            ],
            [
                'title' => 'Nerve Pain',
                'slug' => 'nerve-pain',
                'excerpt' => 'Neuralgia, shingles and diabetic neuropathy — pulsed RF and targeted blocks.',
                'image_url' => self::IMG.'/treatments-photo/nerve-pain.jpg',
                'body' => '<p>Burning, electric and post-herpetic pain gets its own pathway — not a generic painkiller script.</p><ul><li>Pulsed radiofrequency</li><li>Image-guided nerve blocks</li></ul>',
            ],
            [
                'title' => 'Shoulder',
                'slug' => 'shoulder',
                'excerpt' => 'Frozen shoulder, rotator cuff and impingement — capsular distension and PRP.',
                'image_url' => self::IMG.'/treatments-photo/shoulder.jpg',
                'body' => '<p>Frozen and torn shoulders treated as day-care, with a written rehab plan so range of motion actually returns.</p><ul><li>Capsular distension</li><li>PRP</li></ul>',
            ],
            [
                'title' => 'Wrist & Hand',
                'slug' => 'wrist-hand',
                'excerpt' => 'Carpal tunnel, tennis elbow and trigger finger — hydrodissection under ultrasound.',
                'image_url' => self::IMG.'/treatments-photo/wrist-hand.jpg',
                'body' => '<p>Ultrasound hydrodissection for trapped nerves and trigger digits — usually walk-in, no theatre stay.</p><ul><li>Hydrodissection</li></ul>',
            ],
            [
                'title' => 'Sports Injury',
                'slug' => 'sports-injury',
                'excerpt' => 'Ligament, tendon and muscle injuries with a return-to-play plan — PRP and prolotherapy.',
                'image_url' => self::IMG.'/treatments-photo/sports-injury.jpg',
                'body' => '<p>Return-to-play plans that treat the injured tissue, not only the swelling around it.</p><ul><li>PRP</li><li>Prolotherapy</li></ul>',
            ],
            [
                'title' => 'Arthritis Care',
                'slug' => 'arthritis-care',
                'excerpt' => 'OA, RA and gout — long-term joint preservation with viscosupplement and regenerative injections.',
                'image_url' => self::IMG.'/treatments-photo/arthritis.jpg',
                'body' => '<p>Chronic arthritis care with transparent quotes before any injection, and a plan you can follow at home.</p><ul><li>Viscosupplement</li><li>Regenerative injections</li></ul>',
            ],
            [
                'title' => 'Full-body MSK',
                'slug' => 'full-body-msk',
                'excerpt' => 'Fibromyalgia, chronic pain syndromes and cancer-related pain — multimodal, image-guided care.',
                'image_url' => self::IMG.'/treatments-photo/musculoskeletal.jpg',
                'body' => '<p>When pain is not one joint, MUPS maps a multimodal plan — blocks, medication review and honest talk about what will and will not help.</p><ul><li>Multimodal protocols</li><li>Cancer-related pain pathways</li></ul>',
            ],
        ];

        foreach ($rows as $i => $row) {
            Department::create([
                ...$row,
                'sort_order' => $i + 1,
                'is_published' => true,
            ]);
        }
    }

    private function seedBlog(): void
    {
        $posts = [
            [
                'title' => 'When back pain is more than just back pain',
                'slug' => 'when-back-pain-is-more-than-just-back-pain',
                'excerpt' => 'Five warning signs your slipped disc needs an interventional specialist — not just painkillers or bed rest.',
                'image_url' => self::IMG.'/treatments-photo/spine-back.jpg',
                'body' => '<p>Most back pain settles with rest and simple medicine. Some does not. If pain shoots below the knee, if a foot is getting weaker, or if you have already been told “live with it”, that is the moment to see an interventional pain physician rather than waiting for an open operation to become the only remaining option.</p><p>At MUPS we review your imaging, examine you, and only then quote a day-care procedure — epidural, facet work or radiofrequency — so you leave with a plan, not another tablet.</p>',
            ],
            [
                'title' => 'Delay knee replacement — safely, with modern medicine',
                'slug' => 'delay-knee-replacement-safely',
                'excerpt' => 'How image-guided injections, PRP and targeted physio can push surgery back by years — and sometimes avoid it entirely.',
                'image_url' => self::IMG.'/treatments-photo/knee-hip.jpg',
                'body' => '<p>A worn knee is not automatically a knee that must be replaced this year. Hyaluronic acid, cortisone placed under ultrasound, and PRP can buy time — sometimes years — when the joint still has cartilage worth protecting.</p><p>We say so when surgery is the honest next step. We also say so when it is not.</p>',
            ],
            [
                'title' => 'The nerve pain nobody talks about',
                'slug' => 'the-nerve-pain-nobody-talks-about',
                'excerpt' => 'Burning feet, electric shocks, numb hands — why neuropathic pain deserves its own treatment path, not generic tablets.',
                'image_url' => self::IMG.'/treatments-photo/nerve-pain.jpg',
                'body' => '<p>Neuropathic pain does not behave like a sprain. Tablets that help a twisted ankle often do nothing for burning feet or post-shingles shocks. Those nerves need a mapped block or pulsed radiofrequency, not a higher dose of the same pill.</p>',
            ],
        ];

        foreach ($posts as $i => $post) {
            BlogPost::create([
                ...$post,
                'sort_order' => $i + 1,
                'is_published' => true,
                'published_at' => now()->subMonths(3 - $i),
            ]);
        }
    }

    private function seedPages(): void
    {
        WebPage::updateOrCreate(['slug' => '/'], [
            'title' => 'Home',
            'is_published' => true,
            'content' => $this->homeBlocks(),
        ]);

        WebPage::updateOrCreate(['slug' => '/centres'], [
            'title' => 'Centres',
            'is_published' => true,
            'content' => [
                ['type' => 'hero', 'data' => [
                    'headline' => 'Two centres. One standard of care.',
                    'subheadline' => 'Dhaka and Chittagong — clean day-care theatres, high-resolution ultrasound, sterile technique and the same specialist team at every location.',
                    'backed_lead' => 'Open',
                    'backed_strong' => '7 days a week',
                    'rating_score' => '2 cities',
                    'rating_copy' => 'Central booking line 9 AM – 10 PM · +880 1880-728711',
                    'cta_text' => 'Book appointment',
                    'cta_link' => '/book',
                    'image_url' => self::IMG.'/treatments-photo/spine-back.jpg',
                ]],
                ['type' => 'location_hours', 'data' => $this->locationsBlock()],
                ['type' => 'about_facility', 'data' => [
                    'heading' => 'What every centre has — no exceptions',
                    'mission_statement' => 'High-res ultrasound, C-arm fluoroscopy, an RFA generator, a sterile OT, ECG and monitors, and wheelchair access. One call books any centre.',
                    'cta_text' => 'Book a visit',
                    'cta_link' => '/book',
                    'trust_copy' => 'Call',
                    'trust_strong' => '+880 1880-728711',
                    'gallery' => $this->promiseGallery(),
                ]],
                ['type' => 'cta_banner', 'data' => $this->ctaBlock()],
            ],
        ]);

        WebPage::updateOrCreate(['slug' => '/fellowship'], [
            'title' => 'Fellowship',
            'is_published' => true,
            'content' => [
                ['type' => 'hero', 'data' => [
                    'headline' => 'One year. A career redefined.',
                    'subheadline' => 'Bangladesh’s only FIPP-track fellowship in interventional pain — six fellows, twelve months, hundreds of image-guided procedures, mentored by Dr. Moin Uddin and international faculty from the World Institute of Pain.',
                    'backed_lead' => '2026 intake',
                    'backed_strong' => '6 fellows / batch',
                    'rating_score' => '12 mo',
                    'rating_copy' => '500+ live procedures · Apply by 31 December',
                    'cta_text' => 'Message about fellowship',
                    'cta_link' => 'https://wa.me/8801880728711',
                    'image_url' => self::IMG.'/hero/dr-moin-portrait.jpg',
                ]],
                ['type' => 'rich_text', 'data' => [
                    'content' => $this->fellowshipHtml(),
                ]],
                ['type' => 'testimonials', 'data' => [
                    'eyebrow' => 'From past MUPS fellows',
                    'heading' => 'A portfolio, not just a certificate',
                    'promo_text' => 'Ask about the 2026 batch',
                    'promo_link' => 'https://wa.me/8801880728711',
                    'items' => [
                        [
                            'quote' => 'I did 400+ image-guided procedures in one year. No other fellowship in the country comes close to that volume — and every single case was reviewed with Dr. Moin.',
                            'name' => 'Dr. R. Ahmed',
                            'label' => 'Class of 2024 · Consultant, Dhaka',
                        ],
                        [
                            'quote' => 'The WIP webinars alone are worth the fee. Add in the cadaver labs and daily case reviews and you leave with a portfolio, not just a certificate.',
                            'name' => 'Dr. S. Rahman',
                            'label' => 'Class of 2023 · Practising in Sylhet',
                        ],
                    ],
                ]],
                ['type' => 'cta_banner', 'data' => [
                    'headline' => 'Ready to apply?',
                    'subheadline' => 'Applications for the 2026 batch close on 31 December. Only six seats. Programme fee ৳ 3,50,000 including cadaver labs and WIP webinars.',
                    'cta_text' => 'WhatsApp the fellowship desk',
                    'cta_link' => 'https://wa.me/8801880728711',
                    'trust_phone' => '01880-728711',
                    'trust_address' => 'hello@mups.com.bd',
                ]],
            ],
        ]);

        WebPage::updateOrCreate(['slug' => '/gallery'], [
            'title' => 'Gallery',
            'is_published' => true,
            'content' => [
                ['type' => 'hero', 'data' => [
                    'headline' => 'Moments from the practice',
                    'subheadline' => 'Portraits, consultations, procedures, teaching and events — a visual archive of MUPS at work.',
                    'backed_lead' => 'Photo journal',
                    'backed_strong' => 'Clinic · portraits · events',
                    'rating_score' => 'MUPS',
                    'rating_copy' => 'Unposed pictures from the bench, the consult room and teaching days.',
                    'cta_text' => 'Book appointment',
                    'cta_link' => '/book',
                    'image_url' => self::IMG.'/hero/dr-moin-portrait.jpg',
                ]],
                ['type' => 'image_carousel', 'data' => [
                    'heading' => 'Clinic, portraits and teaching',
                    'aspect_ratio' => '16:9',
                    'items' => [
                        ['image_url' => self::IMG.'/hero/dr-moin-portrait.jpg', 'title' => 'Dr. Moin Uddin', 'description' => 'Formal portrait · 2025'],
                        ['image_url' => self::IMG.'/treatments-photo/spine-back.jpg', 'title' => 'Spine pathway', 'description' => 'Image-guided back and disc care'],
                        ['image_url' => self::IMG.'/treatments-photo/knee-hip.jpg', 'title' => 'Knee & hip', 'description' => 'Joint preservation injections'],
                        ['image_url' => self::IMG.'/treatments-photo/nerve-pain.jpg', 'title' => 'Nerve pain', 'description' => 'Mapped blocks and pulsed RF'],
                        ['image_url' => self::IMG.'/treatments-photo/shoulder.jpg', 'title' => 'Shoulder', 'description' => 'Frozen shoulder and rotator cuff'],
                        ['image_url' => self::IMG.'/treatments-photo/arthritis.jpg', 'title' => 'Arthritis care', 'description' => 'Long-term joint preservation'],
                    ],
                ]],
            ],
        ]);

        WebPage::updateOrCreate(['slug' => '/contact'], [
            'title' => 'Contact',
            'is_published' => true,
            'content' => [
                ['type' => 'hero', 'data' => [
                    'headline' => 'A phone call away. A cure closer.',
                    'subheadline' => 'Call, WhatsApp, walk in or book online — a member of the team responds within one working hour. For appointments, please book a serial or call; the inbox is for non-urgent questions only.',
                    'backed_lead' => 'Email',
                    'backed_strong' => 'hello@mups.com.bd',
                    'rating_score' => '1 hr',
                    'rating_copy' => 'Typical callback window, 9 AM – 10 PM, seven days.',
                    'cta_text' => 'Book appointment',
                    'cta_link' => '/book',
                    'image_url' => self::IMG.'/treatments-photo/musculoskeletal.jpg',
                ]],
                ['type' => 'location_hours', 'data' => $this->locationsBlock()],
                ['type' => 'cta_banner', 'data' => $this->ctaBlock()],
            ],
        ]);

        WebPage::updateOrCreate(['slug' => '/appointment'], [
            'title' => 'Appointment',
            'is_published' => true,
            'content' => [
                ['type' => 'hero', 'data' => [
                    'headline' => 'Get the appointment',
                    'subheadline' => 'Pick your slot in under 60 seconds. The booking desk works 9 AM – 10 PM, seven days a week — and someone calls you back within an hour to confirm. Pay at the chamber after the visit.',
                    'backed_lead' => 'No account needed',
                    'backed_strong' => 'Instant confirmation',
                    'rating_score' => '20 min',
                    'rating_copy' => 'Typical first consult — diagnosis, plan, and often same-day treatment.',
                    'cta_text' => 'Book appointment now',
                    'cta_link' => '/book',
                    'image_url' => self::IMG.'/hero/dr-moin-cutout.webp',
                ]],
                ['type' => 'patient_journey', 'data' => [
                    'heading' => 'What happens after you book',
                    'steps' => [
                        ['step_number' => '01', 'title' => 'Instant confirmation', 'description' => 'You receive a ticket for your serial, doctor and centre.'],
                        ['step_number' => '02', 'title' => 'Call back within 1 hour', 'description' => 'A coordinator answers questions and prepares you for the visit.'],
                        ['step_number' => '03', 'title' => 'Arrive and get treated', 'description' => '20-minute consult, diagnosis, and if suitable, same-day procedure.'],
                        ['step_number' => '04', 'title' => '48-hour follow-up', 'description' => 'A phone call from us within two days. Written after-care goes home with you.'],
                    ],
                ]],
                ['type' => 'cta_banner', 'data' => $this->ctaBlock()],
            ],
        ]);
    }

    /**
     * @return list<array{type: string, data: array<string, mixed>}>
     */
    private function homeBlocks(): array
    {
        return [
            ['type' => 'hero', 'data' => [
                'headline' => 'Relief without surgery',
                'subheadline' => 'Back, knee or nerve pain — ultrasound puts the medicine on the exact nerve, usually in under 30 minutes. Bangladesh’s first dedicated pain centre. Book a serial; pay at the chamber.',
                'backed_lead' => 'Treated by',
                'backed_strong' => 'Dr. Mohammad Moin Uddin, MBBS, MD, FIPM',
                'rating_score' => '5000+',
                'rating_copy' => 'Patients treated · Dhaka & Chattogram · pay at the chamber',
                'cta_text' => 'Book a serial',
                'cta_link' => '/book',
                'image_url' => '/images/mups/mups-hero-surgery.jpg',
            ]],
            ['type' => 'stat_band', 'data' => [
                'heading' => 'MUPS — Dhaka and Chittagong',
                'stats' => [
                    ['value' => '15+', 'label' => 'Years of excellence'],
                    ['value' => '5000+', 'label' => 'Patients treated'],
                    ['value' => '40+', 'label' => 'Day-care procedures'],
                    ['value' => '2', 'label' => 'Cities, one standard'],
                ],
            ]],
            ['type' => 'about_facility', 'data' => [
                'heading' => 'Why patients book MUPS',
                'mission_statement' => 'Bangladesh’s first dedicated pain centre. Most visits are under 30 minutes, no overnight stay — and we only recommend a procedure when it will actually help.',
                'cta_text' => 'Meet Dr. Moin',
                'cta_link' => '/doctors/dr-mohammad-moin-uddin',
                'trust_copy' => 'Trusted by',
                'trust_strong' => 'patients across Dhaka, Chattogram and beyond',
                'gallery' => $this->promiseGallery(),
            ]],
            ['type' => 'trust_bar', 'data' => [
                'badges' => [
                    ['text_badge' => 'Ultrasound-guided'],
                    ['text_badge' => 'Minimally invasive'],
                    ['text_badge' => 'Interventional pain'],
                    ['text_badge' => 'International faculty'],
                    ['text_badge' => 'Day-care procedures'],
                    ['text_badge' => 'FIPP-track fellowship'],
                ],
            ]],
            ['type' => 'service_matrix', 'data' => [
                'heading' => 'Choose the pain you want gone',
                'footer_text' => 'Nine image-guided pathways. Open yours, then book a serial — same-day day-care at the chamber.',
                'view_all_text' => 'View all treatments',
                'view_all_link' => '/departments',
            ]],
            ['type' => 'doctor_grid', 'data' => [
                'eyebrow' => 'The founder',
                'heading' => 'Meet the man who put pain medicine on Bangladesh’s map',
                'view_all_text' => 'View full profile',
                'view_all_link' => '/doctors',
            ]],
            ['type' => 'patient_journey', 'data' => [
                'heading' => 'From first call to lasting relief',
                'steps' => [
                    ['step_number' => '01', 'title' => 'Book a visit', 'description' => 'Call, WhatsApp, or book a serial here — a real human calls back within an hour.'],
                    ['step_number' => '02', 'title' => 'Detailed assessment', 'description' => 'History, examination and imaging review — so we treat the cause, not just the symptom.'],
                    ['step_number' => '03', 'title' => 'Targeted treatment', 'description' => 'Image-guided procedure in a sterile suite — usually 20 to 40 minutes, no admission needed.'],
                    ['step_number' => '04', 'title' => 'Recovery and follow-up', 'description' => 'Rehab plan, home exercises and check-ins — because relief should stay.'],
                ],
            ]],
            ['type' => 'testimonials', 'data' => [
                'eyebrow' => 'Voices of relief',
                'heading' => 'Real people, real relief',
                'promo_text' => 'Follow MUPS on Facebook',
                'promo_link' => 'https://www.facebook.com/Dr.MoinUddinPainSolution',
                'items' => [
                    [
                        'quote' => 'Six years of back pain, gone in one afternoon. I walked out and cried in the hallway — happy tears.',
                        'name' => 'Rafiq A.',
                        'label' => 'Chittagong · Spine RF',
                    ],
                    [
                        'quote' => 'Dr. Moin explained everything in plain Bangla. No fear, no jargon — just a plan that actually worked.',
                        'name' => 'Salma K.',
                        'label' => 'Dhaka · Knee injection',
                    ],
                    [
                        'quote' => 'Two hospitals said surgery. MUPS said try this first. Three months later I’m back on the cricket pitch.',
                        'name' => 'Tanvir H.',
                        'label' => 'Sylhet · Shoulder care',
                    ],
                    [
                        'quote' => 'I could not lift my arm to comb my hair. After the injection I slept through the night for the first time in months.',
                        'name' => 'Nasrin C.',
                        'label' => 'Uttara · Frozen shoulder',
                    ],
                    [
                        'quote' => 'Sciatica down my left leg. One image-guided sitting, and I walked to the car without the stick.',
                        'name' => 'Farhan M.',
                        'label' => 'Uttara · Spine',
                    ],
                    [
                        'quote' => 'They quoted the procedure fee before they touched me. No extras. The knee is not new — but it is mine again.',
                        'name' => 'Ayesha R.',
                        'label' => 'Chittagong · Knee injection',
                    ],
                    [
                        'quote' => 'Migraines two days a week. He mapped the nerve, treated it, and I went back to work the same afternoon.',
                        'name' => 'Kamal I.',
                        'label' => 'Dhaka · Headache',
                    ],
                    [
                        'quote' => 'Surgery was already booked. Second opinion at MUPS: try this first. I cancelled the OT.',
                        'name' => 'Rina S.',
                        'label' => 'Panchlaish · Nerve pain',
                    ],
                ],
            ]],
            ['type' => 'location_hours', 'data' => $this->locationsBlock()],
            ['type' => 'health_insights', 'data' => [
                'heading' => 'Learn before you decide',
                'view_all_text' => 'View all articles',
                'view_all_link' => '/blog',
            ]],
            ['type' => 'faq', 'data' => [
                'heading' => 'Before you pick up the phone',
                'promo_image_url' => self::IMG.'/treatments-photo/spine-back.jpg',
                'promo_heading' => 'Ready to be seen? Book a serial',
                'promo_cta_text' => 'Book a serial',
                'promo_cta_link' => '/book',
                'faqs' => [
                    [
                        'question' => 'Will the injection hurt?',
                        'answer' => 'A tiny sting from the local anaesthetic — after that, most patients say it feels like pressure, not pain. We stay in conversation throughout.',
                    ],
                    [
                        'question' => 'How soon will I feel relief?',
                        'answer' => 'Many nerve blocks bring relief within hours. Steroid effects settle in over 3–7 days. Regenerative treatments build over 4–6 weeks.',
                    ],
                    [
                        'question' => 'Do I need admission?',
                        'answer' => 'No. Nearly every procedure is day-care. You come in, we treat, you rest for about 30 minutes, and go home.',
                    ],
                    [
                        'question' => 'Will I still need surgery later?',
                        'answer' => 'For most chronic pain, no. The goal is to solve the pain without the operating table — but if surgery is truly the right answer, we say so and refer you.',
                    ],
                    [
                        'question' => 'What does the first visit cost?',
                        'answer' => 'Consultation fees are posted at each centre. Procedures are quoted upfront with no surprise add-ons. Pay at the chamber after the visit.',
                    ],
                    [
                        'question' => 'How do I book?',
                        'answer' => 'Book a serial on this site, call +880 1880-728711, or WhatsApp the same number. The desk is open 9 AM – 10 PM, seven days a week.',
                    ],
                ],
            ]],
            ['type' => 'cta_banner', 'data' => $this->ctaBlock()],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function locationsBlock(): array
    {
        return [
            'heading' => 'Find us close to home',
            'locations' => [
                [
                    'name' => 'Moin Uddin Pain Solution — Panchlaish',
                    'address' => 'Neurosense, Panchlaish, near Chittagong Medical College, Chattogram',
                    'operating_hours' => 'Sat 10 AM–8 PM · Sun–Mon 4–9 PM · Friday 4–8 PM',
                    'phone' => '+880 1880-728711',
                    'google_maps_url' => 'https://www.google.com/maps/search/?api=1&query='.rawurlencode('Neurosense Panchlaish Chittagong'),
                ],
                [
                    'name' => 'MUPS Dhaka Centre — Uttara',
                    'address' => 'Sector 4, Uttara, Dhaka — about 10 minutes from the airport',
                    'operating_hours' => 'Tue–Wed 5–9 PM · Thursday 10 AM–1 PM and 5–9 PM',
                    'phone' => '+880 1880-728711',
                    'google_maps_url' => 'https://www.google.com/maps/search/?api=1&query='.rawurlencode('Uttara Sector 4 Dhaka pain clinic'),
                ],
            ],
        ];
    }

    /**
     * @return list<array{title: string, description: string, image_url: string}>
     */
    private function promiseGallery(): array
    {
        return [
            [
                'title' => 'See the nerve. Treat the nerve.',
                'description' => 'Ultrasound-guided — the medicine lands on the exact target, not a best guess.',
                'image_url' => self::IMG.'/treatments-photo/nerve-pain.jpg',
            ],
            [
                'title' => 'In and out the same day',
                'description' => 'Most procedures take under 30 minutes. No admission. Rest, then go home.',
                'image_url' => self::IMG.'/treatments-photo/knee-hip.jpg',
            ],
            [
                'title' => 'We say no when it will not help',
                'description' => 'If tablets are enough, we say so. If surgery is truly needed, we refer you. No upsell.',
                'image_url' => self::IMG.'/treatments-photo/spine-back.jpg',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function ctaBlock(): array
    {
        return [
            'headline' => 'Book a serial in under a minute',
            'subheadline' => 'We call back within an hour. Desk 9 AM–10 PM, every day. Pay at the chamber after the visit — not online.',
            'cta_text' => 'Book a serial',
            'cta_link' => '/book',
            'trust_phone' => '01880-728711',
            'trust_address' => 'Dhaka · Chittagong',
        ];
    }

    private function fellowshipHtml(): string
    {
        return <<<'HTML'
<h2>Six modules. Twelve months.</h2>
<p>A structured curriculum from anatomy to independent practice — weekly cadaver labs, live cases and international webinars.</p>
<ol>
<li><strong>Applied anatomy &amp; US</strong> — cross-sectional anatomy, ultrasound physics and probe handling. Cadaver labs, live scanning, weekly quiz.</li>
<li><strong>Peripheral nerve blocks</strong> — upper and lower limb, plexus, truncal blocks. Sciatic, femoral, brachial plexus, truncal.</li>
<li><strong>Spinal interventions</strong> — epidural, facet, SIJ under fluoroscopy and ultrasound. Caudal and TF epidural, facet block/RFA, SIJ.</li>
<li><strong>Advanced RFA</strong> — thermal, pulsed and cooled RF. Genicular, sacroiliac, trigeminal.</li>
<li><strong>Regenerative &amp; MSK</strong> — PRP, prolotherapy, viscosupplementation. Shoulder capsule, knee OA, tendinopathy.</li>
<li><strong>Practice &amp; research</strong> — ethics, consent, billing, journal club, capstone, FIPP prep.</li>
</ol>
<h2>Who should apply?</h2>
<p>Designed for anaesthetists, physiatrists and orthopaedic surgeons who want a high-volume, mentor-led pathway into interventional pain.</p>
<ul>
<li>MBBS + valid BMDC registration at start</li>
<li>Post-graduate degree: DA / MD / FCPS in anaesthesia, PMR or orthopaedics</li>
<li>Minimum 2 years post-PG clinical practice</li>
<li>Full-time, Sunday–Thursday, both centres</li>
<li>English for journal club and international webinars</li>
</ul>
<h2>Fees, dates and deadlines</h2>
<p><strong>Start:</strong> February 2026, rolling admissions until filled.</p>
<p><strong>Programme fee:</strong> ৳ 3,50,000 — includes cadaver labs and WIP webinars.</p>
<p><strong>Apply by:</strong> 31 December. Review within 7 working days.</p>
HTML;
    }
}
