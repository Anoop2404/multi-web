<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('board_results', function (Blueprint $table) {
            if (! Schema::hasColumn('board_results', 'submission_count')) {
                $table->unsignedSmallInteger('submission_count')->default(0)->after('submitted_at');
            }
            if (! Schema::hasColumn('board_results', 'correction_history')) {
                $table->jsonb('correction_history')->nullable()->after('rejection_reason');
            }
        });

        // academic_awards is created by FRD-21 High migration; only ensure it exists for FK.
        if (! Schema::hasTable('academic_awards')) {
            Schema::create('academic_awards', function (Blueprint $table) {
                $table->id();
                $table->string('sahodaya_id')->index();
                $table->string('tenant_id')->nullable()->index();
                $table->string('academic_year');
                $table->unsignedBigInteger('academic_year_id')->nullable()->index();
                $table->string('award_type', 64);
                $table->string('title');
                $table->decimal('score', 10, 4)->nullable();
                $table->unsignedBigInteger('board_result_id')->nullable();
                $table->jsonb('meta')->nullable();
                $table->timestamp('computed_at')->nullable();
                $table->timestamps();
                $table->unique(['sahodaya_id', 'academic_year', 'award_type'], 'academic_awards_unique');
            });
        }

        Schema::table('achievements', function (Blueprint $table) {
            if (! Schema::hasColumn('achievements', 'academic_year')) {
                $table->string('academic_year', 20)->nullable()->after('level');
            }
            if (! Schema::hasColumn('achievements', 'source_award_id')) {
                $table->unsignedBigInteger('source_award_id')->nullable()->after('academic_year');
            }
            if (! Schema::hasColumn('achievements', 'is_system_generated')) {
                $table->boolean('is_system_generated')->default(false)->after('source_award_id');
            }
        });

        if (Schema::hasColumn('achievements', 'source_award_id') && Schema::hasTable('academic_awards')) {
            // Drop first if a previous partial run added the column without FK.
            try {
                Schema::table('achievements', function (Blueprint $table) {
                    $table->foreign('source_award_id')
                        ->references('id')
                        ->on('academic_awards')
                        ->nullOnDelete();
                });
            } catch (\Throwable) {
                // FK may already exist.
            }
        }

        if (Schema::hasColumn('achievements', 'academic_year')) {
            try {
                Schema::table('achievements', function (Blueprint $table) {
                    $table->index(['tenant_id', 'academic_year'], 'achievements_tenant_year_index');
                });
            } catch (\Throwable) {
                // Index may already exist.
            }
        }
    }

    public function down(): void
    {
        Schema::table('achievements', function (Blueprint $table) {
            try {
                $table->dropForeign(['source_award_id']);
            } catch (\Throwable) {
            }
            $cols = array_filter(
                ['academic_year', 'source_award_id', 'is_system_generated'],
                fn ($c) => Schema::hasColumn('achievements', $c)
            );
            if ($cols !== []) {
                $table->dropColumn($cols);
            }
        });

        Schema::table('board_results', function (Blueprint $table) {
            $cols = array_filter(
                ['submission_count', 'correction_history'],
                fn ($c) => Schema::hasColumn('board_results', $c)
            );
            if ($cols !== []) {
                $table->dropColumn($cols);
            }
        });
    }
};
