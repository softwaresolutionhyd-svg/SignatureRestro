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
        'menu_deals' => [
            'label' => 'Menu deals (combo + duration)',
            'migrations' => [
                'database/migrations/tenant/features/2026_08_14_120000_create_menu_deal_tables.php',
            ],
        ],
    ],
];
