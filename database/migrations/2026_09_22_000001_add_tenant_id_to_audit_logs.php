<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Fixes RPT-01 (functional audit, 2026-08-11/12): the audit_logs table lives on
// the central connection (shared across every tenant on the platform, see
// App\Models\AuditLog's CentralConnection trait) but had no tenant/sahodaya
// column at all, so five ERP-hub reports (Audit Trail, Auth Events, Finance
// Audit, Export Activity, Failed Logins) were querying it with zero tenant
// scoping — any Sahodaya admin/staff/finance user could see every other
// federation's audit history. This adds the missing column, indexes it, and
// backfills what can be safely inferred from existing rows. Rows that can't be
// confidently attributed to a tenant are left null and will simply not appear
// in any tenant-scoped report (fail closed, not fail open).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('tenant_id')->nullable()->after('user_id')->index();
        });

        // Best-effort backfill from the properties JSON blob, which several
        // logger call sites (mcq(), training(), festEvent(), festCatalog(),
        // portalProvisioned(), and the auth-event context built in
        // AuthController::auditContext()) already populate with a tenant_id.
        // MySQL/MariaDB and PostgreSQL both support JSON_EXTRACT / ->> here;
        // SQLite (used in tests) is handled separately below since it needs a
        // different JSON accessor syntax.
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            DB::statement(<<<'SQL'
                UPDATE audit_logs
                SET tenant_id = json_extract(properties, '$.tenant_id')
                WHERE tenant_id IS NULL
                  AND properties IS NOT NULL
                  AND json_extract(properties, '$.tenant_id') IS NOT NULL
            SQL);

            DB::statement(<<<'SQL'
                UPDATE audit_logs
                SET tenant_id = json_extract(properties, '$.school_id')
                WHERE tenant_id IS NULL
                  AND properties IS NOT NULL
                  AND json_extract(properties, '$.school_id') IS NOT NULL
            SQL);
        } else {
            DB::statement(<<<'SQL'
                UPDATE audit_logs
                SET tenant_id = JSON_UNQUOTE(JSON_EXTRACT(properties, '$.tenant_id'))
                WHERE tenant_id IS NULL
                  AND properties IS NOT NULL
                  AND JSON_EXTRACT(properties, '$.tenant_id') IS NOT NULL
            SQL);

            DB::statement(<<<'SQL'
                UPDATE audit_logs
                SET tenant_id = JSON_UNQUOTE(JSON_EXTRACT(properties, '$.school_id'))
                WHERE tenant_id IS NULL
                  AND properties IS NOT NULL
                  AND JSON_EXTRACT(properties, '$.school_id') IS NOT NULL
            SQL);
        }
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropColumn('tenant_id');
        });
    }
};
