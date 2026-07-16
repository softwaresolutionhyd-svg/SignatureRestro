<?php

namespace App\Support;

use App\Models\PosSittingArea;
use App\Models\PosTable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

final class PosTablesSchema
{
    public static function ensure(): void
    {
        try {
            $schema = Schema::connection('tenant');

            if (! $schema->hasTable('pos_sitting_areas')) {
                $schema->create('pos_sitting_areas', function (Blueprint $table) {
                    $table->id();
                    $table->string('name', 80)->unique();
                    $table->unsignedSmallInteger('sort_order')->default(0);
                    $table->boolean('active')->default(true);
                    $table->timestamps();
                });
            }

            if (! $schema->hasTable('pos_tables')) {
                $schema->create('pos_tables', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('sitting_area_id')
                        ->nullable()
                        ->constrained('pos_sitting_areas')
                        ->nullOnDelete();
                    $table->string('name', 60);
                    $table->boolean('active')->default(true);
                    $table->timestamps();
                    $table->unique(['sitting_area_id', 'name']);
                });

                return;
            }

            if (! $schema->hasColumn('pos_tables', 'sitting_area_id')) {
                $schema->table('pos_tables', function (Blueprint $table) {
                    $table->foreignId('sitting_area_id')
                        ->nullable()
                        ->after('id')
                        ->constrained('pos_sitting_areas')
                        ->nullOnDelete();
                });

                try {
                    $schema->table('pos_tables', function (Blueprint $table) {
                        $table->dropUnique(['name']);
                    });
                } catch (\Throwable) {
                    // Index name may differ.
                }

                try {
                    $schema->table('pos_tables', function (Blueprint $table) {
                        $table->unique(['sitting_area_id', 'name']);
                    });
                } catch (\Throwable) {
                    // Already exists.
                }
            }

            self::backfillOrphanTables($schema);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private static function backfillOrphanTables($schema): void
    {
        if (! $schema->hasColumn('pos_tables', 'sitting_area_id')) {
            return;
        }

        $orphanIds = PosTable::query()
            ->whereNull('sitting_area_id')
            ->pluck('id');

        if ($orphanIds->isEmpty()) {
            return;
        }

        $area = PosSittingArea::query()->firstOrCreate(
            ['name' => 'General'],
            ['sort_order' => 0, 'active' => true]
        );

        PosTable::query()
            ->whereIn('id', $orphanIds)
            ->update(['sitting_area_id' => $area->id]);
    }
}
