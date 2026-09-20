<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'school_no')) {
                // Permanent, stable per-Sahodaya sequence number for a school
                // (1, 2, 3...) — assigned once (lazily, on first use — see
                // Tenant::schoolCode()) and never reused/renumbered, so it stays
                // valid on ID cards already printed. Unique per parent_id (Sahodaya);
                // multiple NULLs (unassigned schools, and every Sahodaya row itself)
                // are allowed under a standard unique index.
                $table->unsignedInteger('school_no')->nullable()->after('school_prefix');
                $table->unique(['parent_id', 'school_no']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (Schema::hasColumn('tenants', 'school_no')) {
                $table->dropUnique(['parent_id', 'school_no']);
                $table->dropColumn('school_no');
            }
        });
    }
};
