<?php

/**
 * Per-company installable features (tenant DB migrations only — no arbitrary uploads).
 * Keys are referenced from company_updates.feature_key.
 */
return [
    'packages' => [
        'stock_check' => [
            'label' => 'Stock check (count + admin approval)',
            'migrations' => [
                'database/migrations/tenant/features/2026_04_11_100002_create_stock_check_tables.php',
            ],
        ],
    ],
];
