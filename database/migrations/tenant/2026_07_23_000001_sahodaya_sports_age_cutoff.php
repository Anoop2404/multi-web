<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A single, Sahodaya-wide default age reference date for sports age-group
 * eligibility (U8/U10/U14/...), so admins don't have to set it separately on
 * every sports season event. FestEvent.sports_age_cutoff_date (per-event
 * override) still wins if set — this is only the fallback for events that
 * don't set their own.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sahodaya_profiles') && ! Schema::hasColumn('sahodaya_profiles', 'sports_age_cutoff_date')) {
            Schema::table('sahodaya_profiles', function (Blueprint $table) {
                $table->date('sports_age_cutoff_date')->nullable()->after('fest_class_group_scheme');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sahodaya_profiles') && Schema::hasColumn('sahodaya_profiles', 'sports_age_cutoff_date')) {
            Schema::table('sahodaya_profiles', function (Blueprint $table) {
                $table->dropColumn('sports_age_cutoff_date');
            });
        }
    }
};
