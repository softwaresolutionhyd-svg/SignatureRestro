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

    // Seconds between status checks in the browser (push is separate / less frequent)
    'heartbeat_seconds' => (int) env('SYNC_HEARTBEAT_SECONDS', 60),

    // Browser auto-push on load / interval (false = faster LAN; click badge or run sync:cloud)
    'auto_push_heartbeat' => (bool) env('SYNC_AUTO_PUSH_HEARTBEAT', false),

    // Cache hosting ping result (seconds) — avoids 8s wait every status poll
    'remote_ping_cache_seconds' => (int) env('SYNC_REMOTE_PING_CACHE_SECONDS', 45),

    // HTTP timeout for push batches (seconds)
    'push_timeout_seconds' => (int) env('SYNC_PUSH_TIMEOUT_SECONDS', 12),

    // Min seconds between background push attempts (web terminating + heartbeat)
    'push_debounce_seconds' => (int) env('SYNC_PUSH_DEBOUNCE_SECONDS', 90),

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
