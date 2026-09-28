<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A student can have more than two schedule slots clash at once (the detected-clashes
 * report already flags every overlapping pair for the same student) — the form could
 * only report two (schedule_id_a/schedule_id_b). schedule_ids holds the full list a
 * school picks in one report; schedule_id_a/b are kept (set to the first two) so
 * existing code reading them still works.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('fest_clash_requests', 'schedule_ids')) {
            Schema::table('fest_clash_requests', function (Blueprint $table) {
                $table->json('schedule_ids')->nullable()->after('schedule_id_b');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('fest_clash_requests', 'schedule_ids')) {
            Schema::table('fest_clash_requests', function (Blueprint $table) {
                $table->dropColumn('schedule_ids');
            });
        }
    }
};
