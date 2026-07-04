<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fest_events', function (Blueprint $table) {
            if (! Schema::hasColumn('fest_events', 'cluster_key')) {
                $table->string('cluster_key', 64)->nullable()->after('parent_event_id');
            }
            if (! Schema::hasColumn('fest_events', 'cluster_label')) {
                $table->string('cluster_label')->nullable()->after('cluster_key');
            }
        });

        if (Schema::hasTable('mcq_exams') && ! Schema::hasColumn('mcq_exams', 'question_paper_path')) {
            Schema::table('mcq_exams', function (Blueprint $table) {
                $table->string('question_paper_path')->nullable()->after('hall_instructions');
                $table->string('question_paper_label')->nullable()->after('question_paper_path');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('fest_events', 'cluster_label')) {
            Schema::table('fest_events', fn (Blueprint $table) => $table->dropColumn('cluster_label'));
        }
        if (Schema::hasColumn('fest_events', 'cluster_key')) {
            Schema::table('fest_events', fn (Blueprint $table) => $table->dropColumn('cluster_key'));
        }

        if (Schema::hasColumn('mcq_exams', 'question_paper_label')) {
            Schema::table('mcq_exams', fn (Blueprint $table) => $table->dropColumn('question_paper_label'));
        }
        if (Schema::hasColumn('mcq_exams', 'question_paper_path')) {
            Schema::table('mcq_exams', fn (Blueprint $table) => $table->dropColumn('question_paper_path'));
        }
    }
};
