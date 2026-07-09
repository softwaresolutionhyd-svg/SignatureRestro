<?php

namespace App\Support;

final class ModuleAccess
{
    /** Route-name prefix => label (employee / user permission matrix). */
    public const DEFINITIONS = [
        'inventory' => 'Inventory',
        'purchase' => 'Purchase',
        'restaurant-pos' => 'Restaurant POS',
        'order-taker' => 'Order Taker',
        'kitchen' => 'Kitchen',
        'order-status' => 'Order Status',
        'hr' => 'HR',
        'manufacturing' => 'Manufacturing',
        'maintenance' => 'Maintenance',
        'custom-forms' => 'Custom Forms',
        'expenses' => 'Expenses',
        'accounts' => 'Accounts',
        'reports' => 'Reports',
        'analytics' => 'Analytics',
        'contacts' => 'Contacts',
        'credit-book' => 'Credit Book',
        'calendar' => 'Calendar',
    ];

    /** Legacy permission keys merged when checking HR access. */
    private const HR_PERMISSION_ALIASES = ['hr', 'employees'];

    /** Legacy POS Restaurant permissions count as Restaurant POS. */
    private const RESTAURANT_POS_PERMISSION_ALIASES = ['restaurant-pos', 'pos'];

    /**
     * @return list<string>
     */
    public static function moduleKeys(): array
    {
        return array_keys(self::DEFINITIONS);
    }

    /** Map route module prefix to permission matrix key. */
    public static function permissionModuleKey(string $routeModule): string
    {
        return match ($routeModule) {
            'employees' => 'hr',
            'pos' => 'restaurant-pos',
            default => $routeModule,
        };
    }

    /**
     * Permission keys to check for a module (includes legacy aliases).
     *
     * @return list<string>
     */
    public static function permissionKeysFor(string $module): array
    {
        if ($module === 'hr') {
            return self::HR_PERMISSION_ALIASES;
        }

        if ($module === 'restaurant-pos') {
            return self::RESTAURANT_POS_PERMISSION_ALIASES;
        }

        return [$module];
    }

    /**
     * @return array<string, array{view: bool, create: bool, edit: bool, delete: bool, all: bool}>
     */
    public static function normalize(array $permissions): array
    {
        $merged = $permissions;

        // Migrate legacy employees permissions into hr when saving new users.
        if (! isset($merged['hr']) && isset($merged['employees'])) {
            $merged['hr'] = $merged['employees'];
        }

        // Migrate legacy POS Restaurant permissions into Restaurant POS.
        if (! isset($merged['restaurant-pos']) && isset($merged['pos'])) {
            $merged['restaurant-pos'] = $merged['pos'];
        }

        $out = [];
        foreach (self::DEFINITIONS as $m => $_label) {
            $allRaw = data_get($merged, $m.'.all');
            $allOn = $allRaw === true || $allRaw === 1 || $allRaw === '1' || $allRaw === 'on';

            $out[$m] = [
                'view' => false,
                'create' => false,
                'edit' => false,
                'delete' => false,
                'all' => false,
            ];

            foreach (['view', 'create', 'edit', 'delete'] as $a) {
                $raw = data_get($merged, $m.'.'.$a);
                $on = $allOn || $raw === true || $raw === 1 || $raw === '1' || $raw === 'on';
                $out[$m][$a] = $on;
            }

            if ($out[$m]['create'] || $out[$m]['edit'] || $out[$m]['delete']) {
                $out[$m]['view'] = true;
            }

            $out[$m]['all'] = $allOn
                || ($out[$m]['view'] && $out[$m]['create'] && $out[$m]['edit'] && $out[$m]['delete']);
        }

        return $out;
    }
}
