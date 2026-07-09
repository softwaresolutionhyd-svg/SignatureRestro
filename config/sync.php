<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cloud sync (local PC ↔ hosting)
    |--------------------------------------------------------------------------
    |
    | Local role: app always writes to local MySQL. When the internet is up,
    | pending rows are pushed to the hosting API.
    |
    | Cloud role: hosting receives pushes and applies them to its database.
    |
    */

    'enabled' => (bool) env('SYNC_ENABLED', false),

    // local = cafe PC (source of truth while offline)
    // cloud = hosting receiver
    'role' => env('SYNC_ROLE', 'local'),

    'remote_url' => rtrim((string) env('SYNC_REMOTE_URL', ''), '/'),

    'token' => (string) env('SYNC_TOKEN', ''),

    // Max rows per HTTP request
    'batch_size' => (int) env('SYNC_BATCH_SIZE', 100),

    // Seconds between automatic push attempts (web heartbeat)
    'heartbeat_seconds' => (int) env('SYNC_HEARTBEAT_SECONDS', 30),

    // Tables never synced (local-only / framework)
    'exclude_tables' => [
        'migrations',
        'failed_jobs',
        'password_reset_tokens',
        'password_resets',
        'personal_access_tokens',
        'sessions',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'sync_queue',
        'sync_meta',
        'telescope_entries',
        'telescope_entries_tags',
        'telescope_monitoring',
    ],

];
