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

    // Max rows per HTTP request (higher = fewer round-trips)
    'batch_size' => (int) env('SYNC_BATCH_SIZE', 250),

    // Seconds between status checks in the browser
    'heartbeat_seconds' => (int) env('SYNC_HEARTBEAT_SECONDS', 20),

    // Browser auto push+pull on heartbeat (recommended for full two-way sync)
    'auto_push_heartbeat' => (bool) env('SYNC_AUTO_PUSH_HEARTBEAT', true),

    // Cache hosting ping result (seconds) — avoids wait every status poll
    'remote_ping_cache_seconds' => (int) env('SYNC_REMOTE_PING_CACHE_SECONDS', 30),

    // HTTP timeout for push/pull batches (seconds)
    'push_timeout_seconds' => (int) env('SYNC_PUSH_TIMEOUT_SECONDS', 20),

    // Min seconds between background push attempts
    'push_debounce_seconds' => (int) env('SYNC_PUSH_DEBOUNCE_SECONDS', 15),

    // Min seconds between background pull attempts
    'pull_debounce_seconds' => (int) env('SYNC_PULL_DEBOUNCE_SECONDS', 15),

    // Parallel HTTP pulls (tables at once)
    'pull_parallel' => (int) env('SYNC_PULL_PARALLEL', 3),

    // Background pull: always these hot tables, plus N cold tables rotated each cycle
    'pull_cold_per_cycle' => (int) env('SYNC_PULL_COLD_PER_CYCLE', 10),

    'pull_hot_tables' => array_values(array_filter(array_map('trim', explode(',', (string) env(
        'SYNC_PULL_HOT_TABLES',
        'contacts,pos_orders,pos_order_items,pos_payments,credit_ledger,inventory_products,inventory_product_stocks,inventory_moves,expenses,settings,users,pos_sessions,pos_tables,purchase_orders,employees,payroll_entries'
    ))))),

    // Local pulls these tables from hosting (online → cafe PC).
    // Use "*" for full database (all syncable tables).
    'pull_tables' => array_values(array_filter(array_map('trim', explode(',', (string) env(
        'SYNC_PULL_TABLES',
        '*'
    ))))),

    // First pull without cursor: only rows newer than this many days
    'pull_lookback_days' => (int) env('SYNC_PULL_LOOKBACK_DAYS', 120),

    // Auto pull after push / on heartbeat / schedule
    'auto_pull' => (bool) env('SYNC_AUTO_PULL', true),

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
