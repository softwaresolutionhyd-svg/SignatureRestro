<?php

namespace App\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/** Auto-add POS columns when hosting migrate was not run after FTP deploy. */
final class PosRuntimeSchema
{
    /**
     * Bump this whenever the ensure* methods below add a NEW column, so the
     * per-request skip-cache is invalidated. (Deploy/optimize also clears cache.)
     */
    private const SCHEMA_VERSION = '2026_07_13_1';

    /**
     * Run an ensure routine at most once per cache window (avoids hitting
     * information_schema on every POS request/poll). The closure should return
     * FALSE if it could not fully run (e.g. table missing) so we retry later.
     */
    private static function once(string $key, callable $fn): void
    {
        $cacheKey = 'pos_runtime_schema:' . self::SCHEMA_VERSION . ':' . $key;

        try {
            if (Cache::get($cacheKey)) {
                return;
            }
        } catch (\Throwable $e) {
            // Cache unavailable — just run the ensure.
        }

        $result = $fn();

        if ($result !== false) {
            try {
                Cache::put($cacheKey, true, now()->addHours(12));
            } catch (\Throwable $e) {
                // ignore cache write failure
            }
        }
    }

    public static function ensureForSessionSummary(?string $connection = null): void
    {
        self::ensureServiceChargeColumns($connection);
        self::ensureSessionsDailyClosing($connection);
    }

    public static function ordersHasColumn(string $column, ?string $connection = null): bool
    {
        $schema = Schema::connection($connection ?? (new \App\Models\PosOrder)->getConnectionName());

        return $schema->hasTable('pos_orders') && $schema->hasColumn('pos_orders', $column);
    }

    public static function ensureServiceChargeColumns(?string $connection = null): void
    {
        self::once('service_charge:' . ($connection ?? 'auto'), fn () => self::runServiceChargeColumns($connection));
    }

    private static function runServiceChargeColumns(?string $connection = null): bool
    {
        $ranForTable = false;

        foreach (self::resolveConnections($connection) as $conn) {
            $schema = Schema::connection($conn);
            if (! $schema->hasTable('pos_orders')) {
                continue;
            }
            $ranForTable = true;

            if (! $schema->hasColumn('pos_orders', 'service_charge_percent')) {
                try {
                    $schema->table('pos_orders', function (Blueprint $table) {
                        $table->decimal('service_charge_percent', 8, 3)->nullable();
                    });
                } catch (\Throwable $e) {
                    report($e);
                    self::rawAddColumn($conn, 'pos_orders', 'service_charge_percent', 'DECIMAL(8,3) NULL');
                }
            }

            if (! $schema->hasColumn('pos_orders', 'service_charge_total')) {
                try {
                    $schema->table('pos_orders', function (Blueprint $table) {
                        $table->decimal('service_charge_total', 12, 2)->default(0);
                    });
                } catch (\Throwable $e) {
                    report($e);
                    self::rawAddColumn($conn, 'pos_orders', 'service_charge_total', 'DECIMAL(12,2) NOT NULL DEFAULT 0');
                }
            }
        }

        return $ranForTable;
    }

    /**
     * @return list<string>
     */
    private static function resolveConnections(?string $preferred): array
    {
        $names = array_filter([
            $preferred,
            (new \App\Models\PosOrder)->getConnectionName(),
            'tenant',
            'mysql',
        ]);

        return array_values(array_unique($names));
    }

