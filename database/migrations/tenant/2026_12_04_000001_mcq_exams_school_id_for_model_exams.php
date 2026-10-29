<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * School-owned model exams share the Sahodaya tenant DB (tenant_id = parent Sahodaya)
 * but are scoped to one school via school_id + conductor_level=school.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mcq_exams')) {
            return;
        }

        Schema::table('mcq_exams', function (Blueprint $table) {
            if (! Schema::hasColumn('mcq_exams', 'school_id')) {
                $table->string('school_id')->nullable()->after('tenant_id')->index();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('mcq_exams') || ! Schema::hasColumn('mcq_exams', 'school_id')) {
            return;
        }

        Schema::table('mcq_exams', function (Blueprint $table) {
            $table->dropColumn('school_id');
        });
    }
};
