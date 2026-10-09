<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // AuditLog uses the central database. Concurrent builds avoid blocking log writes.
    public $withinTransaction = false;

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! Schema::hasTable('audit_logs')) {
            return;
        }

        DB::statement("CREATE INDEX CONCURRENTLY IF NOT EXISTS audit_logs_event_page_created_idx ON audit_logs ((properties->>'event_id'), (properties->>'page'), created_at DESC)");
        DB::statement("CREATE INDEX CONCURRENTLY IF NOT EXISTS audit_logs_tenant_program_created_idx ON audit_logs ((properties->>'tenant_id'), (properties->>'program'), created_at DESC)");
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS audit_logs_subject_created_idx ON audit_logs (subject_type, subject_id, created_at DESC)');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }
        foreach (['audit_logs_event_page_created_idx', 'audit_logs_tenant_program_created_idx', 'audit_logs_subject_created_idx'] as $index) {
            DB::statement('DROP INDEX CONCURRENTLY IF EXISTS '.$index);
        }
    }
};
