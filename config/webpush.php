<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Web Push (PWA phone / desktop system notifications)
    |--------------------------------------------------------------------------
    |
    | Leave keys empty to auto-generate into storage/app/vapid.json.
    | Online hosting and cafe PC can each have their own keys — subscriptions
    | are stored per server. Cafe relays push events to hosting via sync token.
    |
    */
    'vapid' => [
        'subject' => env('VAPID_SUBJECT', 'mailto:admin@softwaresolutions.pk'),
        'public_key' => env('VAPID_PUBLIC_KEY', ''),
        'private_key' => env('VAPID_PRIVATE_KEY', ''),
    ],

    // Cafe (SYNC_ROLE=local): also POST alert to hosting so installed online PWA gets it
    'relay_to_remote' => (bool) env('WEBPUSH_RELAY_TO_REMOTE', true),

    'relay_timeout_seconds' => max(2, (int) env('WEBPUSH_RELAY_TIMEOUT', 4)),
];
