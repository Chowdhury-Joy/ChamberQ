<?php

return [

    'product_name' => env('MARKETING_PRODUCT_NAME', 'ChamberQ'),

    /*
    |--------------------------------------------------------------------------
    | Sales contact
    |--------------------------------------------------------------------------
    |
    | whatsapp: digits only for wa.me (include BD country code).
    | phone: display / tel: link number for call CTA.
    |
    */
    'whatsapp' => env('MARKETING_WHATSAPP', '8801700000000'),
    'phone' => env('MARKETING_PHONE', '01700000000'),

    'hero_image' => 'images/marketing/step-4-serial-ticket.png',

    'before_after' => [
        'before' => [
            'value' => '2 hrs',
            'label' => 'average wait before',
            'bullets' => [
                'Phone ringing through consults',
                'The sitting runs about 2 hours late',
                'Scramble at the chamber door',
                'You spend the evening calming the queue',
            ],
        ],
        'after' => [
            'value' => '15 min',
            'label' => 'average wait now',
            'bullets' => [
                'Serials booked without the phone',
                'Sittings stay around 15 minutes',
                'One live board per sitting',
                'Your name gets recommended',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | SMS confirmations (prepaid wallet only — no included free SMS)
    |--------------------------------------------------------------------------
    |
    | Clinics top up credits; each confirmation debits 1 credit. Pack prices
    | are sell-side copy for sales chats. Gateway COGS is typically ~৳0.35.
    |
    */
    'sms' => [
        'credit_price' => (float) env('MARKETING_SMS_CREDIT_PRICE', 0.50),
        'packs' => [
            ['credits' => 200, 'price' => 100],
            ['credits' => 500, 'price' => 225],
            ['credits' => 2000, 'price' => 800],
        ],
    ],

    'plans' => [
        'solo' => [
            // Sales name: Maestro. Internal plan_tier key stays `solo`.
            'name' => 'Maestro',
            'tagline' => 'For one doctor, up to 5 chambers — full package',
            // Full Solo/Maestro bundle (= all three modules). À la carte lives under `modules`.
            'setup' => (int) env('MARKETING_SOLO_SETUP', 15000),
            'monthly' => (int) env('MARKETING_SOLO_MONTHLY', 3000),
            'featured' => true,
            'features' => [
                'One doctor, up to 5 locations',
                'Website + online booking',
                'Live queue + outdoor TV',
                'Digital prescription',
                'We set it up with you',
            ],
        ],
        'clinic' => [
            'name' => 'Clinic',
            'tagline' => 'For multi-doctor clinics & labs',
            'setup' => (int) env('MARKETING_CLINIC_SETUP', 75000),
            'monthly' => (int) env('MARKETING_CLINIC_MONTHLY', 7500),
            'featured' => false,
            'features' => [
                'Multiple doctors & chambers',
                'Lab tests catalogue',
                'Same chamber tools, for a team',
                'Prepaid SMS confirmations (optional)',
                'We set it up with you',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Product modules (Solo à la carte)
    |--------------------------------------------------------------------------
    |
    | Unit prices when a client buys a subset. Selecting all three uses the
    | bundle amounts (setup discount vs sum of units; monthly equals the sum).
    | Clinic tier still uses `plans.clinic` list prices.
    |
    */
    'modules' => [
        'front_door' => [
            'setup' => (int) env('MARKETING_MODULE_FRONT_DOOR_SETUP', 7500),
            'monthly' => (int) env('MARKETING_MODULE_FRONT_DOOR_MONTHLY', 1000),
        ],
        'prescription' => [
            'setup' => (int) env('MARKETING_MODULE_PRESCRIPTION_SETUP', 2500),
            'monthly' => (int) env('MARKETING_MODULE_PRESCRIPTION_MONTHLY', 0),
        ],
        'live_queue' => [
            'setup' => (int) env('MARKETING_MODULE_LIVE_QUEUE_SETUP', 7500),
            'monthly' => (int) env('MARKETING_MODULE_LIVE_QUEUE_MONTHLY', 2000),
        ],
        'bundle_all' => [
            'setup' => (int) env('MARKETING_SOLO_SETUP', 15000),
            'monthly' => (int) env('MARKETING_SOLO_MONTHLY', 3000),
        ],
    ],

    'value_points' => [
        [
            'title' => 'Save your time',
            'caption' => 'Fewer phone interruptions. More of your day spent in the consult.',
            'image' => 'images/marketing/value-time.png',
        ],
        [
            'title' => 'The sitting stays on time',
            'caption' => 'One board you control. The hall stops asking when — and you stop repeating it.',
            'image' => 'images/marketing/value-wait.png',
        ],
        [
            'title' => 'Your name travels',
            'caption' => 'An orderly chamber gets recommended. That is the growth — not ads.',
            'image' => 'images/marketing/value-mouth.png',
            'featured' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | App-shot walkthrough
    |--------------------------------------------------------------------------
    |
    | Drop PNGs into public/images/marketing/ using the filenames below.
    | Missing files render as labeled placeholders — the page never breaks.
    |
    */
    'steps' => [
        [
            'key' => 'chamber',
            'title' => 'Your chamber online',
            'caption' => 'Your portfolio site. Book sits under your name, not a hospital template.',
            'image' => 'images/marketing/step-1-chamber-page.png',
        ],
        [
            'key' => 'session',
            'title' => 'Pick a session',
            'caption' => 'They choose a sitting. You see seats left before the day starts.',
            'image' => 'images/marketing/step-2-pick-session.png',
        ],
        [
            'key' => 'confirm',
            'title' => 'Confirm details',
            'caption' => 'Name and phone. Serial locked. Walk-ins still fit the same queue.',
            'image' => 'images/marketing/step-3-confirm-details.png',
        ],
        [
            'key' => 'ticket',
            'title' => 'Serial ticket',
            'caption' => 'They hold a ticket. You stop repeating the same “when is my turn?”',
            'image' => 'images/marketing/step-4-serial-ticket.png',
        ],
        [
            'key' => 'queue',
            'title' => 'Live queue',
            'caption' => 'You or your assistant call the next serial. One board per sitting.',
            'image' => 'images/marketing/step-5-live-queue.png',
        ],
        [
            'key' => 'doctor',
            'title' => 'Your day list',
            'caption' => 'You see who is booked, who is waiting, and who is next.',
            'image' => 'images/marketing/step-6-doctor-day-list.png',
        ],
    ],

];