    private static function rawAddColumn(string $connection, string $table, string $column, string $sqlType): void
    {
        $schema = Schema::connection($connection);
        if ($schema->hasColumn($table, $column)) {
            return;
        }

        try {
            $schema->getConnection()->statement(
                sprintf('ALTER TABLE `%s` ADD COLUMN `%s` %s', $table, $column, $sqlType)
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public static function ensureOrdersTable(?string $connection = null): void
    {
        self::once('orders:' . ($connection ?? 'tenant'), fn () => self::runOrdersTable($connection));
    }

    private static function runOrdersTable(?string $connection = null): bool
    {
        $schema = Schema::connection($connection ?? 'tenant');

        if (! $schema->hasTable('pos_orders')) {
            return false;
        }

        try {
            if (! $schema->hasColumn('pos_orders', 'cash_tendered')) {
                $schema->table('pos_orders', function (Blueprint $table) {
                    $table->decimal('cash_tendered', 12, 2)->nullable();
                });
            }
            if (! $schema->hasColumn('pos_orders', 'cash_change')) {
                $schema->table('pos_orders', function (Blueprint $table) {
                    $table->decimal('cash_change', 12, 2)->nullable();
                });
            }
            if (! $schema->hasColumn('pos_orders', 'contact_id') && $schema->hasTable('contacts')) {
                $schema->table('pos_orders', function (Blueprint $table) {
                    $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
                });
            }
            if (! $schema->hasColumn('pos_orders', 'is_credit')) {
                $schema->table('pos_orders', function (Blueprint $table) {
                    $table->boolean('is_credit')->default(false);
                });
            }
            if (! $schema->hasColumn('pos_orders', 'bill_tax_percent')) {
                $schema->table('pos_orders', function (Blueprint $table) {
                    $table->decimal('bill_tax_percent', 8, 3)->nullable()->after('tax_total');
                });
            }
            if (! $schema->hasColumn('pos_orders', 'bill_discount_percent')) {
                $schema->table('pos_orders', function (Blueprint $table) {
                    $table->decimal('bill_discount_percent', 8, 3)->nullable()->after('bill_tax_percent');
                });
            }
            if (! $schema->hasColumn('pos_orders', 'is_owner_discount')) {
                $schema->table('pos_orders', function (Blueprint $table) {
                    $table->boolean('is_owner_discount')->default(false)->after('bill_discount_percent');
                });
            }
            if (! $schema->hasColumn('pos_orders', 'table_id') && $schema->hasTable('pos_tables')) {
                $schema->table('pos_orders', function (Blueprint $table) {
                    $table->foreignId('table_id')->nullable()->after('session_id')->constrained('pos_tables')->nullOnDelete();
                });
            }
            if (! $schema->hasColumn('pos_orders', 'guest_name')) {
                $schema->table('pos_orders', function (Blueprint $table) {
                    $table->string('guest_name', 120)->nullable()->after('contact_id');
                });
            }
            if (! $schema->hasColumn('pos_orders', 'room_no')) {
                $schema->table('pos_orders', function (Blueprint $table) {
                    $table->string('room_no', 50)->nullable()->after('guest_name');
                });
            }
            if (! $schema->hasColumn('pos_orders', 'waiter_name')) {
                $schema->table('pos_orders', function (Blueprint $table) {
                    $table->string('waiter_name', 120)->nullable()->after('room_no');
                });
            }
            if (! $schema->hasColumn('pos_orders', 'order_notes')) {
                $schema->table('pos_orders', function (Blueprint $table) {
                    $table->text('order_notes')->nullable()->after('waiter_name');
                });
            }
            if (! $schema->hasColumn('pos_orders', 'kitchen_notes')) {
                $schema->table('pos_orders', function (Blueprint $table) {
                    $table->text('kitchen_notes')->nullable()->after('order_notes');
                });
            }
            if (! $schema->hasColumn('pos_orders', 'serve_time')) {
                $schema->table('pos_orders', function (Blueprint $table) {
                    $table->string('serve_time', 10)->nullable()->after('waiter_name');
                });
            }
            if (! $schema->hasColumn('pos_orders', 'serve_date')) {
                $schema->table('pos_orders', function (Blueprint $table) {
                    $table->date('serve_date')->nullable()->after('serve_time');
                });
            }
            if (! $schema->hasColumn('pos_orders', 'serve_meal')) {
                $schema->table('pos_orders', function (Blueprint $table) {
                    $table->string('serve_meal', 20)->nullable()->after('serve_date');
                });
            }
            if (! $schema->hasColumn('pos_orders', 'kitchen_preparing_at')) {
                $schema->table('pos_orders', function (Blueprint $table) {
                    $table->timestamp('kitchen_preparing_at')->nullable()->after('kitchen_completed_at');
                });
            }
            if (! $schema->hasColumn('pos_orders', 'kitchen_ready_at')) {
                $schema->table('pos_orders', function (Blueprint $table) {
                    $table->timestamp('kitchen_ready_at')->nullable()->after('kitchen_preparing_at');
                });
            }
            if (! $schema->hasColumn('pos_orders', 'customer_type')) {
                $schema->table('pos_orders', function (Blueprint $table) {
                    $table->string('customer_type', 20)->default('mess_use')->after('contact_id');
                });
            }
            if (! $schema->hasColumn('pos_orders', 'sale_mode')) {
                $schema->table('pos_orders', function (Blueprint $table) {
                    $table->string('sale_mode', 20)->default('customer')->after('customer_type');
                });
            }
            if (! $schema->hasColumn('pos_orders', 'service_type')) {
                $schema->table('pos_orders', function (Blueprint $table) {
                    $table->string('service_type', 20)->nullable()->after('customer_type');
                });
            }
            if (! $schema->hasColumn('pos_orders', 'service_charge_percent')) {
                try {
                    $schema->table('pos_orders', function (Blueprint $table) {
                        $table->decimal('service_charge_percent', 8, 3)->nullable()->after('tax_total');
                    });
                } catch (\Throwable) {
                    self::ensureServiceChargeColumns($connection);
                }
            }
            if (! $schema->hasColumn('pos_orders', 'service_charge_total')) {
                try {
                    $schema->table('pos_orders', function (Blueprint $table) {
                        $table->decimal('service_charge_total', 12, 2)->default(0)->after('service_charge_percent');
                    });
                } catch (\Throwable) {
                    self::ensureServiceChargeColumns($connection);
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return true;
    }

    public static function ensureOrderItemsTable(?string $connection = null): void
    {
        // v2: includes kitchen_printed_at (bump key so older once-cache cannot skip column ensure)
        self::once('order_items_v2:' . ($connection ?? 'tenant'), fn () => self::runOrderItemsTable($connection));
    }

    private static function runOrderItemsTable(?string $connection = null): bool
    {
        $schema = Schema::connection($connection ?? 'tenant');

        if (! $schema->hasTable('pos_order_items')) {
            return false;
        }

        try {
            if (! $schema->hasColumn('pos_order_items', 'notes')) {
                $schema->table('pos_order_items', function (Blueprint $table) {
                    $table->string('notes', 255)->nullable()->after('tax_percent');
                });
            }
            if (! $schema->hasColumn('pos_order_items', 'kitchen_pending')) {
                $schema->table('pos_order_items', function (Blueprint $table) {
                    $table->boolean('kitchen_pending')->default(true)->after('notes');
                });
            }
            if (! $schema->hasColumn('pos_order_items', 'kitchen_served_at')) {
                $schema->table('pos_order_items', function (Blueprint $table) {
                    $table->timestamp('kitchen_served_at')->nullable()->after('kitchen_pending');
                });
            }
            if (! $schema->hasColumn('pos_order_items', 'kitchen_printed_at')) {
                $schema->table('pos_order_items', function (Blueprint $table) {
                    $table->timestamp('kitchen_printed_at')->nullable()->after('kitchen_served_at');
                });
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return true;
    }

    public static function ensureSessionsDailyClosing(?string $connection = null): void
    {
        self::once('sessions_daily:' . ($connection ?? 'tenant'), fn () => self::runSessionsDailyClosing($connection));
    }

    private static function runSessionsDailyClosing(?string $connection = null): bool
    {
        $schema = Schema::connection($connection ?? 'tenant');

        if (! $schema->hasTable('pos_sessions')) {
            return false;
        }

        try {
            if (! $schema->hasColumn('pos_sessions', 'business_date')) {
                $schema->table('pos_sessions', function (Blueprint $table) {
                    $table->date('business_date')->nullable()->after('session_no');
                });
            }
            if (! $schema->hasColumn('pos_sessions', 'closing_bank')) {
                $schema->table('pos_sessions', function (Blueprint $table) {
                    $table->decimal('closing_bank', 14, 2)->nullable()->after('closing_cash');
                });
            }
            if (! $schema->hasColumn('pos_sessions', 'closing_card')) {
                $schema->table('pos_sessions', function (Blueprint $table) {
                    $table->decimal('closing_card', 14, 2)->nullable()->after('closing_bank');
                });
            }
            if (! $schema->hasColumn('pos_sessions', 'amount_to_collect')) {
                $schema->table('pos_sessions', function (Blueprint $table) {
                    $table->decimal('amount_to_collect', 14, 2)->nullable()->after('closing_card');
                });
            }
            if (! $schema->hasColumn('pos_sessions', 'shift_started')) {
                $schema->table('pos_sessions', function (Blueprint $table) {
                    $table->boolean('shift_started')->default(false)->after('status');
                });
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return true;
    }
}
