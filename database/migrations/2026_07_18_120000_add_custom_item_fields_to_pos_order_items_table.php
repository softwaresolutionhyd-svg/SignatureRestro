<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connections = array_values(array_unique(array_filter([
            'tenant',
            'mysql',
            (new \App\Models\PosOrderItem)->getConnectionName(),
        ])));

        foreach ($connections as $connection) {
            $schema = Schema::connection($connection);
            if (! $schema->hasTable('pos_order_items')) {
                continue;
            }

            $schema->table('pos_order_items', function (Blueprint $table) use ($schema) {
                if (! $schema->hasColumn('pos_order_items', 'item_name')) {
                    $table->string('item_name', 255)->nullable()->after('product_id');
                }
                if (! $schema->hasColumn('pos_order_items', 'is_custom')) {
                    $table->boolean('is_custom')->default(false)->after('item_name');
                }
            });
        }
    }

    public function down(): void
    {
        $connections = array_values(array_unique(array_filter([
            'tenant',
            'mysql',
            (new \App\Models\PosOrderItem)->getConnectionName(),
        ])));

        foreach ($connections as $connection) {
            $schema = Schema::connection($connection);
            if (! $schema->hasTable('pos_order_items')) {
                continue;
            }

            $schema->table('pos_order_items', function (Blueprint $table) use ($schema) {
                if ($schema->hasColumn('pos_order_items', 'is_custom')) {
                    $table->dropColumn('is_custom');
                }
                if ($schema->hasColumn('pos_order_items', 'item_name')) {
                    $table->dropColumn('item_name');
                }
            });
        }
    }
};
