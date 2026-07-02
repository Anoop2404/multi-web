<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fest_events', function (Blueprint $table) {
            if (! Schema::hasColumn('fest_events', 'cloned_from_event_id')) {
                $table->unsignedBigInteger('cloned_from_event_id')->nullable()->after('parent_event_id');
            }
            if (! Schema::hasColumn('fest_events', 'scoring_locked')) {
                $table->boolean('scoring_locked')->default(false)->after('results_published');
            }
            if (! Schema::hasColumn('fest_events', 'appeals_open')) {
                $table->boolean('appeals_open')->default(true)->after('scoring_locked');
            }
            if (! Schema::hasColumn('fest_events', 'chest_reveal_mode')) {
                $table->enum('chest_reveal_mode', ['immediate', 'stage_entry'])->default('immediate')->after('appeals_open');
            }
            if (! Schema::hasColumn('fest_events', 'require_judge_scores_before_publish')) {
                $table->boolean('require_judge_scores_before_publish')->default(false)->after('chest_reveal_mode');
            }
            if (! Schema::hasColumn('fest_events', 'appeal_fee_amount')) {
                $table->decimal('appeal_fee_amount', 10, 2)->nullable()->after('require_judge_scores_before_publish');
            }
            if (! Schema::hasColumn('fest_events', 'certificate_collection_open')) {
                $table->boolean('certificate_collection_open')->default(false)->after('appeal_fee_amount');
            }
            if (! Schema::hasColumn('fest_events', 'registration_locked')) {
                $table->boolean('registration_locked')->default(false)->after('certificate_collection_open');
            }
        });

        Schema::table('fest_participants', function (Blueprint $table) {
            if (! Schema::hasColumn('fest_participants', 'chest_revealed_at')) {
                $table->timestamp('chest_revealed_at')->nullable()->after('chest_no');
            }
            if (! Schema::hasColumn('fest_participants', 'level_registration_number')) {
                $table->string('level_registration_number', 30)->nullable()->after('chest_revealed_at');
            }
        });

        Schema::table('fest_marks', function (Blueprint $table) {
            if (! Schema::hasColumn('fest_marks', 'measurement_value')) {
                $table->string('measurement_value', 50)->nullable()->after('score');
            }
            if (! Schema::hasColumn('fest_marks', 'measurement_unit')) {
                $table->string('measurement_unit', 20)->nullable()->after('measurement_value');
            }
        });

        Schema::table('fest_appeals', function (Blueprint $table) {
            if (! Schema::hasColumn('fest_appeals', 'fee_amount')) {
                $table->decimal('fee_amount', 10, 2)->nullable()->after('reason');
            }
            if (! Schema::hasColumn('fest_appeals', 'fee_paid_at')) {
                $table->timestamp('fee_paid_at')->nullable()->after('fee_amount');
            }
        });

        Schema::table('certificates', function (Blueprint $table) {
            if (! Schema::hasColumn('certificates', 'cert_type')) {
                $table->enum('cert_type', ['winner', 'participation'])->default('winner')->after('entity_id');
            }
            if (! Schema::hasColumn('certificates', 'collected_at')) {
                $table->timestamp('collected_at')->nullable()->after('generated_at');
            }
            if (! Schema::hasColumn('certificates', 'collected_by_user_id')) {
                $table->unsignedBigInteger('collected_by_user_id')->nullable()->after('collected_at');
            }
        });

        if (! Schema::hasTable('fest_venues')) {
            Schema::create('fest_venues', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id');
                $table->unsignedBigInteger('event_id')->nullable();
                $table->string('name');
                $table->string('location')->nullable();
                $table->unsignedInteger('capacity')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->foreign('event_id')->references('id')->on('fest_events')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('fest_combination_rules')) {
            Schema::create('fest_combination_rules', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('event_id');
                $table->string('school_id')->nullable();
                $table->enum('class_group', ['lp', 'up', 'hs', 'hss', 'open'])->nullable();
                $table->unsignedSmallInteger('max_arts_events')->nullable();
                $table->unsignedSmallInteger('max_sports_events')->nullable();
                $table->unsignedSmallInteger('max_common_events')->nullable();
                $table->unsignedSmallInteger('max_on_stage')->nullable();
                $table->unsignedSmallInteger('max_off_stage')->nullable();
                $table->unsignedSmallInteger('max_group')->nullable();
                $table->timestamps();
                $table->foreign('event_id')->references('id')->on('fest_events')->cascadeOnDelete();
                $table->unique(['event_id', 'school_id', 'class_group'], 'fest_combo_unique');
            });
        }

        if (! Schema::hasTable('fest_grade_configs')) {
            Schema::create('fest_grade_configs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('event_id');
                $table->unsignedBigInteger('item_id')->nullable();
                $table->enum('grade', ['A_plus', 'A', 'B', 'C']);
                $table->decimal('min_score', 8, 2)->nullable();
                $table->decimal('max_score', 8, 2)->nullable();
                $table->timestamps();
                $table->foreign('event_id')->references('id')->on('fest_events')->cascadeOnDelete();
                $table->foreign('item_id')->references('id')->on('fest_event_items')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('fest_point_rules')) {
            Schema::create('fest_point_rules', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('event_id');
                $table->enum('grade', ['A_plus', 'A', 'B', 'C'])->nullable();
                $table->unsignedTinyInteger('position')->nullable();
                $table->unsignedSmallInteger('points');
                $table->boolean('is_group')->default(false);
                $table->timestamps();
                $table->foreign('event_id')->references('id')->on('fest_events')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('fest_level_registrations')) {
            Schema::create('fest_level_registrations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('event_id');
                $table->unsignedBigInteger('student_id');
                $table->string('registration_number', 30);
                $table->timestamps();
                $table->foreign('event_id')->references('id')->on('fest_events')->cascadeOnDelete();
                $table->foreign('student_id')->references('id')->on('students')->cascadeOnDelete();
                $table->unique(['event_id', 'student_id']);
                $table->unique(['event_id', 'registration_number']);
            });
        }

        if (! Schema::hasTable('fest_volunteers')) {
            Schema::create('fest_volunteers', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('event_id');
                $table->string('name');
                $table->string('phone', 30)->nullable();
                $table->string('duty', 100)->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->foreign('event_id')->references('id')->on('fest_events')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('fest_individual_championship_points')) {
            Schema::create('fest_individual_championship_points', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('event_id');
                $table->unsignedBigInteger('student_id');
                $table->enum('category', ['lp', 'up', 'hs', 'hss', 'open'])->default('open');
                $table->enum('gender', ['male', 'female', 'open'])->default('open');
                $table->unsignedSmallInteger('points')->default(0);
                $table->timestamps();
                $table->foreign('event_id')->references('id')->on('fest_events')->cascadeOnDelete();
                $table->foreign('student_id')->references('id')->on('students')->cascadeOnDelete();
                $table->unique(['event_id', 'student_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fest_individual_championship_points');
        Schema::dropIfExists('fest_volunteers');
        Schema::dropIfExists('fest_level_registrations');
        Schema::dropIfExists('fest_point_rules');
        Schema::dropIfExists('fest_grade_configs');
        Schema::dropIfExists('fest_combination_rules');
        Schema::dropIfExists('fest_venues');

        Schema::table('certificates', function (Blueprint $table) {
            foreach (['cert_type', 'collected_at', 'collected_by_user_id'] as $col) {
                if (Schema::hasColumn('certificates', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('fest_appeals', function (Blueprint $table) {
            foreach (['fee_amount', 'fee_paid_at'] as $col) {
                if (Schema::hasColumn('fest_appeals', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('fest_marks', function (Blueprint $table) {
            foreach (['measurement_value', 'measurement_unit'] as $col) {
                if (Schema::hasColumn('fest_marks', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('fest_participants', function (Blueprint $table) {
            foreach (['chest_revealed_at', 'level_registration_number'] as $col) {
                if (Schema::hasColumn('fest_participants', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('fest_events', function (Blueprint $table) {
            foreach ([
                'cloned_from_event_id', 'scoring_locked', 'appeals_open', 'chest_reveal_mode',
                'require_judge_scores_before_publish', 'appeal_fee_amount', 'certificate_collection_open', 'registration_locked',
            ] as $col) {
                if (Schema::hasColumn('fest_events', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
