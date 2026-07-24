<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * fest_school_event_fees only had unique(event_id, school_id) — useless for the
 * school_id-only lookups SchoolPaymentHistoryService::buildRows() and every caller of
 * rowsForSahodaya()/rowsForSchool() actually run (whereIn('school_id', ...) with no
 * event_id in the filter). See docs/SCALE_AND_PAGINATION_PLAN.md §1.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fest_school_event_fees')) {
            Schema::table('fest_school_event_fees', function (Blueprint $table) {
                if (! $this->indexExists('fest_school_event_fees', 'fest_school_event_fees_school_status_idx')) {
                    $table->index(['school_id', 'status'], 'fest_school_event_fees_school_status_idx');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('fest_school_event_fees')) {
            Schema::table('fest_school_event_fees', function (Blueprint $table) {
                if ($this->indexExists('fest_school_event_fees', 'fest_school_event_fees_school_status_idx')) {
                    $table->dropIndex('fest_school_event_fees_school_status_idx');
                }
            });
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();

        if ($driver === 'pgsql') {
            $result = $connection->select(
                'SELECT 1 FROM pg_indexes WHERE tablename = ? AND indexname = ?',
                [$table, $index]
            );

            return count($result) > 0;
        }

        if ($driver === 'mysql') {
            $result = $connection->select(
                'SHOW INDEX FROM '.$table.' WHERE Key_name = ?',
                [$index]
            );

            return count($result) > 0;
        }

        if ($driver === 'sqlite') {
            $result = $connection->select(
                "SELECT 1 FROM sqlite_master WHERE type = 'index' AND name = ?",
                [$index]
            );

            return count($result) > 0;
        }

        return false;
    }
};
