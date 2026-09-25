<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lookups the big reports/certificates make by a column the existing composite indexes
 * can't lead with:
 *
 * - fest_marks(participant_id): every report eager-loads `mark` with
 *   WHERE participant_id IN (...); the unique index is (item_id, participant_id), so that
 *   predicate could not use it and scanned the table (item-wise: ~6,000 ids per request).
 * - fest_attendance(event_id, status): the absent report and certificate eligibility filter
 *   an event's rows by status = 'absent'; only (item_id, participant_id) existed.
 * - fest_schedules(participant_id): schedule lookups by participant (portal, admin lists)
 *   -- the unique index leads with item_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->addIndex('fest_marks', ['participant_id'], 'fest_marks_participant_idx');
        $this->addIndex('fest_attendance', ['event_id', 'status'], 'fest_attendance_event_status_idx');
        $this->addIndex('fest_schedules', ['participant_id'], 'fest_schedules_participant_idx');
    }

    public function down(): void
    {
        $this->dropIndex('fest_marks', 'fest_marks_participant_idx');
        $this->dropIndex('fest_attendance', 'fest_attendance_event_status_idx');
        $this->dropIndex('fest_schedules', 'fest_schedules_participant_idx');
    }

    /** @param  list<string>  $columns */
    private function addIndex(string $table, array $columns, string $name): void
    {
        if (Schema::hasTable($table) && ! Schema::hasIndex($table, $name)) {
            Schema::table($table, function (Blueprint $blueprint) use ($columns, $name) {
                $blueprint->index($columns, $name);
            });
        }
    }

    private function dropIndex(string $table, string $name): void
    {
        if (Schema::hasTable($table) && Schema::hasIndex($table, $name)) {
            Schema::table($table, function (Blueprint $blueprint) use ($name) {
                $blueprint->dropIndex($name);
            });
        }
    }
};
