<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A wildcard/appeal registration is filed under the Sahodaya's placeholder
 * "Appeal School" tenant (school_id), so it's excluded from real-school
 * championship totals — origin_school_id records the student's real school
 * separately, for certificates and audit display only. Null on every normal
 * registration.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fest_registrations')) {
            return;
        }

        Schema::table('fest_registrations', function (Blueprint $table) {
            if (! Schema::hasColumn('fest_registrations', 'origin_school_id')) {
                $table->string('origin_school_id')->nullable()->after('school_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('fest_registrations')) {
            return;
        }

        Schema::table('fest_registrations', function (Blueprint $table) {
            if (Schema::hasColumn('fest_registrations', 'origin_school_id')) {
                $table->dropColumn('origin_school_id');
            }
        });
    }
};
