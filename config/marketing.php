<?php

return [

    'product_name' => env('MARKETING_PRODUCT_NAME', 'Doctor Gemini'),

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
                'Patients idle about 2 hours',
                'Scramble at the chamber door',
                'Weak word of mouth',
            ],
        ],
        'after' => [
            'value' => '15 min',
            'label' => 'average wait now',
            'bullets' => [
                'Serials booked online',
                'Waits around 15 minutes',
                'Calm live queue at the room',
                'Patients recommend you',
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
            'name' => 'Solo',
            'tagline' => 'For one doctor, up to 5 chambers',
            'setup' => (int) env('MARKETING_SOLO_SETUP', 5000),
            'monthly' => (int) env('MARKETING_SOLO_MONTHLY', 2000),
            'featured' => true,
            'features' => [
                'One doctor, up to 5 locations',
                'Branded patient site & online serial',
                'Live queue & patient ticket',
                'Prepaid SMS confirmations',
                'We set it up with you',
            ],
        ],
        'clinic' => [
            'name' => 'Clinic',
            'tagline' => 'For multi-doctor clinics & labs',
            'setup' => (int) env('MARKETING_CLINIC_SETUP', 25000),
            'monthly' => (int) env('MARKETING_CLINIC_MONTHLY', 7500),
            'featured' => false,
            'features' => [
                'Multiple doctors & chambers',
                'Lab tests catalogue',
                'Same patient experience, scaled',
                'Prepaid SMS confirmations',
                'We set it up with you',
            ],
        ],
    ],

    'value_points' => [
        [
            'title' => 'Save your time',
            'caption' => 'Fewer phone interruptions. More of your day spent seeing patients.',
            'image' => 'images/marketing/value-time.png',
        ],
        [
            'title' => 'Patients wait less',
            'caption' => 'They book ahead, know when to come, and sit idle far less.',
            'image' => 'images/marketing/value-wait.png',
        ],
        [
            'title' => 'They tell others',
            'caption' => 'Respected with their time, they recommend you. That is the growth.',
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
            'caption' => 'Patients land on your branded page and tap Book.',
            'image' => 'images/marketing/step-1-chamber-page.png',
        ],
        [
            'key' => 'session',
            'title' => 'Pick a session',
            'caption' => 'They choose a day and session. Seats left are clear.',
            'image' => 'images/marketing/step-2-pick-session.png',
        ],
        [
            'key' => 'confirm',
            'title' => 'Confirm details',
            'caption' => 'Name and phone. Serial locked. Pay at the chamber.',
            'image' => 'images/marketing/step-3-confirm-details.png',
        ],
        [
            'key' => 'ticket',
            'title' => 'Serial ticket',
            'caption' => 'They get a ticket with their place in line — shareable on WhatsApp.',
            'image' => 'images/marketing/step-4-serial-ticket.png',
        ],
        [
            'key' => 'queue',
            'title' => 'Live queue',
            'caption' => 'The waiting-room screen calls the next serial. Fair and calm.',
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
