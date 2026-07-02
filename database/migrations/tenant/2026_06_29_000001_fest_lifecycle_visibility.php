<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fest_events', function (Blueprint $table) {
            if (! Schema::hasColumn('fest_events', 'schedule_published')) {
                $table->boolean('schedule_published')->default(false)->after('registration_locked');
            }
            if (! Schema::hasColumn('fest_events', 'require_all_marks_before_publish')) {
                $table->boolean('require_all_marks_before_publish')->default(false)->after('require_judge_scores_before_publish');
            }
        });
    }

    public function down(): void
    {
        Schema::table('fest_events', function (Blueprint $table) {
            foreach (['schedule_published', 'require_all_marks_before_publish'] as $col) {
                if (Schema::hasColumn('fest_events', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
