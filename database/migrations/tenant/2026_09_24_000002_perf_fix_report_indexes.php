<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Performance fix indexes — see PERFORMANCE_FIX_PLAN_2026_08_13.md Phase 4.
 *
 * 1. fest_registrations(event_id, item_id, status): complements the existing
 *    fest_reg_event_school_item_status_idx (event_id, school_id, item_id, status) from
 *    2026_07_06_160002_erp_tenant_scale_indexes.php, which only helps school-scoped
 *    queries due to the leftmost-prefix rule (school_id sits between event_id and
 *    item_id, so a query that filters by event_id + item_id + status but no
 *    school_id can't use it efficiently). Several Sahodaya-wide report queries do
 *    exactly that shape (FestEventReportAnalyticsService::assignmentCompletenessRows()/
 *    itemRegistrationRows(), FestReportService::markEntryStatusRows(), etc.) — this
 *    index targets those directly, additively, alongside the existing one.
 * 2. students: trigram GIN index on lower(name) so `LOWER(name) LIKE '%term%'`
 *    (FestRegistrationController::eligibleStudents(), McqController::exam() search)
 *    can use an index scan instead of a sequential scan. Requires the Postgres
 *    pg_trgm extension; guarded so the migration doesn't hard-fail if the DB user
 *    lacks CREATE EXTENSION privileges (common on some managed Postgres hosts) — in
 *    that case the index is skipped (logged via report()) and name search keeps its
 *    current sequential-scan behavior rather than the whole migration run failing.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fest_registrations') && ! Schema::hasIndex('fest_registrations', 'fest_reg_event_item_status_idx')) {
            Schema::table('fest_registrations', function (Blueprint $table) {
                $table->index(['event_id', 'item_id', 'status'], 'fest_reg_event_item_status_idx');
            });
        }

        if (Schema::hasTable('students') && Schema::getConnection()->getDriverName() === 'pgsql') {
            $this->addStudentNameTrigramIndex();
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('fest_registrations') && Schema::hasIndex('fest_registrations', 'fest_reg_event_item_status_idx')) {
            Schema::table('fest_registrations', function (Blueprint $table) {
                $table->dropIndex('fest_reg_event_item_status_idx');
            });
        }

        if (Schema::hasTable('students') && Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::getConnection()->statement('DROP INDEX IF EXISTS students_name_trgm_idx');
        }
    }

    private function addStudentNameTrigramIndex(): void
    {
        if (Schema::hasIndex('students', 'students_name_trgm_idx')) {
            return;
        }

        $connection = Schema::getConnection();

        try {
            $connection->statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
            $connection->statement(
                'CREATE INDEX students_name_trgm_idx ON students USING GIN (lower(name) gin_trgm_ops)'
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }
};
