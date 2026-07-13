<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FRD-08 Phase 4: pair/trio participant types + result_method on fest items.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fest_event_items')) {
            $this->widenParticipantType('fest_event_items');

            if (! Schema::hasColumn('fest_event_items', 'result_method')) {
                Schema::table('fest_event_items', function (Blueprint $table) {
                    $table->string('result_method', 30)->nullable()->after('ranking_direction');
                });
            }
        }

        if (Schema::hasTable('fest_catalog_items') && ! Schema::hasColumn('fest_catalog_items', 'result_method')) {
            Schema::table('fest_catalog_items', function (Blueprint $table) {
                $table->string('result_method', 30)->nullable()->after('participant_type');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('fest_event_items') && Schema::hasColumn('fest_event_items', 'result_method')) {
            Schema::table('fest_event_items', function (Blueprint $table) {
                $table->dropColumn('result_method');
            });
        }

        if (Schema::hasTable('fest_catalog_items') && Schema::hasColumn('fest_catalog_items', 'result_method')) {
            Schema::table('fest_catalog_items', function (Blueprint $table) {
                $table->dropColumn('result_method');
            });
        }
    }

    private function widenParticipantType(string $table): void
    {
        if (! Schema::hasColumn($table, 'participant_type')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE `{$table}` MODIFY `participant_type` VARCHAR(20) NOT NULL DEFAULT 'individual'");
        }
    }
};
