<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pos_sitting_areas')) {
            Schema::create('pos_sitting_areas', function (Blueprint $table) {
                $table->id();
                $table->string('name', 80)->unique();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->boolean('active')->default(true);
                $table->timestamps();
            });
        }

        if (Schema::hasTable('pos_tables') && ! Schema::hasColumn('pos_tables', 'sitting_area_id')) {
            Schema::table('pos_tables', function (Blueprint $table) {
                $table->foreignId('sitting_area_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('pos_sitting_areas')
                    ->nullOnDelete();
            });

            // Allow same table name in different sitting areas.
            try {
                Schema::table('pos_tables', function (Blueprint $table) {
                    $table->dropUnique(['name']);
                });
            } catch (\Throwable) {
                // Index name may differ across environments.
            }

            try {
                Schema::table('pos_tables', function (Blueprint $table) {
                    $table->unique(['sitting_area_id', 'name']);
                });
            } catch (\Throwable) {
                // Already exists.
            }
        }

        if (Schema::hasTable('pos_tables') && Schema::hasTable('pos_sitting_areas')) {
            $orphanCount = DB::table('pos_tables')->whereNull('sitting_area_id')->count();
            if ($orphanCount > 0) {
                $areaId = DB::table('pos_sitting_areas')->where('name', 'General')->value('id');
                if (! $areaId) {
                    $areaId = DB::table('pos_sitting_areas')->insertGetId([
                        'name' => 'General',
                        'sort_order' => 0,
                        'active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
                DB::table('pos_tables')
                    ->whereNull('sitting_area_id')
                    ->update(['sitting_area_id' => $areaId]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pos_tables') && Schema::hasColumn('pos_tables', 'sitting_area_id')) {
            Schema::table('pos_tables', function (Blueprint $table) {
                $table->dropConstrainedForeignId('sitting_area_id');
            });
        }

        Schema::dropIfExists('pos_sitting_areas');
    }
};
