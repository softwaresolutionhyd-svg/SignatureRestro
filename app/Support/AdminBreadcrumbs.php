<?php

namespace App\Support;

final class AdminBreadcrumbs
{
    /**
     * @return list<array{label: string, url: ?string}>
     */
    public static function items(): array
    {
        $name = request()->route()?->getName() ?? '';

        if ($name === 'dashboard' || $name === 'home') {
            return [['label' => 'Dashboard', 'url' => null]];
        }

        if ($name === 'analytics') {
            return [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Analytics', 'url' => null]];
        }

        if (str_starts_with($name, 'platform.manual-update.')) {
            return [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Manual update', 'url' => null],
            ];
        }

        if (str_starts_with($name, 'platform.updates.')) {
            return self::platformUpdates($name);
        }

        $dash = ['label' => 'Dashboard', 'url' => route('dashboard')];

        if (str_starts_with($name, 'updates.')) {
            return [$dash, ['label' => 'Updates', 'url' => null]];
        }

        return match (true) {
            str_starts_with($name, 'my-attendance') => [$dash, ['label' => 'My attendance', 'url' => null]],
            str_starts_with($name, 'activity-logs.') => [$dash, ['label' => 'Activity logs', 'url' => null]],
            str_starts_with($name, 'admin.') => self::admin($name, $dash),
            $name === 'admin' => [$dash, ['label' => 'Admin', 'url' => null]],
            $name === 'inventory.low-stock'       => [$dash, ['label'=>'Inventory','url'=>route('inventory.index')], ['label'=>'Low Stock Report','url'=>null]],
            str_starts_with($name, 'inventory.') => self::inventory($name, $dash),
            str_starts_with($name, 'manufacturing.') => self::manufacturing($name, $dash),
            str_starts_with($name, 'maintenance.') => [$dash, ['label' => 'Maintenance', 'url' => null]],
            str_starts_with($name, 'custom-forms.') => [$dash, ['label' => 'Custom Forms', 'url' => null]],
            str_starts_with($name, 'purchase.') => self::purchase($name, $dash),
            str_starts_with($name, 'restaurant-pos.') => [$dash, ['label' => 'Restaurant POS', 'url' => $name === 'restaurant-pos.index' ? null : route('restaurant-pos.index')]],
            str_starts_with($name, 'order-taker.') => [$dash, ['label' => 'Order Taker', 'url' => $name === 'order-taker.index' ? null : route('order-taker.index')]],
            str_starts_with($name, 'kitchen.') => [$dash, ['label' => 'Kitchen', 'url' => $name === 'kitchen.index' ? null : route('kitchen.index')]],
            str_starts_with($name, 'hr.') => self::hr($name, $dash),
            str_starts_with($name, 'employees.') => self::employees($name, $dash),
            str_starts_with($name, 'notifications.') => [$dash, ['label' => 'Notifications', 'url' => null]],
            str_starts_with($name, 'profile.') => [$dash, ['label' => 'Profile', 'url' => null]],
            str_starts_with($name, 'settings.') => [$dash, ['label' => 'Settings', 'url' => null]],
            str_starts_with($name, 'reports.')  => self::reports($name, $dash),
            str_starts_with($name, 'expenses.')  => self::expenses($name, $dash),
            str_starts_with($name, 'accounts.')  => self::accounts($name, $dash),
            str_starts_with($name, 'calendar.')    => [$dash, ['label' => 'Calendar',    'url' => null]],
            str_starts_with($name, 'contacts.')    => self::contacts($name, $dash),
            str_starts_with($name, 'credit-book.') => [$dash, ['label' => 'Credit Book', 'url' => null]],
            default => [$dash, ['label' => 'App', 'url' => null]],
        };
    }

    /**
     * @param  array{label: string, url: ?string}  $dash
     * @return list<array{label: string, url: ?string}>
     */
    private static function inventory(string $name, array $dash): array
    {
        $out = [$dash, ['label' => 'Inventory', 'url' => $name === 'inventory.index' ? null : route('inventory.index')]];
        if ($name === 'inventory.index') {
            return $out;
        }

        if (str_contains($name, '.products.')) {
            $productsUrl = null;
            if ($name !== 'inventory.products.index') {
                $productsUrl = route('inventory.products.index');
                $ret = SafeInternalReturnPath::normalize(request()->query('return'));
                if ($ret !== null) {
                    $indexPath = parse_url(route('inventory.products.index', [], false), PHP_URL_PATH);
                    $retPath = parse_url($ret, PHP_URL_PATH);
                    if (is_string($indexPath) && $indexPath !== '' && is_string($retPath) && rtrim($retPath, '/') === rtrim($indexPath, '/')) {
                        $productsUrl = url($ret);
                    }
                }
            }
            $out[] = ['label' => 'Products', 'url' => $productsUrl];
            self::appendCrudTail($out, $name);
        } elseif (str_contains($name, '.categories.')) {
            $out[] = ['label' => 'Categories', 'url' => $name === 'inventory.categories.index' ? null : route('inventory.categories.index')];
            self::appendCrudTail($out, $name);
        } elseif (str_contains($name, '.departments.')) {
            $out[] = ['label' => 'Departments', 'url' => $name === 'inventory.departments.index' ? null : route('inventory.departments.index')];
            self::appendCrudTail($out, $name);
        } elseif (str_contains($name, '.issues.')) {
            $out[] = ['label' => 'Issue Stock', 'url' => $name === 'inventory.issues.index' ? null : route('inventory.issues.index')];
            if ($name === 'inventory.issues.create' || $name === 'inventory.issues.store') {
                $out[] = ['label' => 'New', 'url' => null];
            }
        } elseif (str_contains($name, '.moves.')) {
            $out[] = ['label' => 'Moves', 'url' => $name === 'inventory.moves.index' ? null : route('inventory.moves.index')];
            self::appendCrudTail($out, $name);
        } elseif (str_starts_with($name, 'inventory.stock-in.')) {
            $out[] = ['label' => 'Stock in', 'url' => null];
        } elseif (str_contains($name, '.stock-check.')) {
            $out[] = ['label' => 'Stock check', 'url' => $name === 'inventory.stock-check.index' ? null : route('inventory.stock-check.index')];
            if ($name === 'inventory.stock-check.index') {
                return $out;
            }
            if (in_array($name, ['inventory.stock-check.create', 'inventory.stock-check.store'], true)) {
                $out[] = ['label' => 'New', 'url' => null];

                return $out;
            }
            if (str_contains($name, 'inventory.stock-check.edit') || str_contains($name, 'inventory.stock-check.update')) {
                $out[] = ['label' => 'Edit', 'url' => null];

                return $out;
            }
            if ($name === 'inventory.stock-check.show') {
                $out[] = ['label' => 'View', 'url' => null];

                return $out;
            }

            return $out;
        } elseif (str_contains($name, 'uom-library')) {
            $out[] = ['label' => 'Units & conversions', 'url' => null];
        }

        return $out;
    }

    /**
     * @param  array{label: string, url: ?string}  $dash
     * @return list<array{label: string, url: ?string}>
     */
    /**
     * @param  array{label: string, url: ?string}  $dash
     * @return list<array{label: string, url: ?string}>
     */
    private static function manufacturing(string $name, array $dash): array
    {
        $hub = ['label' => 'Manufacturing', 'url' => $name === 'manufacturing.index' ? null : route('manufacturing.index')];
        $out = [$dash, $hub];

        if ($name === 'manufacturing.index') {
            return $out;
        }

        if (str_contains($name, '.boms.')) {
            $out[] = ['label' => 'BoMs', 'url' => $name === 'manufacturing.boms.index' ? null : route('manufacturing.boms.index')];
            if ($name === 'manufacturing.boms.show') {
                $out[] = ['label' => 'View', 'url' => null];
            } else {
                self::appendCrudTail($out, $name);
            }

            return $out;
        }

        if (str_contains($name, '.orders.')) {
            $out[] = ['label' => 'Orders', 'url' => $name === 'manufacturing.orders.index' ? null : route('manufacturing.orders.index')];
            if ($name === 'manufacturing.orders.complete') {
                $out[] = ['label' => 'Complete', 'url' => null];

                return $out;
            }
            if (in_array($name, ['manufacturing.orders.create', 'manufacturing.orders.store'], true)) {
                $out[] = ['label' => 'New order', 'url' => null];
            } elseif ($name === 'manufacturing.orders.show') {
                $out[] = ['label' => 'Order', 'url' => null];
            }

            return $out;
        }

        return $out;
    }

    private static function purchase(string $name, array $dash): array
    {
        $out = [$dash, ['label' => 'Purchase', 'url' => $name === 'purchase.index' ? null : route('purchase.index')]];
        if ($name === 'purchase.index') {
            return $out;
        }

        if (str_contains($name, '.vendors.')) {
            $out[] = ['label' => 'Vendors', 'url' => $name === 'purchase.vendors.index' ? null : route('purchase.vendors.index')];
            self::appendCrudTail($out, $name);
        } elseif (str_contains($name, '.orders.')) {
            $out[] = ['label' => 'Orders', 'url' => $name === 'purchase.orders.index' ? null : route('purchase.orders.index')];
            if (str_ends_with($name, '.confirm')) {
                $out[] = ['label' => 'Confirm', 'url' => null];
            } elseif (str_ends_with($name, '.receive')) {
                $out[] = ['label' => 'Receive', 'url' => null];
            } else {
                self::appendCrudTail($out, $name);
            }
        }

        return $out;
    }

    /**
     * @param  array{label: string, url: ?string}  $dash
     * @return list<array{label: string, url: ?string}>
     */
    private static function hr(string $name, array $dash): array
    {
        $hub = ['label' => 'HR', 'url' => $name === 'hr.index' ? null : route('hr.index')];

        if ($name === 'hr.index') {
            return [$dash, $hub];
        }

        if (str_starts_with($name, 'hr.leave.')) {
            $out = [$dash, $hub, [
                'label' => 'Leave',
                'url' => $name === 'hr.leave.index' ? null : route('hr.leave.index'),
            ]];
            if ($name === 'hr.leave.create' || $name === 'hr.leave.store') {
                $out[] = ['label' => 'Request', 'url' => null];
            } elseif ($name === 'hr.leave.show' || str_contains($name, 'approve') || str_contains($name, 'reject')) {
                $out[] = ['label' => 'Details', 'url' => null];
            }

            return $out;
        }

        return [$dash, $hub];
    }

    /**
     * @param  array{label: string, url: ?string}  $dash
     * @return list<array{label: string, url: ?string}>
     */
    private static function employees(string $name, array $dash): array
    {
        $hrHub = ['label' => 'HR', 'url' => route('hr.index')];

        if (str_contains($name, '.attendance.')) {
            $leaf = ['label' => 'Attendance', 'url' => $name === 'employees.attendance.index' ? null : route('employees.attendance.index')];

            return [$dash, $hrHub, $leaf];
        }

        if (str_contains($name, '.payroll.')) {
            $leaf = ['label' => 'Payroll', 'url' => $name === 'employees.payroll.index' ? null : route('employees.payroll.index')];

            return [$dash, $hrHub, $leaf];
        }

        if (str_contains($name, '.departments.')) {
            $out = [$dash, $hrHub, [
                'label' => 'Departments',
                'url' => $name === 'employees.departments.index' ? null : route('employees.departments.index'),
            ]];
            self::appendCrudTail($out, $name);

            return $out;
        }

        if (str_contains($name, '.designations.')) {
            $out = [$dash, $hrHub, [
                'label' => 'Designations',
                'url' => $name === 'employees.designations.index' ? null : route('employees.designations.index'),
            ]];
            self::appendCrudTail($out, $name);

            return $out;
        }

        $out = [$dash, $hrHub, ['label' => 'Employees', 'url' => $name === 'employees.index' ? null : route('employees.index')]];
        if ($name === 'employees.index') {
            return $out;
        }

        if (in_array($name, ['employees.create', 'employees.store'], true)) {
            $out[] = ['label' => 'Create', 'url' => null];
        } elseif (str_contains($name, 'employees.edit') || str_contains($name, 'employees.update')) {
            $out[] = ['label' => 'Edit', 'url' => null];
        }

        return $out;
    }

    /**
     * @param  array{label: string, url: ?string}  $dash
     * @return list<array{label: string, url: ?string}>
     */
    private static function reports(string $name, array $dash): array
    {
        $hub = ['label' => 'Reports', 'url' => $name === 'reports.index' ? null : route('reports.index')];
        if ($name === 'reports.index') return [$dash, $hub];

        $labels = [
            'reports.sales'      => 'Sales',
            'reports.purchases'  => 'Purchases',
            'reports.inventory'  => 'Inventory',
            'reports.employees'  => 'Employees',
        ];
        return [$dash, $hub, ['label' => $labels[$name] ?? 'Report', 'url' => null]];
    }

    /**
     * @param  array{label: string, url: ?string}  $dash
     * @return list<array{label: string, url: ?string}>
     */
    private static function contacts(string $name, array $dash): array
    {
        $hub = ['label' => 'Contacts', 'url' => $name === 'contacts.index' ? null : route('contacts.index')];
        if ($name === 'contacts.index') return [$dash, $hub];
        $out = [$dash, $hub];
        if (in_array($name, ['contacts.create','contacts.store'], true)) $out[] = ['label' => 'New Contact','url'=>null];
        elseif (in_array($name, ['contacts.edit','contacts.update'], true)) $out[] = ['label' => 'Edit','url'=>null];
        elseif ($name === 'contacts.show') $out[] = ['label' => 'View Contact','url'=>null];
        return $out;
    }

    private static function expenses(string $name, array $dash): array
    {
        $hub = ['label' => 'Expenses', 'url' => $name === 'expenses.index' ? null : route('expenses.index')];
        if ($name === 'expenses.index') return [$dash, $hub];

        if (str_contains($name, '.categories.')) {
            $out = [$dash, $hub, [
                'label' => 'Categories',
                'url'   => $name === 'expenses.categories.index' ? null : route('expenses.categories.index'),
            ]];
            self::appendCrudTail($out, $name);
            return $out;
        }

        $out = [$dash, $hub];
        if (in_array($name, ['expenses.create', 'expenses.store'], true)) {
            $out[] = ['label' => 'New Expense', 'url' => null];
        } elseif (in_array($name, ['expenses.edit', 'expenses.update'], true)) {
            $out[] = ['label' => 'Edit Expense', 'url' => null];
        } elseif ($name === 'expenses.show') {
            $out[] = ['label' => 'View Expense', 'url' => null];
        }
        return $out;
    }

    private static function accounts(string $name, array $dash): array
    {
        $hub = ['label' => 'Accounts', 'url' => $name === 'accounts.index' ? null : route('accounts.index')];
        if ($name === 'accounts.index') {
            return [$dash, $hub];
        }

        if (str_starts_with($name, 'accounts.chart-of-accounts.')) {
            $out = [$dash, $hub, [
                'label' => 'Chart of Accounts',
                'url' => $name === 'accounts.chart-of-accounts.index' ? null : route('accounts.chart-of-accounts.index'),
            ]];
            self::appendCrudTail($out, $name);

            return $out;
        }

        if (str_starts_with($name, 'accounts.journal-entries.')) {
            $out = [$dash, $hub, [
                'label' => 'Journal Entries',
                'url' => in_array($name, ['accounts.journal-entries.index'], true) ? null : route('accounts.journal-entries.index'),
            ]];
            if (in_array($name, ['accounts.journal-entries.create', 'accounts.journal-entries.store'], true)) {
                $out[] = ['label' => 'New Entry', 'url' => null];
            } elseif (in_array($name, ['accounts.journal-entries.edit', 'accounts.journal-entries.update'], true)) {
                $out[] = ['label' => 'Edit Entry', 'url' => null];
            } elseif ($name === 'accounts.journal-entries.show') {
                $out[] = ['label' => 'View Entry', 'url' => null];
            }

            return $out;
        }

        if (str_starts_with($name, 'accounts.reports.')) {
            return [$dash, $hub, ['label' => 'Trial Balance', 'url' => null]];
        }

        return [$dash, $hub];
    }

    /**
     * @param  list<array{label: string, url: ?string}>  $out
     */
    private static function appendCrudTail(array &$out, string $name): void
    {
        if (str_ends_with($name, '.create') || str_ends_with($name, '.store')) {
            $out[] = ['label' => 'Create', 'url' => null];
        } elseif (str_ends_with($name, '.edit') || str_ends_with($name, '.update')) {
            $out[] = ['label' => 'Edit', 'url' => null];
        }
    }

    /**
     * @param  array{label: string, url: ?string}  $dash
     * @return list<array{label: string, url: ?string}>
     */
    /**
     * @return list<array{label: string, url: ?string}>
     */
    private static function platformUpdates(string $name): array
    {
        $dash = ['label' => 'Dashboard', 'url' => route('dashboard')];
        $hub = ['label' => 'Release notes (admin)', 'url' => $name === 'platform.updates.index' ? null : route('platform.updates.index')];

        if ($name === 'platform.updates.index') {
            return [$dash, $hub];
        }

        if (in_array($name, ['platform.updates.create', 'platform.updates.store'], true)) {
            return [$dash, $hub, ['label' => 'New entry', 'url' => null]];
        }

        if (str_contains($name, 'platform.updates.edit') || str_contains($name, 'platform.updates.update')) {
            return [$dash, $hub, ['label' => 'Edit', 'url' => null]];
        }

        return [$dash, $hub];
    }

  /**
     * @param  array{label: string, url: ?string}  $dash
     * @return list<array{label: string, url: ?string}>
     */
    private static function guestRooms(string $name, array $dash): array
    {
        $hub = ['label' => 'Guest Rooms', 'url' => $name === 'guest-rooms.index' ? null : route('guest-rooms.index')];
        if ($name === 'guest-rooms.index') {
            return [$dash, $hub];
        }

        $sections = [
            'guest-rooms.categories.' => ['Categories', 'guest-rooms.categories.index'],
            'guest-rooms.rooms.' => ['Rooms', 'guest-rooms.rooms.index'],
            'guest-rooms.cleaning.' => ['Cleaning', 'guest-rooms.cleaning.index'],
            'guest-rooms.room-maintenance.' => ['Room Maintenance', 'guest-rooms.room-maintenance.index'],
            'guest-rooms.rates.' => ['Rates', 'guest-rooms.rates.index'],
            'guest-rooms.bookings.' => ['Bookings', 'guest-rooms.bookings.index'],
            'guest-rooms.billing.' => ['Billing', 'guest-rooms.billing.index'],
            'guest-rooms.reports.' => ['Reports', 'guest-rooms.reports.index'],
        ];

        foreach ($sections as $prefix => [$label, $route]) {
            if (str_starts_with($name, $prefix)) {
                $out = [$dash, $hub, ['label' => $label, 'url' => $name === $route ? null : route($route)]];
                self::appendCrudTail($out, $name);
                if ($name === 'guest-rooms.bookings.checkout' || $name === 'guest-rooms.bookings.checkout.store') {
                    $out[] = ['label' => 'Checkout', 'url' => null];
                }
                if ($name === 'guest-rooms.bookings.bill-receipt') {
                    $out[] = ['label' => 'Print Bill', 'url' => null];
                }
                if ($name === 'guest-rooms.bookings.change-rooms' || $name === 'guest-rooms.bookings.rooms.update' || $name === 'guest-rooms.bookings.rooms.release') {
                    $out[] = ['label' => 'Change Rooms', 'url' => null];
                }
                if ($name === 'guest-rooms.bookings.show') {
                    $out[] = ['label' => 'Details', 'url' => null];
                }
                if ($name === 'guest-rooms.billing.show' || $name === 'guest-rooms.billing.receipt') {
                    $out[] = ['label' => 'Bill', 'url' => $name === 'guest-rooms.billing.show' ? null : route('guest-rooms.billing.show', request()->route('bill'))];
                }
                if ($name === 'guest-rooms.billing.receipt') {
                    $out[] = ['label' => 'Print', 'url' => null];
                }
                return $out;
            }
        }

        return [$dash, $hub];
    }

    private static function admin(string $name, array $dash): array
    {
        if ($name === 'admin.users.index') {
            return [$dash, ['label' => 'Users & roles', 'url' => null]];
        }

        if (str_contains($name, 'admin.users.')) {
            return [$dash, ['label' => 'Users & roles', 'url' => route('admin.users.index')], ['label' => 'Edit', 'url' => null]];
        }

        if (str_starts_with($name, 'admin.password-reset-requests.')) {
            return [$dash, ['label' => 'Password reset requests', 'url' => null]];
        }

        return [$dash, ['label' => 'Admin', 'url' => null]];
    }
}
