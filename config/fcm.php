<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Firebase Cloud Messaging (HTTP v1)
    |--------------------------------------------------------------------------
    |
    | Place the Firebase service-account JSON at storage/app/firebase-credentials.json
    | (or set FCM_CREDENTIALS to an absolute path). Project ID can be omitted when
    | present inside the JSON as project_id.
    |
    */

    'enabled' => (bool) env('FCM_ENABLED', true),

    'project_id' => env('FCM_PROJECT_ID', ''),

    'credentials' => env('FCM_CREDENTIALS', storage_path('app/firebase-credentials.json')),

    /*
    | Default Android notification channel — must match Flutter channel id.
    */
    'android_channel_id' => env('FCM_ANDROID_CHANNEL_ID', 'stair_pos_orders'),

    /*
    | Drop invalid / expired tokens automatically after FCM rejects them.
    */
    'prune_invalid_tokens' => (bool) env('FCM_PRUNE_INVALID_TOKENS', true),

];
