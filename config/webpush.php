<?php

return [
    /*
    | Pocket buzz on the patient ticket. No SMS. Generate a key pair with:
    | php -r 'require "vendor/autoload.php"; echo json_encode(Minishlink\WebPush\VAPID::createVapidKeys()), PHP_EOL;'
    */
    'vapid' => [
        'public_key' => env('VAPID_PUBLIC_KEY', ''),
        'private_key' => env('VAPID_PRIVATE_KEY', ''),
        'subject' => env('VAPID_SUBJECT', 'mailto:hello@example.com'),
    ],
];
